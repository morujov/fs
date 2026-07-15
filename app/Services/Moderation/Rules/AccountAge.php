<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Аккаунту не десять минут от роду.
 *
 * Свежесозданный Google-аккаунт, немедленно выставляющий номер, — типовая
 * заготовка спамера. Но это же и типовой честный сценарий: человек увидел
 * площадку, зашёл, продал.
 *
 * Поэтому вес маленький (1). Сам по себе он никуда не отправит: порог
 * ручной очереди — 3. Но в сумме с другим подозрением — телефон в описании,
 * одноразовая почта — уже да. Именно так это и должно работать: молодость
 * аккаунта не улика, а обстоятельство.
 */
final class AccountAge implements ModerationRule
{
    private const SUSPICIOUS_MINUTES = 60;

    public function name(): string
    {
        return 'account_age';
    }

    public function check(Listing $listing): RuleResult
    {
        $user = $listing->user;

        if ($user === null) {
            return RuleResult::pass();
        }

        $minutes = $user->created_at->diffInMinutes(now());

        if ($minutes >= self::SUSPICIOUS_MINUTES) {
            return RuleResult::pass();
        }

        // Только на первое объявление: если человек уже что-то публиковал
        // и это прошло, возраст аккаунта больше ни о чём не говорит.
        $isFirst = Listing::where('user_id', $user->id)
            ->where('id', '!=', $listing->id)
            ->doesntExist();

        if (! $isFirst) {
            return RuleResult::pass(['account_age_minutes' => $minutes]);
        }

        return RuleResult::flag(
            'moderation.reasons.account_too_new',
            score: 1,
            payload: ['account_age_minutes' => $minutes]
        );
    }
}
