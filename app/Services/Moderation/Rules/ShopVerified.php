<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Объявление магазина публикуется только после верификации NIF/CIF.
 *
 * Иначе «магазином» назовётся кто угодно — а метка магазина на витрине
 * это ровно тот сигнал доверия, за которым покупатель к магазину и идёт.
 * Продать доверие за галочку в форме мы не можем.
 *
 * Частных продавцов правило не касается: им верифицировать нечего.
 *
 * Вес 3 = порог: непроверенный магазин в одиночку уходит на ручной разбор.
 * Это не наказание — это и есть очередь на верификацию.
 */
final class ShopVerified implements ModerationRule
{
    public function name(): string
    {
        return 'shop_verified';
    }

    public function check(Listing $listing): RuleResult
    {
        $user = $listing->user;

        if ($user === null || $user->seller_type !== 'shop') {
            return RuleResult::pass();
        }

        $shop = $user->shop;

        if ($shop === null) {
            return RuleResult::flag('moderation.reasons.shop_missing', score: 3);
        }

        if ($shop->isVerified()) {
            return RuleResult::pass(['shop_id' => $shop->id]);
        }

        return RuleResult::flag(
            'moderation.reasons.shop_unverified',
            score: 3,
            payload: ['shop_id' => $shop->id, 'shop_status' => $shop->status]
        );
    }
}
