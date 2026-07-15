<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * ~500 демо-объявлений.
 *
 * Смысл: получить объём, на котором можно честно мерить wildcard-поиск и
 * проверять фильтры. 500 строк — не нагрузочный тест (он в S11 на 100k),
 * а рабочий минимум, чтобы пагинация по 20 давала 25 страниц и было видно,
 * как ведут себя сортировки.
 *
 * Красивые номера сеются отдельно и с запасом: на случайных цифрах
 * паттерн-теги практически не выпадают, и фильтр по категориям было бы
 * не на чем проверить.
 */
class DemoListingSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = User::factory()->count(60)->create();

        $pick = fn () => ['user_id' => $sellers->random()->id];

        // Обычные активные.
        Listing::factory()->count(380)->state($pick)->create();

        // Красивые — то, ради чего люди приходят на площадку. Активных дублей
        // быть не может (active_msisdn UNIQUE), поэтому номера набираются
        // уникальным набором. «repetido» по строгому правилу всего два (6/7),
        // больше запросить нельзя — иначе вставка упадёт на unique-индексе.
        $this->seedPretty($sellers, 'repetido', 2);
        $this->seedPretty($sellers, 'capicua', 20);
        $this->seedPretty($sellers, 'escalera', 10);
        $this->seedPretty($sellers, 'terminacion', 25);

        // Не-активные: нужны, чтобы проверить, что витрина их НЕ показывает,
        // а генерируемая колонка active_msisdn не мешает дублям в истории.
        Listing::factory()->count(25)->pending()->state($pick)->create();
        Listing::factory()->count(15)->sold()->state($pick)->create();
        Listing::factory()->count(10)->expired()->state($pick)->create();

        $this->command->info('Демо-объявлений: '.Listing::count());
    }

    /**
     * Засеять $target активных «красивых» номеров вида $kind, гарантируя
     * уникальность msisdn. Генератор factory зовётся в цикле с дедупликацией:
     * у некоторых категорий пространство маленькое (repetido — всего два), и
     * набрать больше физически нельзя. Если уникальных не хватает — сеем сколько
     * есть и честно предупреждаем, а не молча теряем строки на unique-индексе.
     */
    private function seedPretty(Collection $sellers, string $kind, int $target): void
    {
        $factory = Listing::factory();
        $gen = 'gen'.ucfirst($kind);

        $numbers = [];
        $cap = $target * 60 + 100;                 // страховка от бесконечного цикла
        for ($i = 0; count($numbers) < $target && $i < $cap; $i++) {
            $numbers[$factory->{$gen}()] = true;   // ключ = msisdn → авто-дедуп
        }

        // withMsisdn пересчитывает теги, slug и ставит «красивую» цену —
        // повторно звать состояние ($kind) не нужно.
        foreach (array_keys($numbers) as $msisdn) {
            Listing::factory()
                ->withMsisdn(fn () => $msisdn)
                ->state(['user_id' => $sellers->random()->id])
                ->create();
        }

        if (count($numbers) < $target) {
            $this->command->warn(
                "  {$kind}: доступно только ".count($numbers)." уникальных из {$target} — засеяно столько."
            );
        }
    }
}
