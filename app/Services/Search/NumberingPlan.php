<?php

namespace App\Services\Search;

use App\Models\NumberingRange;
use Illuminate\Support\Facades\Cache;

/**
 * Политика: какой номер считается продаваемым мобильным.
 *
 * ── Почему отдельно от NumberPatternQuery ───────────────────────────────
 * NumberPatternQuery отвечает за СИНТАКСИС: санитизация ввода, построение
 * LIKE, нормализация. Он чистый, без БД, и юнит-тестируется мгновенно —
 * это важно, потому что там живёт защита от выгрузки базы через '%'.
 *
 * Этот класс отвечает за ПОЛИТИКУ: что говорит Plan Nacional de Numeración.
 * Политика меняется без нас (CNMC двигает диапазоны), поэтому она в БД.
 * Смешивать их — значит либо тащить БД в тесты санитизации, либо requerir
 * деплой ради нового префикса. Ни то, ни другое не нужно.
 *
 * ── Матчинг ─────────────────────────────────────────────────────────────
 * По самому длинному префиксу, как таблица маршрутизации. '70' перебивает
 * '7'. Новый диапазон = одна строка в БД, релиз не нужен.
 *
 * ── Fail-closed ─────────────────────────────────────────────────────────
 * Если таблица пуста (свежая установка до сидера) — НЕ пропускаем всё
 * подряд. Отсутствие правил не должно молча означать «всё разрешено»:
 * так одна забытая миграция открыла бы площадку под любой мусор.
 */
final class NumberingPlan
{
    private const CACHE_KEY = 'numbering_plan.ranges';

    /**
     * Резервный план на случай пустой таблицы. Сознательно минимальный:
     * только то, в чём нет сомнений. Полный план — в NumberingRangeSeeder.
     *
     * @var list<array{prefix:string,length:int,sellable:bool,reason:?string}>
     */
    private const FALLBACK = [
        ['prefix' => '6',  'length' => 9, 'sellable' => true,  'reason' => null],
        ['prefix' => '7',  'length' => 9, 'sellable' => true,  'reason' => null],
        ['prefix' => '70', 'length' => 9, 'sellable' => false, 'reason' => 'La numeración 70X es numeración personal, no un móvil.'],
    ];

    /**
     * Правило, применимое к номеру: самый длинный совпавший префикс.
     * Возвращает null, если номер не попадает ни в один известный диапазон.
     *
     * @return array{prefix:string,length:int,sellable:bool,reason:?string}|null
     */
    public function match(string $msisdn): ?array
    {
        $digits = preg_replace('/\D/', '', $msisdn) ?? '';

        if ($digits === '') {
            return null;
        }

        $best = null;

        foreach ($this->ranges() as $r) {
            if (! str_starts_with($digits, $r['prefix'])) {
                continue;
            }

            // Длиннее — значит конкретнее, значит побеждает.
            if ($best === null || strlen($r['prefix']) > strlen($best['prefix'])) {
                $best = $r;
            }
        }

        return $best;
    }

    /** Можно ли выставить этот номер на продажу. */
    public function isSellable(string $msisdn): bool
    {
        $digits = preg_replace('/\D/', '', $msisdn) ?? '';
        $rule = $this->match($digits);

        if ($rule === null) {
            return false; // неизвестный диапазон — не пропускаем
        }

        return $rule['sellable'] && strlen($digits) === $rule['length'];
    }

    /**
     * Причина отказа на испанском — для формы подачи.
     * null, если номер продаваем.
     */
    public function rejectionReason(string $msisdn): ?string
    {
        $digits = preg_replace('/\D/', '', $msisdn) ?? '';

        if ($this->isSellable($digits)) {
            return null;
        }

        $rule = $this->match($digits);

        if ($rule === null) {
            return 'Este número no pertenece a ningún rango móvil español conocido.';
        }

        if (! $rule['sellable']) {
            return $rule['reason'] ?? 'Este rango de numeración no se puede vender.';
        }

        return "Un número de este rango debe tener {$rule['length']} dígitos.";
    }

    /**
     * Префиксы, из которых можно генерировать продаваемые номера.
     * Учитывает исключения: '7' продаваем, но '70' из него вычтен, поэтому
     * отдаётся '71'…'79'. Используется фабрикой — так новый диапазон в БД
     * автоматически появляется и в демо-данных, без правки фабрики.
     *
     * @return list<array{prefix:string,length:int}>
     */
    public function sellablePrefixes(): array
    {
        $ranges = $this->ranges();
        $out = [];

        foreach ($ranges as $r) {
            if (! $r['sellable']) {
                continue;
            }

            // Ищем более длинные непродаваемые исключения внутри этого префикса.
            $blocked = array_filter(
                $ranges,
                fn ($x) => ! $x['sellable']
                    && strlen($x['prefix']) > strlen($r['prefix'])
                    && str_starts_with($x['prefix'], $r['prefix'])
            );

            if ($blocked === []) {
                $out[] = ['prefix' => $r['prefix'], 'length' => $r['length']];

                continue;
            }

            // Раскрываем префикс на одну цифру и выкидываем перекрытые ветки.
            foreach (range(0, 9) as $d) {
                $candidate = $r['prefix'].$d;

                $isBlocked = false;
                foreach ($blocked as $b) {
                    if (str_starts_with($candidate, $b['prefix']) || $b['prefix'] === $candidate) {
                        $isBlocked = true;
                        break;
                    }
                }

                if (! $isBlocked) {
                    $out[] = ['prefix' => $candidate, 'length' => $r['length']];
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array{prefix:string,length:int,sellable:bool,reason:?string}>
     */
    private function ranges(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            // Таблицы может не быть на этапе миграций — тогда fallback.
            try {
                $rows = NumberingRange::query()
                    ->where('is_active', true)
                    ->get(['prefix', 'length', 'is_sellable', 'reason_es']);
            } catch (\Throwable) {
                return self::FALLBACK;
            }

            if ($rows->isEmpty()) {
                return self::FALLBACK;
            }

            return $rows->map(fn ($r) => [
                'prefix'   => $r->prefix,
                'length'   => $r->length,
                'sellable' => $r->is_sellable,
                'reason'   => $r->reason_es,
            ])->all();
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
