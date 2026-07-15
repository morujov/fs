<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\Province;
use App\Models\User;
use App\Services\Search\NumberingPlan;
use App\Services\Search\NumberPatternQuery;
use App\Services\Search\PatternTagger;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Listing>
 *
 * Генерирует продаваемые номера, спрашивая план нумерации
 * (таблица numbering_ranges) — а не зашивая «6 или 7» в код.
 *
 * Важно: обычные номера намеренно делаются «скучными», а красивые
 * добавляются отдельными state'ами. Если сеять только случайные цифры,
 * паттерн-теги почти никогда не сработают, и проверить ни фильтр по
 * категориям, ни SEO-посадочные будет не на чем.
 */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        $msisdn = $this->randomMsisdn();

        return [
            'user_id'     => User::factory(),
            'shop_id'     => null,
            'msisdn'      => $msisdn,
            'price'       => $this->faker->randomElement([50, 80, 100, 150, 200, 300, 500, 750, 1200]),
            'is_negotiable' => $this->faker->boolean(20),
            'operator_id' => Operator::inRandomOrder()->value('id'),
            'line_type'   => $this->faker->randomElement(['prepago', 'prepago', 'contrato']),
            'has_permanency' => $this->faker->boolean(15),
            'permanency_until' => null,
            'condition'   => $this->faker->randomElement(['used', 'used', 'new']),
            'pattern_tags' => PatternTagger::tag($msisdn),
            'province_id' => Province::inRandomOrder()->value('id'),
            'city'        => $this->faker->city(),
            'description' => $this->faker->boolean(70) ? $this->faker->sentence(12) : null,
            'description_lang' => 'es',
            'contact_name'  => $this->faker->name(),
            'contact_phone' => '+346'.$this->faker->numerify('########'),
            'contact_email' => $this->faker->safeEmail(),
            'contact_whatsapp' => $this->faker->boolean(70),
            'status'      => 'active',
            'moderation_score' => 0,
            'phone_verified_at' => now(),
            'published_at' => $this->faker->dateTimeBetween('-50 days', 'now'),
            'expires_at'  => now()->addDays(60),
            'views'       => $this->faker->numberBetween(0, 400),
            'contact_reveals' => $this->faker->numberBetween(0, 40),
            'slug'        => $msisdn.'-'.Str::lower(Str::random(6)),
        ];
    }

    /**
     * Продаваемый префикс, взятый из плана нумерации (таблица numbering_ranges).
     *
     * Фабрика намеренно НЕ знает, что мобильные начинаются на 6 и 7: она
     * спрашивает план. Следствие — когда CNMC откроет новый диапазон и его
     * добавят строкой в БД, демо-данные подхватят его сами, без правки кода.
     *
     * Раньше здесь было `randomElement(['6','7']) . numerify('########')`,
     * и в 5% случаев выпадал 70X — numeración personal, которую собственный
     * блок-лист и конвейер модерации отвергают. Демо-данные противоречили
     * собственным правилам.
     */
    private function sellablePrefix(): array
    {
        $prefixes = app(NumberingPlan::class)->sellablePrefixes();

        return $this->faker->randomElement($prefixes);
    }

    /** Обычный номер: продаваемый префикс + добивка случайными цифрами. */
    private function randomMsisdn(): string
    {
        ['prefix' => $p, 'length' => $len] = $this->sellablePrefix();

        return $p.$this->faker->numerify(str_repeat('#', $len - strlen($p)));
    }

    // ---- Состояния для «красивых» номеров: нужны, чтобы было что искать ----
    //
    // Генерация номера и state разделены намеренно. Метод-состояние (repetido
    // и т.д.) отдаёт factory, готовый создать одну строку. Метод-генератор
    // (genRepetido и т.д.) — чистая функция «дай ещё один такой номер»: сидер
    // зовёт её в цикле, чтобы набрать уникальный набор msisdn, потому что
    // active_msisdn — UNIQUE, и два одинаковых активных номера падают на вставке.

    /** 666666666 */
    public function repetido(): static
    {
        return $this->withMsisdn(fn () => $this->genRepetido());
    }

    /** Палиндром: 612 3 4 3 216 */
    public function capicua(): static
    {
        return $this->withMsisdn(fn () => $this->genCapicua());
    }

    /** 612345678 / 698765432 */
    public function escalera(): static
    {
        return $this->withMsisdn(fn () => $this->genEscalera());
    }

    /** Хвост из четырёх одинаковых: 61234 7777 */
    public function terminacion(): static
    {
        return $this->withMsisdn(fn () => $this->genTerminacion());
    }

    /**
     * Все девять цифр одинаковы. Таких номеров крайне мало: при нынешнем
     * плане продаваемы ровно два — 666666666 и 777777777. Изначально сидер
     * просил пятнадцать, чего физически не существует, и упирался в
     * active_msisdn UNIQUE. Сколько их — решает план, а не эта константа.
     */
    public function genRepetido(): string
    {
        $plan = app(NumberingPlan::class);

        // Кандидаты строим из плана, а не из зашитых [6,7]: если откроют
        // новый диапазон, «репетидо» в нём появится сам.
        $candidates = [];
        foreach (range(0, 9) as $d) {
            $n = str_repeat((string) $d, NumberPatternQuery::LENGTH);
            if ($plan->isSellable($n)) {
                $candidates[] = $n;
            }
        }

        return $this->faker->randomElement($candidates);
    }

    /** Палиндром: 4 цифры + средняя + зеркало. ~20 000 вариантов. */
    public function genCapicua(): string
    {
        return $this->generateSellable(function () {
            $head = $this->faker->numerify('####');
            $mid  = (string) $this->faker->numberBetween(0, 9);

            return $head.$mid.strrev($head);
        });
    }

    /**
     * Лестница: гарантированный подряд-возрастающий прогон длиной 6 (порог
     * тега — 6, см. PatternTagger::isEscalera) со сдвигом внутри номера,
     * остальные позиции — случайные цифры. Первая цифра оставлена случайной
     * и отбраковывается планом: так генератор не знает про диапазоны.
     */
    public function genEscalera(): string
    {
        return $this->generateSellable(function () {
            $start  = $this->faker->numberBetween(0, 4);  // start..start+5 влезает в 0..9
            $offset = $this->faker->numberBetween(1, 3);  // прогон длиной 6 внутри 9 цифр

            $d = [];
            for ($i = 0; $i < 9; $i++) {
                $d[] = ($i >= $offset && $i < $offset + 6)
                    ? (string) ($start + ($i - $offset))
                    : (string) $this->faker->numberBetween(0, 9);
            }

            return implode('', $d);
        });
    }

    /** Хвост из четырёх одинаковых: 61234 7777. ~200 000 вариантов. */
    public function genTerminacion(): string
    {
        return $this->generateSellable(fn () => $this->faker->numerify('#####')
            .str_repeat((string) $this->faker->numberBetween(0, 9), 4));
    }

    /**
     * Генератор предлагает — план решает.
     *
     * «Красивые» паттерны имеют структуру (палиндром, лестница), которая
     * задаёт первые цифры и может не попасть в продаваемый диапазон.
     * Городить условия внутри каждого генератора — значит вшить туда знание
     * о плане нумерации, которое мы только что оттуда убрали. Проще
     * перевыбрать: отбраковка редкая, цикл сходится.
     *
     * Лимит защищает от вечного цикла, если план запретит всё: молча
     * крутиться вечно хуже, чем упасть с внятным сообщением.
     */
    private function generateSellable(callable $gen, int $maxTries = 500): string
    {
        $plan = app(NumberingPlan::class);

        for ($i = 0; $i < $maxTries; $i++) {
            $n = $gen();

            if ($plan->isSellable($n)) {
                return $n;
            }
        }

        throw new \RuntimeException(
            'Не удалось сгенерировать продаваемый номер за '.$maxTries.' попыток. '
            .'Похоже, numbering_ranges запрещает всё — проверь NumberingRangeSeeder.'
        );
    }

    /** Общий помощник: подставить номер и пересчитать теги и slug. */
    public function withMsisdn(callable $gen): static
    {
        return $this->state(function () use ($gen) {
            $m = $gen();

            return [
                'msisdn'       => $m,
                'pattern_tags' => PatternTagger::tag($m),
                'slug'         => $m.'-'.Str::lower(Str::random(6)),
                // Красивые номера стоят дороже — иначе сортировка по цене
                // на тестовых данных бессмысленна.
                'price'        => $this->faker->randomElement([2000, 3500, 5000, 9000, 15000]),
            ];
        });
    }

    // ---- Состояния жизненного цикла ----

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'published_at' => null,
            'phone_verified_at' => null,
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn () => ['status' => 'sold']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'expires_at' => now()->subDays(3),
        ]);
    }
}
