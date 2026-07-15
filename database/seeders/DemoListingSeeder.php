<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\User;
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

        // Красивые — то, ради чего люди приходят на площадку.
        Listing::factory()->count(15)->repetido()->state($pick)->create();
        Listing::factory()->count(20)->capicua()->state($pick)->create();
        Listing::factory()->count(10)->escalera()->state($pick)->create();
        Listing::factory()->count(25)->terminacion()->state($pick)->create();

        // Не-активные: нужны, чтобы проверить, что витрина их НЕ показывает,
        // а генерируемая колонка active_msisdn не мешает дублям в истории.
        Listing::factory()->count(25)->pending()->state($pick)->create();
        Listing::factory()->count(15)->sold()->state($pick)->create();
        Listing::factory()->count(10)->expired()->state($pick)->create();

        $this->command->info('Демо-объявлений: '.Listing::count());
    }
}
