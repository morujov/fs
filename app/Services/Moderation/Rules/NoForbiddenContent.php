<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * В описании нет HTML и явного спама.
 *
 * Описание выводится через Blade с экранированием, поэтому HTML не опасен
 * технически. Но его наличие — сигнал: живой продавец не пишет теги.
 *
 * Стоп-слова держим в коде намеренно узким списком. Развесистая фильтрация
 * мата и «плохих слов» на доске объявлений даёт ложные срабатывания чаще,
 * чем ловит спам, а модератора у нас пока нет вовсе.
 */
final class NoForbiddenContent implements ModerationRule
{
    /** Однозначные маркеры чужой рекламы, а не «плохие слова». */
    private const SPAM_MARKERS = [
        'bitcoin', 'criptomoneda', 'inversión garantizada', 'dinero fácil',
        'préstamo rápido', 'casino', 'apuestas', 'viagra',
        'seguidores', 'followers', 'hackear', 'hack',
    ];

    public function name(): string
    {
        return 'forbidden_content';
    }

    public function check(Listing $listing): RuleResult
    {
        $text = (string) $listing->description;

        if (trim($text) === '') {
            return RuleResult::pass();
        }

        $found = [];

        if ($text !== strip_tags($text)) {
            $found['html'] = true;
        }

        $lower = mb_strtolower($text);
        $markers = array_values(array_filter(
            self::SPAM_MARKERS,
            fn ($w) => str_contains($lower, $w)
        ));

        if ($markers !== []) {
            $found['markers'] = $markers;
        }

        // ЗАГЛАВНЫМИ КРИЧАТ спамеры. Считаем только если текст достаточно
        // длинный: «OFERTA» из шести букв — это не крик.
        $letters = preg_replace('/[^\p{L}]/u', '', $text) ?? '';
        if (mb_strlen($letters) >= 20) {
            $upper = preg_replace('/[^\p{Lu}]/u', '', $text) ?? '';
            if (mb_strlen($upper) / mb_strlen($letters) > 0.7) {
                $found['shouting'] = true;
            }
        }

        if ($found === []) {
            return RuleResult::pass();
        }

        return RuleResult::flag('moderation.reasons.forbidden_content', score: 2, payload: $found);
    }
}
