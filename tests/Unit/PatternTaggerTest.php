<?php

namespace Tests\Unit;

use App\Services\Search\PatternTagger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Теги «красоты» номера — это навигация и SEO-посадочные, а при отсутствии
 * монетизации органика единственный канал трафика. Ошибка в теге тихо
 * выкидывает номер из категории, куда он должен попасть.
 */
class PatternTaggerTest extends TestCase
{
    /**
     * @return list<array{0:string,1:string}>
     */
    public static function repetidoProvider(): array
    {
        return [
            ['666666666', 'все девять шестёрок'],
            ['777777777', 'все девять семёрок'],
        ];
    }

    #[Test]
    #[DataProvider('repetidoProvider')]
    public function tags_repetido(string $msisdn, string $why): void
    {
        $this->assertContains('repetido', PatternTagger::tag($msisdn), $why);
    }

    #[Test]
    public function does_not_tag_repetido_when_a_single_digit_differs(): void
    {
        $this->assertNotContains('repetido', PatternTagger::tag('666666665'));
    }

    #[Test]
    public function tags_capicua(): void
    {
        // 612 3 4 3 216 наоборот — то же самое
        $this->assertContains('capicua', PatternTagger::tag('612343216'));
        $this->assertContains('capicua', PatternTagger::tag('612040216'));

        $this->assertNotContains('capicua', PatternTagger::tag('612345678'));
    }

    #[Test]
    public function repetido_is_also_a_palindrome(): void
    {
        // 666666666 читается одинаково в обе стороны — это факт, а не баг.
        // Тег должен ставиться честно, даже если выглядит избыточным.
        $tags = PatternTagger::tag('666666666');

        $this->assertContains('repetido', $tags);
        $this->assertContains('capicua', $tags);
    }

    #[Test]
    public function tags_escalera_ascending(): void
    {
        // Порог — прогон длиной 6, см. PatternTagger::isEscalera
        $this->assertContains('escalera', PatternTagger::tag('612345678'));
        $this->assertContains('escalera', PatternTagger::tag('698123456'), 'прогон в хвосте');

        $this->assertNotContains('escalera', PatternTagger::tag('612345987'), 'прогон длиной 5 — мало');
    }

    #[Test]
    public function tags_escalera_descending(): void
    {
        $this->assertContains('escalera_desc', PatternTagger::tag('698765432'));
        $this->assertNotContains('escalera', PatternTagger::tag('698765432'), 'убывающая — не возрастающая');
    }

    #[Test]
    public function tags_terminacion(): void
    {
        $this->assertContains('terminacion', PatternTagger::tag('612347777'));

        $this->assertNotContains('terminacion', PatternTagger::tag('612377770'), 'хвост из трёх и ноль в конце');
        $this->assertNotContains('terminacion', PatternTagger::tag('612345777'), 'только три одинаковых в конце');
    }

    #[Test]
    public function tags_triplete(): void
    {
        $this->assertContains('triplete', PatternTagger::tag('612777456'));
        $this->assertNotContains('triplete', PatternTagger::tag('612774456'), 'только пара');
    }

    #[Test]
    public function derives_facil_from_strong_patterns(): void
    {
        // «Лёгкий» — производный тег: если сработал сильный паттерн,
        // номер по определению запоминается.
        $this->assertContains('facil', PatternTagger::tag('666666666'));
        $this->assertContains('facil', PatternTagger::tag('612345678'));
        $this->assertContains('facil', PatternTagger::tag('612343216'));

        $this->assertNotContains('facil', PatternTagger::tag('639284751'), 'случайный номер не «лёгкий»');
    }

    #[Test]
    public function returns_no_tags_for_a_boring_number(): void
    {
        $this->assertSame([], PatternTagger::tag('639284751'));
    }

    #[Test]
    public function returns_no_tags_for_a_structurally_invalid_number(): void
    {
        $this->assertSame([], PatternTagger::tag('61234567'), 'восемь цифр');
        $this->assertSame([], PatternTagger::tag('6123456789'), 'десять цифр');
        $this->assertSame([], PatternTagger::tag('abcdefghi'), 'буквы');
        $this->assertSame([], PatternTagger::tag(''), 'пусто');
    }

    #[Test]
    public function does_not_depend_on_the_numbering_plan(): void
    {
        // Красота числа не зависит от того, выделил ли CNMC диапазон.
        // Если завтра откроют 75X — палиндромы в нём останутся палиндромами,
        // и трогать этот класс не придётся.
        $this->assertContains('capicua', PatternTagger::tag('912343219'), '9XX не продаётся, но палиндром');
        $this->assertContains('capicua', PatternTagger::tag('701343107'), '70X не мобильный, но палиндром');
    }

    #[Test]
    public function returns_no_duplicate_tags(): void
    {
        foreach (['666666666', '612345678', '612343216', '612347777'] as $msisdn) {
            $tags = PatternTagger::tag($msisdn);

            $this->assertSame(
                array_values(array_unique($tags)),
                $tags,
                "Дубли тегов у {$msisdn}"
            );
        }
    }

    #[Test]
    public function returns_only_known_tags(): void
    {
        foreach (['666666666', '612345678', '698765432', '612343216', '612347777', '612777456'] as $msisdn) {
            foreach (PatternTagger::tag($msisdn) as $tag) {
                $this->assertContains(
                    $tag,
                    PatternTagger::TAGS,
                    "Тег '{$tag}' отсутствует в PatternTagger::TAGS — фильтр на витрине его не покажет"
                );
            }
        }
    }
}
