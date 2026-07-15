<?php

namespace Tests\Feature;

use App\Models\BlocklistNumber;
use App\Models\Listing;
use App\Models\ModerationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Moderation\ModerationPipeline;
use App\Services\Moderation\RuleOutcome;
use App\Services\Moderation\Rules\NotBlocklisted;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Решение конвейера — ворота на витрину.
 *
 * Худший исход, который эти тесты обязаны ловить: объявление стало `active`
 * в обход проверок. На витрине это означает чужой номер, непроверенный
 * магазин или контакты в обход гейта.
 */
class ModerationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private ModerationPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);

        NotBlocklisted::flush();

        $this->pipeline = new ModerationPipeline;
    }

    /** Чистое объявление, прошедшее OTP: должно публиковаться. */
    private function clean(array $attrs = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'user_id'           => User::factory()->create(['created_at' => now()->subYear()]),
            'msisdn'            => '612345678',
            'status'            => 'pending',
            'phone_verified_at' => now(),
            'published_at'      => null,
            'price'             => 250,
            'is_negotiable'     => false,
            'description'       => 'Número fácil de recordar, poco usado.',
            'contact_phone'     => '+34655443322',
            'contact_email'     => 'juan@gmail.com',
            'slug'              => 'x-'.uniqid(),
        ], $attrs));
    }

    // -----------------------------------------------------------------
    // Счастливый путь
    // -----------------------------------------------------------------

    #[Test]
    public function a_clean_listing_gets_published(): void
    {
        $verdict = $this->pipeline->run($this->clean());

        $this->assertSame('active', $verdict->status, 'чистое объявление не опубликовалось');
        $this->assertSame(0, $verdict->score);
        $this->assertSame([], $verdict->rejects());
    }

    #[Test]
    public function publishing_sets_dates(): void
    {
        Setting::updateOrCreate(['key' => 'listing.ttl_days'], ['value' => '60', 'type' => 'int']);
        cache()->flush();

        $listing = $this->clean();
        $this->pipeline->run($listing);

        $fresh = $listing->fresh();

        $this->assertNotNull($fresh->published_at);
        $this->assertNotNull($fresh->expires_at);
        $this->assertEqualsWithDelta(60, now()->diffInDays($fresh->expires_at), 1);
    }

    #[Test]
    public function a_repeat_run_does_not_bump_the_publication_date(): void
    {
        // Иначе правка объявления поднимала бы его наверх витрины —
        // бесплатный буст, которым немедленно начали бы пользоваться.
        $listing = $this->clean();
        $this->pipeline->run($listing);

        $first = $listing->fresh()->published_at;

        $this->travel(2)->days();
        $this->pipeline->run($listing->fresh());

        $this->assertEquals($first, $listing->fresh()->published_at);
    }

    // -----------------------------------------------------------------
    // OTP — главный барьер
    // -----------------------------------------------------------------

    #[Test]
    public function a_listing_without_otp_is_never_published(): void
    {
        // Если этот тест покраснеет, площадка начнёт публиковать номера,
        // владение которыми никто не подтверждал.
        $verdict = $this->pipeline->run($this->clean(['phone_verified_at' => null]));

        $this->assertSame('pending', $verdict->status);
        $this->assertArrayHasKey('otp_verified', $verdict->holds());
    }

    #[Test]
    public function a_missing_otp_is_a_hold_not_a_rejection(): void
    {
        // Продавец в процессе, а не виноват. Показать ему «отклонено» —
        // соврать, и он уйдёт.
        $verdict = $this->pipeline->run($this->clean(['phone_verified_at' => null]));

        $this->assertSame([], $verdict->rejects());
        $this->assertSame(RuleOutcome::Hold, $verdict->results['otp_verified']->outcome);
    }

    #[Test]
    public function otp_can_be_disabled_by_a_feature_flag_for_dev_only(): void
    {
        Setting::updateOrCreate(['key' => 'features.otp_enabled'], ['value' => 'false', 'type' => 'bool']);
        cache()->flush();

        $verdict = $this->pipeline->run($this->clean(['phone_verified_at' => null]));

        $this->assertSame('active', $verdict->status);
    }

    // -----------------------------------------------------------------
    // Отказы
    // -----------------------------------------------------------------

    #[Test]
    public function a_rejection_beats_everything_else(): void
    {
        $verdict = $this->pipeline->run($this->clean(['msisdn' => '701234567']));

        $this->assertSame('rejected', $verdict->status);
        $this->assertNotNull($verdict->reason);
    }

    #[Test]
    public function personal_numbering_is_rejected_with_the_reason_from_the_plan(): void
    {
        $verdict = $this->pipeline->run($this->clean(['msisdn' => '701234567']));

        $this->assertSame('rejected', $verdict->status);
        $this->assertStringContainsString('personal', $verdict->reason);
    }

    #[Test]
    public function a_blocklisted_number_is_rejected(): void
    {
        BlocklistNumber::create([
            'msisdn_pattern' => '612345678',
            'reason'         => 'Denuncia confirmada',
            'is_active'      => true,
        ]);
        NotBlocklisted::flush();

        $verdict = (new ModerationPipeline)->run($this->clean(['msisdn' => '612345678']));

        $this->assertSame('rejected', $verdict->status);
    }

    #[Test]
    public function a_blocklist_wildcard_pattern_works(): void
    {
        BlocklistNumber::create([
            'msisdn_pattern' => '6123?????',
            'reason'         => 'Rango problemático',
            'is_active'      => true,
        ]);
        NotBlocklisted::flush();

        $this->assertSame('rejected', (new ModerationPipeline)->run($this->clean(['msisdn' => '612345678']))->status);
        $this->assertSame('active', (new ModerationPipeline)->run($this->clean(['msisdn' => '698765432']))->status);
    }

    #[Test]
    public function an_inactive_blocklist_entry_does_not_block(): void
    {
        BlocklistNumber::create([
            'msisdn_pattern' => '612345678',
            'reason'         => 'Ya resuelto',
            'is_active'      => false,
        ]);
        NotBlocklisted::flush();

        $this->assertSame('active', (new ModerationPipeline)->run($this->clean())->status);
    }

    #[Test]
    public function an_active_duplicate_is_rejected(): void
    {
        Listing::factory()->create([
            'user_id' => User::factory(),
            'msisdn'  => '612345678',
            'status'  => 'active',
            'slug'    => 'taken-'.uniqid(),
        ]);

        $verdict = $this->pipeline->run($this->clean(['msisdn' => '612345678']));

        $this->assertSame('rejected', $verdict->status);
    }

    #[Test]
    public function a_price_outside_the_range_is_rejected(): void
    {
        $this->assertSame('rejected', $this->pipeline->run($this->clean(['price' => 999999]))->status);
        $this->assertSame('rejected', $this->pipeline->run($this->clean(['price' => 0]))->status);
    }

    #[Test]
    public function a_negotiable_listing_needs_no_price(): void
    {
        $verdict = $this->pipeline->run($this->clean(['price' => null, 'is_negotiable' => true]));

        $this->assertSame('active', $verdict->status);
    }

    #[Test]
    public function a_contact_phone_equal_to_the_number_for_sale_is_rejected(): void
    {
        // Частая честная ошибка: человек машинально вписывает тот же номер.
        // Продав его, он потеряет связь с покупателями.
        $verdict = $this->pipeline->run($this->clean([
            'msisdn'        => '612345678',
            'contact_phone' => '+34612345678',
        ]));

        $this->assertSame('rejected', $verdict->status);
    }

    // -----------------------------------------------------------------
    // Подозрения и порог
    // -----------------------------------------------------------------

    #[Test]
    public function a_phone_in_the_description_sends_the_listing_to_manual_review(): void
    {
        // Телефон в свободном тексте обходит и Google-гейт, и лимиты,
        // и маскировку разом. Одна такая строка сводит на нет всю защиту.
        $verdict = $this->pipeline->run($this->clean([
            'description' => 'Llámame al 655 44 33 22, respondo rápido.',
        ]));

        $this->assertSame('pending', $verdict->status);
        $this->assertArrayHasKey('contacts_in_text', $verdict->flags());
    }

    #[Test]
    public function the_number_for_sale_appearing_in_its_own_description_is_not_a_contact_leak(): void
    {
        // Продаваемый номер и так виден целиком — это товар.
        $verdict = $this->pipeline->run($this->clean([
            'msisdn'      => '612345678',
            'description' => 'El número 612345678 es muy fácil de recordar.',
        ]));

        $this->assertSame('active', $verdict->status, 'ложное срабатывание на собственном номере');
    }

    #[Test]
    public function an_email_or_url_in_the_description_is_flagged(): void
    {
        $this->assertSame('pending', $this->pipeline->run($this->clean([
            'description' => 'Escríbeme a juan@example.com',
        ]))->status);

        $this->assertSame('pending', $this->pipeline->run($this->clean([
            'description' => 'Más info en https://mi-tienda.example',
        ]))->status);
    }

    #[Test]
    public function an_ordinary_description_with_digits_is_not_flagged(): void
    {
        // «vendo 3 números», год, цена — цифры в тексте не всегда телефон.
        // Ложное срабатывание тут стоит нам честного продавца.
        // Номера разные: иначе второе объявление упрётся в антидубль,
        // и тест покажет отказ, не имеющий отношения к описанию.
        $cases = [
            '612345678' => 'Vendo 3 números de la misma serie.',
            '698765432' => 'Línea de 2019, poco uso.',
            '677111222' => 'Precio 250 euros, negociable.',
        ];

        foreach ($cases as $msisdn => $description) {
            $this->assertSame(
                'active',
                (new ModerationPipeline)->run($this->clean([
                    'msisdn'      => (string) $msisdn,
                    'description' => $description,
                ]))->status,
                "ложное срабатывание на: {$description}"
            );
        }
    }

    #[Test]
    public function a_brand_new_account_alone_does_not_block_publication(): void
    {
        // Молодость аккаунта — обстоятельство, а не улика. Вес 1 при пороге 3.
        $verdict = $this->pipeline->run($this->clean([
            'user_id' => User::factory()->create(['created_at' => now()]),
        ]));

        $this->assertSame('active', $verdict->status);
        $this->assertSame(1, $verdict->score);
        $this->assertArrayHasKey('account_age', $verdict->flags());
    }

    #[Test]
    public function suspicions_accumulate_and_cross_the_threshold(): void
    {
        // Ни одно из этих подозрений само по себе не блокирует. Вместе — да.
        // Именно так конвейер и должен работать.
        $verdict = $this->pipeline->run($this->clean([
            'user_id'       => User::factory()->create(['created_at' => now()]),
            'contact_email' => 'seller@mailinator.com',
        ]));

        $this->assertSame('pending', $verdict->status);
        $this->assertGreaterThanOrEqual(3, $verdict->score);
    }

    #[Test]
    public function the_manual_review_threshold_comes_from_settings(): void
    {
        Setting::updateOrCreate(['key' => 'moderation.manual_threshold'], ['value' => '1', 'type' => 'int']);
        cache()->flush();

        // Один только возраст аккаунта (вес 1) теперь достаточен.
        $verdict = (new ModerationPipeline)->run($this->clean([
            'user_id' => User::factory()->create(['created_at' => now()]),
        ]));

        $this->assertSame('pending', $verdict->status);
    }

    #[Test]
    public function an_unverified_shop_listing_waits(): void
    {
        $user = User::factory()->create(['seller_type' => 'shop', 'created_at' => now()->subYear()]);

        $verdict = $this->pipeline->run($this->clean(['user_id' => $user->id]));

        $this->assertSame('pending', $verdict->status);
        $this->assertArrayHasKey('shop_verified', $verdict->flags());
    }

    #[Test]
    public function submitting_too_many_listings_in_a_day_is_flagged(): void
    {
        Setting::updateOrCreate(['key' => 'listing.per_day_user'], ['value' => '2', 'type' => 'int']);
        cache()->flush();

        $user = User::factory()->create(['created_at' => now()->subYear()]);

        Listing::factory()->count(2)->create([
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        $verdict = (new ModerationPipeline)->run($this->clean(['user_id' => $user->id]));

        $this->assertArrayHasKey('rate_limit', $verdict->flags());
    }

    // -----------------------------------------------------------------
    // Логи
    // -----------------------------------------------------------------

    #[Test]
    public function every_rule_is_logged_including_the_ones_that_passed(): void
    {
        // Без строк 'pass' невозможно отличить «правило отработало и
        // претензий нет» от «правило не запускалось» — а через полгода
        // на спорном случае это ровно тот вопрос, который возникнет.
        $listing = $this->clean();
        $verdict = $this->pipeline->run($listing);

        $logged = ModerationLog::where('listing_id', $listing->id)->pluck('rule')->all();

        foreach (array_keys($verdict->results) as $rule) {
            $this->assertContains($rule, $logged, "правило {$rule} не попало в лог");
        }
    }

    #[Test]
    public function the_log_carries_the_payload_explaining_the_decision(): void
    {
        $listing = $this->clean(['description' => 'Mi email: juan@example.com']);
        $this->pipeline->run($listing);

        $log = ModerationLog::where('listing_id', $listing->id)
            ->where('rule', 'contacts_in_text')
            ->first();

        $this->assertSame('flag', $log->result);
        $this->assertArrayHasKey('emails', $log->payload);
    }

    #[Test]
    public function a_rejection_reason_is_stored_on_the_listing(): void
    {
        $listing = $this->clean(['msisdn' => '701234567']);
        $this->pipeline->run($listing);

        $this->assertNotNull($listing->fresh()->rejection_reason);
    }

    #[Test]
    public function a_rejection_reason_is_cleared_once_the_listing_is_fixed(): void
    {
        $listing = $this->clean(['msisdn' => '701234567']);
        $this->pipeline->run($listing);
        $this->assertNotNull($listing->fresh()->rejection_reason);

        $listing->update(['msisdn' => '698765432']);
        $this->pipeline->run($listing->fresh());

        $this->assertNull($listing->fresh()->rejection_reason, 'причина отказа осталась после исправления');
        $this->assertSame('active', $listing->fresh()->status);
    }

    // -----------------------------------------------------------------
    // dryRun
    // -----------------------------------------------------------------

    #[Test]
    public function dry_run_changes_nothing(): void
    {
        $listing = $this->clean();

        $verdict = $this->pipeline->dryRun($listing);

        $this->assertSame('active', $verdict->status);
        $this->assertSame('pending', $listing->fresh()->status, 'dryRun изменил объявление');
        $this->assertSame(0, ModerationLog::where('listing_id', $listing->id)->count(), 'dryRun написал в лог');
    }
}
