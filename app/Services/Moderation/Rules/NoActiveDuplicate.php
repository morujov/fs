<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Один номер — одно активное объявление.
 *
 * Пять «продавцов» на один номер — это мошенничество: четверо из них
 * заведомо не владельцы.
 *
 * На уровне БД это уже гарантирует генерируемая колонка active_msisdn
 * с UNIQUE. Здесь проверка нужна, чтобы конвейер не пытался опубликовать
 * заведомо конфликтное объявление и не получал QueryException вместо
 * внятного отказа. БД — гарантия, это правило — вежливость.
 */
final class NoActiveDuplicate implements ModerationRule
{
    public function name(): string
    {
        return 'duplicate';
    }

    public function check(Listing $listing): RuleResult
    {
        $conflict = Listing::active()
            ->where('msisdn', $listing->msisdn)
            ->where('id', '!=', $listing->id)
            ->first();

        if ($conflict === null) {
            return RuleResult::pass();
        }

        return RuleResult::reject(
            'moderation.reasons.duplicate',
            payload: ['conflicting_listing_id' => $conflict->id]
        );
    }
}
