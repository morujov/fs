<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Models\Setting;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Цена в разумных пределах либо объявление договорное.
 *
 * Без этого продавцы вобьют 1 или 999999, и сортировка по цене — один из
 * двух осмысленных способов листать витрину — перестанет работать.
 *
 * Пороги в settings: их правят по факту рынка, а не релизом.
 */
final class PriceInRange implements ModerationRule
{
    public function name(): string
    {
        return 'price_range';
    }

    public function check(Listing $listing): RuleResult
    {
        if ($listing->is_negotiable) {
            // «A consultar» — легитимный выбор. Цены нет и не должно быть.
            return RuleResult::pass(['negotiable' => true]);
        }

        if ($listing->price === null) {
            return RuleResult::reject('moderation.reasons.price_missing');
        }

        $min = (int) Setting::get('listing.price_min', 1);
        $max = (int) Setting::get('listing.price_max', 50000);
        $price = (float) $listing->price;

        if ($price < $min || $price > $max) {
            return RuleResult::reject(
                'moderation.reasons.price_out_of_range',
                ['min' => $min, 'max' => $max],
                ['price' => $price]
            );
        }

        return RuleResult::pass();
    }
}
