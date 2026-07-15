<?php

namespace App\Services\Moderation;

use App\Models\Listing;
use App\Models\ModerationLog;
use App\Models\Setting;
use App\Services\Moderation\Rules\AccountAge;
use App\Services\Moderation\Rules\ContactsValid;
use App\Services\Moderation\Rules\NoActiveDuplicate;
use App\Services\Moderation\Rules\NoContactsInDescription;
use App\Services\Moderation\Rules\NoForbiddenContent;
use App\Services\Moderation\Rules\NotBlocklisted;
use App\Services\Moderation\Rules\NumberIsSellable;
use App\Services\Moderation\Rules\PhoneVerified;
use App\Services\Moderation\Rules\PriceInRange;
use App\Services\Moderation\Rules\ShopVerified;
use App\Services\Moderation\Rules\SubmissionRateLimit;
use Illuminate\Support\Facades\DB;

/**
 * Системная модерация — п.3 исходного ТЗ, раскрытый в блюпринте (раздел 5).
 *
 * ── Как решает ──────────────────────────────────────────────────────────
 * Прогоняет все правила и складывает их суждения:
 *
 *   любой reject           → rejected. Продавец видит причину и правит.
 *   любой hold             → pending. Не ошибка: OTP ещё не введён.
 *   score >= порога        → pending. Ручная очередь модератора.
 *   иначе                  → active. Публикуем.
 *
 * Порядок правил не влияет на исход — все отрабатывают всегда. Это
 * сознательно: ранний выход экономил бы запросы, но лишал бы модератора
 * полной картины. Объявление, отклонённое по блок-листу, могло ещё и
 * содержать телефон в описании, и это стоит знать.
 *
 * ── Идемпотентность ─────────────────────────────────────────────────────
 * Прогон можно повторять сколько угодно: при подаче, после OTP, после
 * правки. Решение пересчитывается с нуля. Поэтому редактирование
 * опубликованного объявления автоматически возвращает его на проверку —
 * иначе схема очевидна: опубликовать чистое, потом вписать спам.
 *
 * ── Чего правила не делают ──────────────────────────────────────────────
 * Не правят объявление. В частности, НЕ вырезают контакты из описания
 * молча: тихо менять то, что написал человек, — плохой способ решать
 * проблему. Нашли телефон в тексте — отправили модератору.
 */
final class ModerationPipeline
{
    /** @var list<ModerationRule> */
    private array $rules;

    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? [
            // Жёсткие: номер вообще не может быть опубликован
            app(NumberIsSellable::class),
            app(NotBlocklisted::class),
            app(NoActiveDuplicate::class),
            app(PriceInRange::class),
            app(ContactsValid::class),

            // Ожидание: не ошибка продавца
            app(PhoneVerified::class),

            // Подозрения: копятся в score
            app(NoContactsInDescription::class),
            app(NoForbiddenContent::class),
            app(SubmissionRateLimit::class),
            app(AccountAge::class),
            app(ShopVerified::class),
        ];
    }

    /**
     * Прогнать конвейер и применить решение к объявлению.
     */
    public function run(Listing $listing): Verdict
    {
        $results = [];

        foreach ($this->rules as $rule) {
            $results[$rule->name()] = $rule->check($listing);
        }

        $verdict = $this->decide($results);

        DB::transaction(function () use ($listing, $verdict, $results) {
            $this->log($listing, $results);
            $this->apply($listing, $verdict);
        });

        return $verdict;
    }

    /**
     * Прогнать без записи — для предпросмотра в админке и для тестов.
     */
    public function dryRun(Listing $listing): Verdict
    {
        $results = [];

        foreach ($this->rules as $rule) {
            $results[$rule->name()] = $rule->check($listing);
        }

        return $this->decide($results);
    }

    /**
     * @param  array<string, RuleResult>  $results
     */
    private function decide(array $results): Verdict
    {
        $score = 0;

        foreach ($results as $r) {
            if ($r->outcome === RuleOutcome::Flag) {
                $score += $r->score;
            }
        }

        // Отказ перебивает всё: чинить придётся в любом случае.
        foreach ($results as $r) {
            if ($r->outcome === RuleOutcome::Reject) {
                return new Verdict('rejected', $score, $results, $r->message());
            }
        }

        // Ожидание. Не ошибка — просто ещё не готово.
        foreach ($results as $r) {
            if ($r->outcome === RuleOutcome::Hold) {
                return new Verdict('pending', $score, $results, $r->message());
            }
        }

        $threshold = (int) Setting::get('moderation.manual_threshold', 3);

        if ($score >= $threshold) {
            return new Verdict('pending', $score, $results, __('moderation.reasons.manual_review'));
        }

        return new Verdict('active', $score, $results);
    }

    /**
     * @param  array<string, RuleResult>  $results
     */
    private function log(Listing $listing, array $results): void
    {
        // Логируем всё, включая pass. Без строк 'pass' невозможно отличить
        // «правило отработало и претензий нет» от «правило не запускалось» —
        // а это разные вещи, когда через полгода разбираешь спорный случай.
        $rows = [];
        $now = now();

        foreach ($results as $name => $r) {
            $rows[] = [
                'listing_id' => $listing->id,
                'rule'       => $name,
                'result'     => $r->outcome->value,
                'payload'    => $r->payload === [] ? null : json_encode($r->payload, JSON_UNESCAPED_UNICODE),
                'actor'      => 'system',
                'created_at' => $now,
            ];
        }

        ModerationLog::insert($rows);
    }

    private function apply(Listing $listing, Verdict $verdict): void
    {
        $attrs = [
            'status'           => $verdict->status,
            'moderation_score' => min($verdict->score, 255),
            'rejection_reason' => $verdict->status === 'rejected' ? $verdict->reason : null,
        ];

        if ($verdict->published()) {
            $ttl = (int) Setting::get('listing.ttl_days', 60);

            // published_at не перетираем при повторном прогоне: дата первой
            // публикации — это порядок сортировки на витрине, и сбрасывать
            // её при каждой правке значило бы поднимать объявление наверх
            // за счёт редактирования. Бесплатный буст, которым немедленно
            // начали бы пользоваться.
            $attrs['published_at'] = $listing->published_at ?? now();
            $attrs['expires_at']   = now()->addDays($ttl);
        }

        $listing->update($attrs);
    }
}
