<?php

namespace App\Services\Moderation;

use App\Models\Listing;

/**
 * Одно правило конвейера.
 *
 * Правила НЕ меняют объявление. Они только выносят суждение — решение
 * принимает ModerationPipeline. Правило, которое само что-то правит,
 * невозможно ни протестировать в изоляции, ни выключить.
 */
interface ModerationRule
{
    /**
     * Идентификатор для логов и аналитики: 'blocklist', 'duplicate',
     * 'price_range'... Попадает в moderation_logs.rule, по нему потом
     * считается, какое правило сколько раз ложно сработало.
     */
    public function name(): string;

    public function check(Listing $listing): RuleResult;
}
