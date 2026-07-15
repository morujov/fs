<?php

namespace Database\Seeders;

use App\Models\Operator;
use Illuminate\Database\Seeder;

/**
 * Операторы мобильной связи Испании.
 *
 * Четыре сети с собственной инфраструктурой (Movistar, Vodafone, Orange,
 * Yoigo/MásMóvil) плюс основные виртуальные. У MVNO указана сеть-хозяин:
 * от неё зависит процедура портации, покупателю это важно.
 *
 * Список меняется — Digi строит свою сеть, MásMóvil и Orange слились.
 * Поэтому справочник в БД, а не в enum: правится из админки без деплоя.
 */
class OperatorSeeder extends Seeder
{
    public function run(): void
    {
        // [name, slug, is_mvno, host_network, sort_order]
        $rows = [
            ['Movistar',   'movistar',   false, null,       10],
            ['Vodafone',   'vodafone',   false, null,       20],
            ['Orange',     'orange',     false, null,       30],
            ['Yoigo',      'yoigo',      false, null,       40],
            ['MásMóvil',   'masmovil',   false, null,       50],
            ['Digi',       'digi',       true,  'movistar', 60],
            ['Pepephone',  'pepephone',  true,  'vodafone', 70],
            ['Lowi',       'lowi',       true,  'vodafone', 80],
            ['O2',         'o2',         true,  'movistar', 90],
            ['Simyo',      'simyo',      true,  'orange',   100],
            ['Jazztel',    'jazztel',    true,  'orange',   110],
            ['Amena',      'amena',      true,  'orange',   120],
            ['Finetwork',  'finetwork',  true,  'vodafone', 130],
            ['Otro',       'otro',       false, null,       999],
        ];

        foreach ($rows as [$name, $slug, $isMvno, $host, $sort]) {
            Operator::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'         => $name,
                    'is_mvno'      => $isMvno,
                    'host_network' => $host,
                    'sort_order'   => $sort,
                    'is_active'    => true,
                ]
            );
        }
    }
}
