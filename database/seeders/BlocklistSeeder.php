<?php

namespace Database\Seeders;

use App\Models\BlocklistNumber;
use Illuminate\Database\Seeder;

/**
 * Служебные и непродаваемые диапазоны испанского нумерационного плана.
 *
 * Правило конвейера №2 (format) уже отсекает всё, что не начинается на 6 или 7,
 * так что 112/900/902 сюда попадать не должны в принципе. Блок-лист — второй
 * рубеж и место, куда админ добавляет паттерны руками.
 *
 * Диапазон 70 — persistente: это персональная нумерация (numeración personal),
 * формально начинается на 7, но мобильным номером не является и продаваться
 * не может. Именно ради него блок-лист нужен уже на старте.
 */
class BlocklistSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['70???????', 'Numeración personal — no es un móvil'],
            ['600000000', 'Número de prueba / placeholder'],
            ['612345678', 'Número de ejemplo, usado en documentación'],
            ['666666666', 'Reservado: revisar manualmente antes de publicar'],
        ];

        foreach ($rows as [$pattern, $reason]) {
            BlocklistNumber::updateOrCreate(
                ['msisdn_pattern' => $pattern],
                ['reason' => $reason, 'is_active' => true, 'created_by' => null]
            );
        }
    }
}
