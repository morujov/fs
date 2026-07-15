<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ContactReveal;
use App\Models\Listing;
use App\Models\ModerationLog;
use App\Models\OtpCode;
use App\Models\Report;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ограничение хранения — GDPR, ст. 5(1)(e).
 *
 * ── Зачем ───────────────────────────────────────────────────────────────
 * Персональные данные нельзя держать «на всякий случай»: у каждого срока
 * должна быть цель, и когда цель отпадает, данные обязаны уйти. У нас их
 * копилось немало и вечно: IP каждого раскрытия, IP каждого заявителя,
 * user-agent'ы, куски описаний в логах модерации.
 *
 * Ирония была в том, что мы построили сложную защиту контактов продавца
 * от посторонних — и параллельно вечно копили IP покупателей без единого
 * способа их стереть. Данные защищены от чужих и не защищены от нас.
 *
 * ── Обезличивание, а не удаление ────────────────────────────────────────
 * Главное решение здесь. Строку contact_reveals удалять нельзя: на ней
 * держится правило «повторное раскрытие бесплатно» — человек, вернувшийся
 * через полгода, не должен платить лимитом за то, что уже видел. Но IP
 * в этой строке нужен ровно на 24 часа — столько живут все окна лимитера.
 *
 * Поэтому: связь (кто, что, когда) остаётся, а IP и user-agent зануляются.
 * Это и есть минимизация: хранить не «всё или ничего», а ровно то, что
 * ещё работает на цель.
 */
class PruneOldData extends Command
{
    protected $signature = 'data:prune {--dry-run : Показать, не меняя}';

    protected $description = 'Обезличить и удалить персональные данные, у которых истёк срок хранения (GDPR ст. 5)';

    private bool $dry = false;

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');

        $this->table(['Что', 'Строк'], [
            ['IP в логе раскрытий',        $this->anonymizeReveals()],
            ['IP в закрытых жалобах',      $this->anonymizeReports()],
            ['IP в аудите',                $this->anonymizeAuditLogs()],
            ['Контакты в проданных',       $this->anonymizeSoldListings()],
            ['Логи модерации (удалено)',   $this->pruneModerationLogs()],
            ['OTP-коды (удалено)',         $this->pruneOtpCodes()],
        ]);

        if ($this->dry) {
            $this->warn('Это dry-run. Ничего не изменено.');
        }

        return self::SUCCESS;
    }

    /**
     * IP и user-agent в логе раскрытий.
     *
     * Строку НЕ удаляем: на ней держится «повтор бесплатно». Зануляем то,
     * что нужно было только лимитеру, а его окна не длиннее суток.
     */
    private function anonymizeReveals(): int
    {
        $days = (int) Setting::get('retention.reveal_ip_days', 90);

        $q = ContactReveal::where('created_at', '<', now()->subDays($days))
            ->whereNotNull('ip');

        return $this->apply($q, ['ip' => null, 'user_agent' => null]);
    }

    /**
     * IP заявителя в закрытых жалобах.
     *
     * Только в закрытых: пока жалоба открыта, IP — часть разбора.
     * Отсчёт от закрытия, а не от подачи.
     *
     * Антифлуд по UNIQUE(listing_id, reporter_ip) при этом слабеет — но
     * через полгода после закрытия он и не нужен: объявления, скорее всего,
     * уже нет.
     */
    private function anonymizeReports(): int
    {
        $days = (int) Setting::get('retention.report_ip_days', 180);

        $q = Report::whereIn('status', ['resolved', 'dismissed'])
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<', now()->subDays($days))
            ->whereNotNull('reporter_ip');

        // reporter_ip в UNIQUE-индексе, NULL там допустим и не конфликтует.
        return $this->apply($q, ['reporter_ip' => null, 'reporter_email' => null]);
    }

    private function anonymizeAuditLogs(): int
    {
        $days = (int) Setting::get('retention.audit_ip_days', 365);

        // Само действие оставляем навсегда: кто что удалил — это
        // подотчётность, и она не имеет срока. Уходит только IP.
        $q = AuditLog::where('created_at', '<', now()->subDays($days))
            ->whereNotNull('ip');

        return $this->apply($q, ['ip' => null]);
    }

    /**
     * Контакты в давно проданных объявлениях.
     *
     * Объявление оставляем: история продаж — это данные рынка, по ним
     * видно, за сколько уходят номера. Но контакты продавца через год
     * после продажи не нужны никому и ни для чего.
     */
    private function anonymizeSoldListings(): int
    {
        $days = (int) Setting::get('retention.sold_listing_days', 365);

        $q = Listing::withTrashed()
            ->whereIn('status', ['sold', 'archived', 'expired'])
            ->where('updated_at', '<', now()->subDays($days))
            ->whereNotNull('contact_phone');

        return $this->apply($q, [
            'contact_phone'    => '',
            'contact_email'    => null,
            'contact_name'     => '',
            'contact_whatsapp' => false,
        ]);
    }

    /**
     * Логи модерации.
     *
     * Здесь именно удаление, а не обезличивание: в payload правила
     * contacts_in_text лежат найденные телефоны и email из описания.
     * Обезличить payload построчно нельзя — он разный у каждого правила.
     */
    private function pruneModerationLogs(): int
    {
        $days = (int) Setting::get('retention.moderation_log_days', 365);

        $q = ModerationLog::where('created_at', '<', now()->subDays($days));

        if ($this->dry) {
            return $q->count();
        }

        return $q->delete();
    }

    /** Отработавшие OTP. Хэш кода бесполезен, msisdn — персональные данные. */
    private function pruneOtpCodes(): int
    {
        $days = (int) Setting::get('retention.otp_days', 30);

        $q = OtpCode::where('created_at', '<', now()->subDays($days));

        if ($this->dry) {
            return $q->count();
        }

        return $q->delete();
    }

    /**
     * Обновление пачкой.
     *
     * Через query builder, а не моделью: тут не нужны ни события, ни касты,
     * а строк может быть много. Побочный эффект — не трогается updated_at,
     * и это правильно: обезличивание не редактирование.
     */
    private function apply($query, array $attrs): int
    {
        if ($this->dry) {
            return $query->count();
        }

        return DB::transaction(fn () => $query->toBase()->update($attrs));
    }
}
