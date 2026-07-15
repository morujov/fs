<?php

namespace Tests\Unit;

use App\Services\Search\NumberPatternQuery;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Самый важный тест проекта.
 *
 * NumberPatternQuery строит SQL LIKE из пользовательского ввода. Если сюда
 * просочится '%', один запрос выгрузит всю базу контактов продавцов — то
 * самое, что мы защищаем Google-гейтом, лимитами и маскировкой. Все те меры
 * бессмысленны, если поиск отдаёт базу целиком.
 *
 * Класс намеренно чистый (без БД), поэтому тест — обычный PHPUnit\TestCase
 * без Laravel: он должен быть мгновенным, чтобы его гоняли на каждый чих.
 */
class NumberPatternQueryTest extends TestCase
{
    // -----------------------------------------------------------------
    // Санитизация: whitelist, а не blacklist
    // -----------------------------------------------------------------

    /**
     * @return list<array{0:string,1:string,2:string}>
     */
    public static function injectionProvider(): array
    {
        return [
            'голый процент'         => ['%', '', 'выгрузил бы всю базу'],
            'процент внутри'        => ['6%2', '62', 'LIKE 6%2 матчит всё на 6'],
            'подчёркивание'         => ['6_2', '62', '_ — тоже wildcard в LIKE'],
            'экранированный процент'=> ['6\\%2', '62', 'обратный слэш не спасает'],
            'sql-инъекция'          => ["6'; DROP TABLE listings;--", '6', 'кавычка и точка с запятой'],
            'комментарий sql'       => ['6--', '6', 'комментарий'],
            'звёздочка'             => ['6*', '6', 'glob-синтаксис'],
            'юникод-омоглиф'        => ['６12', '12', 'полноширинная 6 — не ASCII-цифра'],
            'пробелы и дефисы'      => ['612 34-56', '6123456', 'форматирование убирается'],
            'плюс и скобки'         => ['+34(612)', '34612', 'символы убираются'],
            'нулевой байт'          => ["6\0%", '6', 'нулевой байт и процент'],
            'перевод строки'        => ["6\n%\n2", '62', 'многострочный ввод'],
        ];
    }

    #[Test]
    #[DataProvider('injectionProvider')]
    public function sanitize_keeps_only_digits_and_question_marks(string $input, string $expected, string $why): void
    {
        $this->assertSame(
            $expected,
            NumberPatternQuery::sanitize($input),
            "Санитизация пропустила опасный ввод: {$why}"
        );
    }

    #[Test]
    public function sanitize_lets_through_no_like_wildcard_at_all(): void
    {
        // Перебираем весь ASCII: ни один символ, кроме цифр и '?',
        // не имеет права пережить санитизацию. Blacklist рано или поздно
        // забывает про очередной спецсимвол — этот тест ловит такое.
        for ($i = 0; $i < 128; $i++) {
            $char = chr($i);
            $result = NumberPatternQuery::sanitize($char);

            $allowed = ctype_digit($char) || $char === '?';

            $this->assertSame(
                $allowed ? $char : '',
                $result,
                'Символ '.$i.' ('.var_export($char, true).') прошёл санитизацию'
            );
        }
    }

    #[Test]
    public function sanitize_truncates_to_number_length(): void
    {
        $this->assertSame('123456789', NumberPatternQuery::sanitize('1234567890123'));
    }

    #[Test]
    public function sanitize_handles_null_and_empty_string(): void
    {
        $this->assertSame('', NumberPatternQuery::sanitize(null));
        $this->assertSame('', NumberPatternQuery::sanitize(''));
    }

    // -----------------------------------------------------------------
    // toLike
    // -----------------------------------------------------------------

    #[Test]
    public function to_like_turns_question_mark_into_underscore(): void
    {
        $this->assertSame('6__12__34', NumberPatternQuery::toLike('6??12??34'));
    }

    #[Test]
    public function to_like_pads_short_input_on_the_right(): void
    {
        // '612' значит «начинается на 612», а не «ровно 612»:
        // пользователь, набравший три цифры, ждёт именно этого.
        $this->assertSame('612______', NumberPatternQuery::toLike('612'));
    }

    #[Test]
    public function to_like_returns_null_on_empty_input_not_a_match_all(): void
    {
        // null означает «не фильтровать», и вызывающий сам решит,
        // показывать ли всё. Вернуть '_________' было бы тем же самым,
        // но неявно — а неявность здесь стоит базы контактов.
        $this->assertNull(NumberPatternQuery::toLike(''));
        $this->assertNull(NumberPatternQuery::toLike(null));
    }

    #[Test]
    public function to_like_returns_null_for_bare_percent_not_a_full_dump(): void
    {
        // Ключевой тест. Если этот assert упадёт — один символ '%'
        // в поле поиска отдаёт все контакты продавцов разом.
        $this->assertNull(NumberPatternQuery::toLike('%'));
        $this->assertNull(NumberPatternQuery::toLike('%%%'));
        $this->assertNull(NumberPatternQuery::toLike('%_%'));
    }

