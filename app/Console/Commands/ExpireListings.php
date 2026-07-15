<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Notifications\ListingExpired;
use App\Notifications\ListingExpiringSoon;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Истечение объявлений по TTL.
 *
 * ── Зачем ───────────────────────────────────────────────────────────────
 * Без этого доска за полгода зарастает мёртвыми номерами: продано, передумал,
 * забыл. Покупатель звонит по десяти объявлениям, все неактуальны — и больше
 * не возвращается. Это блокер №5 из блюпринта, и до сих пор его не было:
 * `expires_at` заполнялся, но никто его не читал.
 *
 * ── Почему не через `status` напрямую в SQL ──────────────────────────────
 * Прогоняем моделью, а не `update()` одним запросом, потому что при
 * истечении нужно отправить письмо. Объём смешной (десятки строк в день),
 * оптимизировать нечего.
 *
 * ── Важное следствие ────────────────────────────────────────────────────
 * Истечение освобождает номер: генерируемая колонка active_msisdn станет
 * NULL, и номер снова можно выставить. Иначе забытое объявление держало бы
 * номер занятым вечно.
 */
class ExpireListings extends Command
{
    protected $signature = 'listings:expire {--dry-run : Показать, не меняя}';

    protected $description = 'Пометить истёкшие объявления и предупредить тех, у кого срок на исходе';

    public function handle(): int
    {
        $notice = (int) Setting::get('listing.expiry_notice_days', 7);

        $warned = $this->warnExpiringSoon($notice);
        $expired = $this->expire();

        $this->info("Предупреждено: {$warned}. Истекло: {$expired}.");

        return self::SUCCESS;
    }

    /**
     * Предупредить за N дней.
     *
     * `expiry_notified_at` не даёт слать письмо каждый день все семь дней
     * подряд — человек отпишется от нас после третьего.
     */
    private function warnExpiringSoon(int $days): int
    {
        $listings = Listing::active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now())
            ->whereNull('expiry_notified_at')
            ->with('user')
            ->get();

        foreach ($listings as $listing) {
            if ($this->option('dry-run')) {
                $this->line("  предупредить: {$listing->msisdn}");

                continue;
            }

            $listing->user?->notify(new ListingExpiringSoon($listing));
            $listing->forceFill(['expiry_notified_at' => now()])->save();
        }

        return $listings->count();
    }

    private function expire(): int
    {
        $listings = Listing::active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with('user')
            ->get();

        foreach ($listings as $listing) {
            if ($this->option('dry-run')) {
                $this->line("  истекло: {$listing->msisdn}");

                continue;
            }

            // status → expired освобождает номер: active_msisdn станет NULL,
            // и его снова можно будет выставить. Ради этого всё и затевалось.
            $listing->update(['status' => 'expired']);

            $listing->user?->notify(new ListingExpired($listing));
        }

        return $listings->count();
    }
}
