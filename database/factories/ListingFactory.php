<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\Province;
use App\Models\User;
use App\Services\Search\PatternTagger;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Listing>
 *
 * Генерирует валидные испанские мобильные (6xx/7xx, 9 цифр).
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

    /** Обычный номер: 6/7 + 8 случайных цифр. */
    private function randomMsisdn(): string
    {
        return $this->faker->randomElement(['6', '7']).$this->faker->numerify('########');
    }

    // ---- Состояния для «красивых» номеров: нужны, чтобы было что искать ----

    /** 666666666 */
    public function repetido(): static
    {
        return $this->withMsisdn(fn () => str_repeat((string) $this->faker->randomElement([6, 7]), 9));
    }

    /** Палиндром: 612 3 4 3 216 */
    public function capicua(): static
    {
        return $this->withMsisdn(function () {
            $head = $this->faker->randomElement(['6', '7']).$this->faker->numerify('###');
            $mid  = (string) $this->faker->numberBetween(0, 9);

            return $head.$mid.strrev($head);
        });
    }

    /** 612345678 / 698765432 */
    public function escalera(): static
    {
        return $this->withMsisdn(function () {
            $start = $this->faker->numberBetween(1, 2);
            $n = $this->faker->randomElement(['6', '7']);

            for ($i = 0; $i < 8; $i++) {
                $n .= ($start + $i) % 10;
            }

            return $n;
        });
    }

    /** Хвост из четырёх одинаковых: 61234 7777 */
    public function terminacion(): static
    {
        return $this->withMsisdn(fn () => $this->faker->randomElement(['6', '7'])
            .$this->faker->numerify('####')
            .str_repeat((string) $this->faker->numberBetween(0, 9), 4));
    }

    /** Общий помощник: подставить номер и пересчитать теги и slug. */
    private function withMsisdn(callable $gen): static
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
