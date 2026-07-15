<?php

namespace App\Rules;

use App\Services\Search\NumberPatternQuery;
use App\Services\Search\NumberingPlan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Продаваемый испанский мобильный.
 *
 * Спрашивает NumberingPlan (таблица numbering_ranges), а не регулярку:
 * CNMC двигает диапазоны без нас, и правило в коде означало бы деплой
 * ради префикса. См. инвариант №9 в CLAUDE.md.
 *
 * Причина отказа берётся из плана и приходит на испанском — её видит
 * продавец в форме. «Número no válido» без объяснения заставляет гадать,
 * а у нас есть точный ответ: это numeración personal, это фиксированная
 * связь, это неизвестный диапазон.
 */
class SellableMsisdn implements ValidationRule
{
    public function __construct(
        private readonly NumberingPlan $plan = new NumberingPlan,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $msisdn = NumberPatternQuery::normalize((string) $value);
        } catch (InvalidArgumentException) {
            $fail('listing.validation.msisdn_malformed')->translate();

            return;
        }

        if (! $this->plan->isSellable($msisdn)) {
            $fail($this->plan->rejectionReason($msisdn) ?? __('listing.validation.msisdn_not_sellable'));
        }
    }
}
