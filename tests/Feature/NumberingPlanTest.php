<?php

namespace Tests\Feature;

use App\Models\NumberingRange;
use App\Services\Search\NumberingPlan;
use Database\Seeders\NumberingRangeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * План нумерации живёт в БД, а не в регулярке, потому что CNMC двигает
 * диапазоны без нас, а деплой на shared-хостинге — ручной git pull
 * в рвущемся терминале.
 *
 * Главное свойство, которое тут проверяется: НОВЫЙ ДИАПАЗОН ДОБАВЛЯЕТСЯ
 * СТРОКОЙ В БД И РАБОТАЕТ БЕЗ ПРАВКИ КОДА. Если это перестанет быть так —
 * вся затея бессмысленна, и тесты ниже должны это поймать.
 */
class NumberingPlanTest extends TestCase
{
    use RefreshDatabase;

    private NumberingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->plan = new NumberingPlan;
    }

    private function fresh(): NumberingPlan
    {
        NumberingPlan::flush();

        return new NumberingPlan;
    }

    // -----------------------------------------------------------------
    // Матчинг по самому длинному префиксу
    // -----------------------------------------------------------------

    /**
     * @return list<array{0:string,1:bool,2:string}>
     */
    public static function sellabilityProvider(): array
    {
        return [
            ['612345678', true,  '6XX — исторический мобильный диапазон'],
            ['666666666', true,  'самый ценный repetido в стране'],
            ['712345678', true,  '71X — мобильный с 2010 года'],
            ['742345678', true,  '74X — мобильный'],
            ['752345678', true,  '75X — резерв под мобильные, принимаем сознательно'],
            ['792345678', true,  '79X — резерв под мобильные'],

            ['701234567', false, '70X — numeración personal, НЕ мобильный'],
            ['700000000', false, '70X целиком'],
            ['709999999', false, '70X целиком'],

            ['912345678', false, '9XX — фиксированная связь'],
            ['812345678', false, '8XX — фиксированная связь'],
            ['900123456', false, '900 — тарификация'],
            ['902123456', false, '902 — тарификация'],

            ['512345678', false, '5XX — неизвестный диапазон, fail-closed'],
            ['012345678', false, '0XX — неизвестный диапазон'],
        ];
    }

    #[Test]
    #[DataProvider('sellabilityProvider')]
    public function decides_sellability_by_longest_prefix(string $msisdn, bool $sellable, string $why): void
    {
        $this->assertSame(
            $sellable,
            $this->plan->isSellable($msisdn),
            "{$msisdn}: {$why}"
        );
    }

    #[Test]
    public function longer_prefix_beats_shorter_one(): void
    {
        // '70' и '7' оба матчат 701234567. Побеждать обязан '70' как более
        // конкретный — ровно как в таблице маршрутизации. Если это сломается,
        // 70X начнёт продаваться.
        $rule = $this->plan->match('701234567');

        $this->assertSame('70', $rule['prefix'], 'выиграл более короткий префикс');
        $this->assertFalse($rule['sellable']);

        $this->assertSame('7', $this->plan->match('711234567')['prefix']);
    }

    #[Test]
    public function rejects_number_of_wrong_length_within_a_valid_range(): void
    {
        $this->assertFalse($this->plan->isSellable('61234567'), 'восемь цифр');
        $this->assertFalse($this->plan->isSellable('6123456789'), 'десять цифр');
    }

    // -----------------------------------------------------------------
    // Fail-closed: пустой план не означает «всё разрешено»
    // -----------------------------------------------------------------

    #[Test]
    public function unknown_range_is_rejected_not_allowed(): void
    {
        // Отсутствие правила — это отказ. Иначе один забытый диапазон
        // молча открыл бы площадку под что угодно.
        $this->assertNull($this->plan->match('512345678'));
        $this->assertFalse($this->plan->isSellable('512345678'));
    }

    #[Test]
    public function empty_table_falls_back_instead_of_allowing_everything(): void
    {
        NumberingRange::query()->delete();
        $plan = $this->fresh();

        // Резервный план минимален, но 70X он обязан держать.
        $this->assertTrue($plan->isSellable('612345678'), 'fallback пропускает 6XX');
        $this->assertFalse($plan->isSellable('701234567'), 'fallback держит 70X');
        $this->assertFalse($plan->isSellable('912345678'), 'fallback не пропускает 9XX');
    }

    // -----------------------------------------------------------------
    // ГЛАВНОЕ: новый диапазон работает без правки кода
    // -----------------------------------------------------------------

    #[Test]
    public function a_new_range_added_as_a_row_works_without_touching_code(): void
    {
        // Сценарий: CNMC открывает мобильные на '5'. Админ добавляет строку.
        $this->assertFalse($this->plan->isSellable('512345678'), 'до добавления — отказ');

        NumberingRange::create([
            'prefix' => '5', 'length' => 9, 'kind' => 'mobile',
            'is_sellable' => true, 'source' => 'Гипотетическая резолюция CNMC',
        ]);

        $this->assertTrue(
            $this->fresh()->isSellable('512345678'),
            'Новый диапазон не заработал строкой в БД — вся затея с таблицей теряет смысл'
        );
    }

    #[Test]
    public function a_range_can_be_closed_as_a_row_without_touching_code(): void
    {
        // Сценарий: выясняется, что 75X отдали не мобильным.
        $this->assertTrue($this->plan->isSellable('752345678'));

        NumberingRange::create([
            'prefix' => '75', 'length' => 9, 'kind' => 'reserved',
            'is_sellable' => false,
            'reason_es' => 'Rango no asignado a móviles.',
        ]);

        $this->assertFalse($this->fresh()->isSellable('752345678'), 'исключение не сработало');
        $this->assertTrue($this->fresh()->isSellable('762345678'), 'соседний диапазон задет зря');
    }

    #[Test]
    public function a_different_number_length_is_supported(): void
    {
        // Закладка на случай, если нумерация перестанет быть девятизначной.
        NumberingRange::create([
            'prefix' => '5', 'length' => 10, 'kind' => 'mobile', 'is_sellable' => true,
        ]);

        $plan = $this->fresh();

        $this->assertTrue($plan->isSellable('5123456789'), 'десять цифр в 10-значном диапазоне');
        $this->assertFalse($plan->isSellable('512345678'), 'девять цифр туда не годятся');
    }

    #[Test]
    public function deactivating_a_row_takes_effect(): void
    {
        NumberingRange::where('prefix', '70')->update(['is_active' => false]);

        // '70' выключён → остаётся общее правило '7' → продаётся.
        $this->assertTrue(
            $this->fresh()->isSellable('701234567'),
            'выключенная строка продолжает действовать'
        );
    }

    // -----------------------------------------------------------------
    // Причины отказа — их видит продавец в форме
    // -----------------------------------------------------------------

    #[Test]
    public function gives_a_spanish_reason_for_rejection(): void
    {
        $this->assertNull($this->plan->rejectionReason('612345678'), 'у годного номера причины нет');

        $reason = $this->plan->rejectionReason('701234567');
        $this->assertNotNull($reason);
        $this->assertStringContainsString('personal', $reason);

        $this->assertNotNull($this->plan->rejectionReason('912345678'));
        $this->assertNotNull($this->plan->rejectionReason('512345678'), 'неизвестный диапазон тоже объясняется');
    }

    // -----------------------------------------------------------------
    // sellablePrefixes — на них опирается фабрика
    // -----------------------------------------------------------------

    #[Test]
    public function sellable_prefixes_expand_around_exclusions(): void
    {
        $prefixes = array_column($this->plan->sellablePrefixes(), 'prefix');

        $this->assertContains('6', $prefixes, '6 без исключений — отдаётся целиком');

        // '7' содержит исключение '70', поэтому обязан раскрыться в 71..79.
        $this->assertNotContains('7', $prefixes, '7 отдан целиком — фабрика начнёт плодить 70X');
        $this->assertNotContains('70', $prefixes);

        foreach (range(1, 9) as $d) {
            $this->assertContains("7{$d}", $prefixes, "потерян продаваемый префикс 7{$d}");
        }
    }

    #[Test]
    public function every_sellable_prefix_yields_a_sellable_number(): void
    {
        // Свойство: что бы ни отдал sellablePrefixes, добивка цифрами
        // обязана давать продаваемый номер. Именно на это опирается фабрика.
        foreach ($this->plan->sellablePrefixes() as $p) {
            $msisdn = $p['prefix'].str_repeat('1', $p['length'] - strlen($p['prefix']));

            $this->assertTrue(
                $this->plan->isSellable($msisdn),
                "Префикс {$p['prefix']} отдан как продаваемый, но {$msisdn} не продаётся"
            );
        }
    }

    #[Test]
    public function sellable_prefixes_never_produce_personal_numbering(): void
    {
        // Свойство, ради которого всё затевалось: из отданных фабрике
        // префиксов физически нельзя собрать номер из диапазона 70.
        foreach ($this->plan->sellablePrefixes() as $p) {
            foreach (range(0, 9) as $d) {
                $sample = str_pad($p['prefix'], $p['length'], (string) $d);

                $this->assertFalse(
                    str_starts_with($sample, '70'),
                    "Префикс {$p['prefix']} породил {$sample} — это numeración personal"
                );
            }
        }
    }
}
