<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Models\Setting;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * OTP пройден — продавец доказал владение продаваемым номером.
 *
 * Главная защита проекта (блюпринт, пробел №1). Google подтверждает
 * личность, но ничего не говорит о том, чей это номер.
 *
 * Исход — hold, а не reject: продавец ничего плохого не сделал, он просто
 * ещё не ввёл код. Показать ему «отклонено» за то, что он в процессе —
 * неправда, и он уйдёт.
 */
final class PhoneVerified implements ModerationRule
{
    public function name(): string
    {
        return 'otp_verified';
    }

    public function check(Listing $listing): RuleResult
    {
        // Фича-флаг только для dev: без OTP площадка становится
        // инструментом для публикации чужих телефонов.
        if (! Setting::get('features.otp_enabled', true)) {
            return RuleResult::pass(['otp_disabled' => true]);
        }

        if ($listing->phone_verified_at !== null) {
            return RuleResult::pass();
        }

        return RuleResult::hold('moderation.reasons.otp_pending');
    }
}
