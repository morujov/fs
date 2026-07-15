<?php

namespace App\Services\Search;

/**
 * Определяет «красивость» номера: repetido, capicúa, escalera и т.д.
 *
 * Это не украшение, а навигация и SEO. Именно эти категории становятся
 * посадочными страницами («números repetidos», «números capicúa»),
 * а они — единственный канал трафика, пока нет монетизации и рекламы.
 * См. блюпринт, пробел №14.
 *
 * Теги считаются один раз при сохранении и лежат в listings.pattern_tags
 * (JSON), а не вычисляются на лету: фильтр по ним должен быть дешёвым.
 */
final class PatternTagger
{
    /** Все существующие теги — для фильтров, сидеров и валидации. */
    public const TAGS = [
        'repetido',    // 666666666 — все цифры одинаковы
        'triplete',    // 6xx777xxx — три одинаковые подряд
        'pareja',      // много пар: 611 22 33 44
        'capicua',     // палиндром: 612343216 наоборот тот же
        'escalera',    // 123456789 — цифры идут подряд
        'escalera_desc', // 987654321
        'terminacion',   // повтор в конце: xxxxx7777
        'facil',         // легко запомнить (эвристика)
    ];

    /**
     * @return list<string>
     *
     * Проверка структурная, а не «продаётся ли номер»: красота числа не
     * зависит от того, выделил ли CNMC этот диапазон. Если завтра откроют
     * 75X — палиндромы в нём останутся палиндромами, и трогать этот класс
     * не придётся.
     */
    public static function tag(string $msisdn): array
    {
        if (! NumberPatternQuery::isWellFormed($msisdn)) {
            return [];
        }

        $tags = [];
        $d = str_split($msisdn);

        if (self::isRepetido($msisdn)) {
            $tags[] = 'repetido';
        }

        if (self::hasTriplete($msisdn)) {
            $tags[] = 'triplete';
        }

        if (self::pairCount($d) >= 3) {
            $tags[] = 'pareja';
        }

        if (self::isCapicua($msisdn)) {
            $tags[] = 'capicua';
        }

        if (self::isEscalera($d, 1)) {
            $tags[] = 'escalera';
        }

        if (self::isEscalera($d, -1)) {
            $tags[] = 'escalera_desc';
        }

        if (self::hasTerminacion($msisdn)) {
            $tags[] = 'terminacion';
        }

        // «Лёгкий» — производный тег: если сработало что-то из сильных
        // паттернов, номер по определению запоминается легко.
        if (array_intersect($tags, ['repetido', 'capicua', 'escalera', 'escalera_desc', 'terminacion'])) {
            $tags[] = 'facil';
        }

        return array_values(array_unique($tags));
    }

    /** Все девять цифр одинаковы. */
    private static function isRepetido(string $n): bool
    {
        return count(array_unique(str_split($n))) === 1;
    }

    /** Три одинаковые цифры подряд где угодно. */
    private static function hasTriplete(string $n): bool
    {
        return (bool) preg_match('/(\d)\1{2}/', $n);
    }

    /** Сколько пар одинаковых цифр подряд. */
    private static function pairCount(array $d): int
    {
        $c = 0;

        for ($i = 0; $i < count($d) - 1; $i++) {
            if ($d[$i] === $d[$i + 1]) {
                $c++;
            }
        }

        return $c;
    }

    /**
     * Палиндром. Номер читается одинаково в обе стороны.
     * Девять цифр — нечётная длина, средняя цифра свободна.
     */
    private static function isCapicua(string $n): bool
    {
        return $n === strrev($n);
    }

    /**
     * Лестница: каждая следующая цифра на $step больше предыдущей.
     * Достаточно шести подряд — 612345678 это «лестница с 1»,
     * и она ценится не меньше идеальной.
     */
    private static function isEscalera(array $d, int $step): bool
    {
        $run = 1;
        $max = 1;

        for ($i = 1; $i < count($d); $i++) {
            if ((int) $d[$i] - (int) $d[$i - 1] === $step) {
                $run++;
                $max = max($max, $run);
            } else {
                $run = 1;
            }
        }

        return $max >= 6;
    }

    /** Хвост из четырёх и более одинаковых цифр: 61234 7777. */
    private static function hasTerminacion(string $n): bool
    {
        return (bool) preg_match('/(\d)\1{3}$/', $n);
    }
}