    #[Test]
    public function to_like_never_contains_a_percent(): void
    {
        foreach (['%', '6%', '%6', '6%%2', '%?%', '612%'] as $input) {
            $like = NumberPatternQuery::toLike($input);

            if ($like !== null) {
                $this->assertStringNotContainsString('%', $like, "Ввод {$input} дал LIKE с '%'");
            }
        }
    }

    #[Test]
    public function to_like_is_always_exactly_nine_characters(): void
    {
        foreach (['6', '61', '612345678', '6??', '?'] as $input) {
            $this->assertSame(
                9,
                strlen(NumberPatternQuery::toLike($input)),
                "Ввод {$input} дал LIKE неправильной длины"
            );
        }
    }

    // -----------------------------------------------------------------
    // Семантика LIKE: проверяем через эквивалентный regex
    // -----------------------------------------------------------------

    /**
     * @return list<array{0:string,1:string,2:bool}>
     */
    public static function matchProvider(): array
    {
        return [
            ['612345678', '612345678', true],
            ['612345678', '6?2?4?6?8', true],
            ['612345678', '612',       true],
            ['712345678', '612',       false],
            ['612345678', '6??12??34', false],
            ['666666666', '6????????', true],
            ['666666666', '666666666', true],
            ['612345678', '???345???', true],
            ['612945678', '???345???', false],
        ];
    }

    #[Test]
    #[DataProvider('matchProvider')]
    public function like_matches_as_expected(string $msisdn, string $pattern, bool $shouldMatch): void
    {
        $like = NumberPatternQuery::toLike($pattern);

        // В LIKE '_' — ровно один символ; эквивалент в regex — '.'
        $regex = '/^'.str_replace('_', '.', preg_quote($like, '/')).'$/';

        $this->assertSame(
            $shouldMatch,
            (bool) preg_match($regex, $msisdn),
            "LIKE '{$like}' против {$msisdn}"
        );
    }

    // -----------------------------------------------------------------
    // toRegex — используется алертами по сохранённым поискам
    // -----------------------------------------------------------------

    #[Test]
    public function to_regex_matches_the_same_as_to_like(): void
    {
        $regex = NumberPatternQuery::toRegex('6??12??34');

        $this->assertSame(1, preg_match($regex, '612123434') ?: 0, 'должен матчить');
        $this->assertSame(0, preg_match($regex, '712123434'), 'другая первая цифра');
        $this->assertSame(0, preg_match($regex, '61212343'), 'восемь цифр');
    }

    #[Test]
    public function to_regex_allows_no_regex_injection(): void
    {
        // Ввод '.*' не должен превратиться в regex-квантификатор.
        $regex = NumberPatternQuery::toRegex('.*');
        $this->assertNull($regex, 'из мусора regex строить нечего');
    }

    #[Test]
    public function to_regex_returns_null_on_empty_input(): void
    {
        $this->assertNull(NumberPatternQuery::toRegex(''));
        $this->assertNull(NumberPatternQuery::toRegex('%'));
    }

    // -----------------------------------------------------------------
    // isWellFormed — структура, а не политика
    // -----------------------------------------------------------------

    #[Test]
    public function is_well_formed_checks_structure_only(): void
    {
        $this->assertTrue(NumberPatternQuery::isWellFormed('612345678'));
        $this->assertTrue(NumberPatternQuery::isWellFormed('912345678'), 'структурно годен, продаваемость — не его дело');
        $this->assertTrue(NumberPatternQuery::isWellFormed('701234567'), '70X структурно годен; отсекает его NumberingPlan');

        $this->assertFalse(NumberPatternQuery::isWellFormed('61234567'), 'восемь цифр');
        $this->assertFalse(NumberPatternQuery::isWellFormed('6123456789'), 'десять цифр');
        $this->assertFalse(NumberPatternQuery::isWellFormed('61234567a'), 'буква');
        $this->assertFalse(NumberPatternQuery::isWellFormed(''), 'пусто');
    }

    // -----------------------------------------------------------------
    // normalize
    // -----------------------------------------------------------------

    /**
     * @return list<array{0:string,1:string}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'E.164 с пробелами' => ['+34 612 34 56 78', '612345678'],
            'префикс 0034'      => ['0034612345678',    '612345678'],
            'дефисы'            => ['612-34-56-78',     '612345678'],
            'голые девять'      => ['612345678',        '612345678'],
            '34 + девять'       => ['34612345678',      '612345678'],
            'скобки'            => ['(612) 345 678',    '612345678'],
        ];
    }

    #[Test]
    #[DataProvider('normalizeProvider')]
    public function normalize_reduces_to_nine_digits(string $input, string $expected): void
    {
        $this->assertSame($expected, NumberPatternQuery::normalize($input));
    }

    #[Test]
    public function normalize_throws_on_structurally_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NumberPatternQuery::normalize('61234567');
    }

    #[Test]
    public function normalize_does_not_judge_sellability(): void
    {
        // 9XX — фиксированная связь, но это забота NumberingPlan.
        // Смешивать структуру и политику здесь нельзя: normalize обязан
        // работать без БД, иначе БД уедет в юнит-тесты санитизации.
        $this->assertSame('912345678', NumberPatternQuery::normalize('912345678'));
        $this->assertSame('701234567', NumberPatternQuery::normalize('701234567'));
    }
}
