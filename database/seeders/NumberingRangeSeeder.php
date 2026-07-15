<?php

namespace Database\Seeders;

use App\Models\NumberingRange;
use App\Services\Search\NumberingPlan;
use Illuminate\Database\Seeder;

/**
 * Plan Nacional de Numeración (CNMC) — стартовый набор.
 *
 * Матчинг по самому длинному префиксу: '70' перебивает '7'.
 * Дальше правится из админки, без деплоя.
 *
 * Источники:
 *   - Plan Nacional de Numeración Telefónica (RD 2296/2004 и последующие)
 *   - Resolución SETSI от 12.03.2010 — открытие сегмента 7 для мобильных
 *     (NX = 71, 72, 73, 74; резерв 75–79)
 *   - CNMC, Cuadros de Numeración
 */
class NumberingRangeSeeder extends Seeder
{
    public function run(): void
    {
        // [prefix, kind, sellable, reason_es, source]
        $rows = [
            [
                '6', 'mobile', true, null,
                'PNN: rango histórico de móviles',
            ],
            [
                '7', 'mobile', true, null,
                'Resolución SETSI 12/03/2010: apertura del segmento 7 para móviles (71-74; reserva 75-79)',
            ],

            // Исключение внутри '7'. Более длинный префикс побеждает.
            [
                '70', 'personal', false,
                'La numeración 70X es numeración personal, no un teléfono móvil. No se puede vender.',
                'PNN: NX=70 reservado a servicios de numeración personal',
            ],

            // Фиксированная связь. Не продаётся, но знать о них полезно:
            // так пользователь получит внятную причину вместо «неизвестный номер».
            [
                '8', 'fixed', false,
                'Los números que empiezan por 8 son de red fija, no móviles.',
                'PNN: numeración geográfica y de servicios',
            ],
            [
                '9', 'fixed', false,
                'Los números que empiezan por 9 son de red fija, no móviles.',
                'PNN: numeración geográfica',
            ],

            // Служебные диапазоны внутри 8/9 — отдельно, ради точной причины.
            [
                '900', 'service', false,
                'El 900 es numeración de tarificación especial, no un móvil.',
                'PNN: servicios de tarificación especial',
            ],
            [
                '902', 'service', false,
                'El 902 es numeración de tarificación especial, no un móvil.',
                'PNN: servicios de tarificación especial',
            ],
        ];

        foreach ($rows as [$prefix, $kind, $sellable, $reason, $source]) {
            NumberingRange::updateOrCreate(
                ['prefix' => $prefix],
                [
                    'length'      => 9,
                    'kind'        => $kind,
                    'is_sellable' => $sellable,
                    'reason_es'   => $reason,
                    'source'      => $source,
                    'is_active'   => true,
                ]
            );
        }

        // План кэшируется навсегда — после пересева кэш обязан протухнуть,
        // иначе сидер отработает, а приложение продолжит жить по старому плану.
        NumberingPlan::flush();
    }
}
