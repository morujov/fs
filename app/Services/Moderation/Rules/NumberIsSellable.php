<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;
use App\Services\Search\NumberPatternQuery;
use App\Services\Search\NumberingPlan;

/**
 * Номер вообще может продаваться.
 *
 * Спрашивает NumberingPlan, а не регулярку: план нумерации живёт в БД,
 * потому что CNMC двигает диапазоны без нас (инвариант №9).
 *
 * Дублирует проверку из StoreListingRequest намеренно — и это тот редкий
 * случай, когда дублирование оправдано. Валидация формы срабатывает при
 * подаче. Но план нумерации может измениться потом: админ закроет диапазон,
 * и объявления в нём обязаны перестать публиковаться при следующем прогоне.
 * Конвейер — это ворота на витрину, а не эхо формы.
 */
final class NumberIsSellable implements ModerationRule
{
    public function __construct(private readonly NumberingPlan $plan) {}

    public function name(): string
    {
        return 'number_sellable';
    }

    public function check(Listing $listing): RuleResult
    {
        if (! NumberPatternQuery::isWellFormed($listing->msisdn)) {
            return RuleResult::reject('moderation.reasons.msisdn_malformed', payload: [
                'msisdn' => $listing->msisdn,
            ]);
        }

        if ($this->plan->isSellable($listing->msisdn)) {
            return RuleResult::pass();
        }

        $rule = $this->plan->match($listing->msisdn);

        // Причина берётся из плана и приходит на испанском: «это numeración
        // personal», а не безликое «número no válido». Продавец должен
        // понять, что именно не так, а не гадать.
        return RuleResult::reject(
            'moderation.reasons.msisdn_not_sellable',
            ['reason' => $this->plan->rejectionReason($listing->msisdn) ?? ''],
            [
                'msisdn'         => $listing->msisdn,
                'matched_prefix' => $rule['prefix'] ?? null,
            ]
        );
    }
}
