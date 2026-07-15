<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Operator;
use App\Models\Province;
use App\Models\User;
use App\Services\Sms\SmsSenderInterface;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Подача объявления.
 *
 * Ключевое: объявление НЕ попадает на витрину само. Оно доходит до
 * `pending` и ждёт OTP и конвейера модерации (S3). Если этот тест
 * покраснеет так, что появится `active` — на витрину поедет
 * непроверенный, возможно чужой номер.
 */
class ListingSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);

        // Никаких настоящих SMS в тестах.
        $this->app->bind(SmsSenderInterface::class, fn () => new class implements SmsSenderInterface
        {
            public function send(string $msisdn, string $text): void {}
        });
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'msisdn'        => '612345678',
            'price'         => 250,
            'is_negotiable' => false,
            'operator_id'   => Operator::first()->id,
            'line_type'     => 'prepago',
            'has_permanency' => false,
            'condition'     => 'used',
            'province_id'   => Province::first()->id,
            'city'          => 'Madrid',
            'description'   => 'Número fácil de recordar.',
            'contact_name'  => 'Juan Martínez',
            'contact_phone' => '+34655443322',
            'contact_email' => 'juan@example.com',
            'contact_whatsapp' => true,
            'seller_type'   => 'private',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // Доступ
    // -----------------------------------------------------------------

    #[Test]
    public function a_guest_cannot_submit_a_listing(): void
    {
        $this->post(route('seller.listings.store'), $this->payload())
            ->assertRedirect();

        $this->assertSame(0, Listing::count());
    }

    #[Test]
    public function a_blocked_user_cannot_submit_a_listing(): void
    {
        $user = User::factory()->create(['status' => 'blocked']);

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Listing::count());
    }

    // -----------------------------------------------------------------
    // Успешная подача
    // -----------------------------------------------------------------

    #[Test]
    public function a_submitted_listing_lands_in_pending_not_active(): void
    {
        $user = User::factory()->create(['seller_type' => null]);

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload())
            ->assertRedirect();

        $listing = Listing::first();

        $this->assertNotNull($listing);
        $this->assertSame('pending', $listing->status, 'объявление опубликовалось в обход модерации');
        $this->assertNull($listing->phone_verified_at, 'номер отмечен подтверждённым без OTP');
    }

    #[Test]
    public function the_seller_type_is_recorded_on_the_first_submission(): void
    {
        // На входе мы не знаем, покупатель это или продавец, — выясняем здесь.
        $user = User::factory()->create(['seller_type' => null]);

        $this->actingAs($user)->post(route('seller.listings.store'), $this->payload(['seller_type' => 'shop']));

        $this->assertSame('shop', $user->fresh()->seller_type);
    }

    #[Test]
    public function pattern_tags_are_computed_on_submission(): void
    {
        // Теги — это навигация и SEO-посадочные. Если их не посчитать при
        // сохранении, номер выпадет из своей категории молча.
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('seller.listings.store'), $this->payload(['msisdn' => '666666666']));

        $this->assertContains('repetido', Listing::first()->pattern_tags);
    }

    #[Test]
    public function the_number_is_normalized_before_storing(): void
    {
        // Продавец введёт как угодно. Хранить обязаны одну форму, иначе
        // антидубль по active_msisdn перестанет ловить дубли.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload(['msisdn' => '+34 612 34 56 78']));

        $this->assertSame('612345678', Listing::first()->msisdn);
    }

    // -----------------------------------------------------------------
    // Валидация номера — через план нумерации, не через регулярку
    // -----------------------------------------------------------------

    #[Test]
    public function a_personal_numbering_number_is_rejected_with_a_spanish_reason(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload(['msisdn' => '701234567']));

        $response->assertSessionHasErrors('msisdn');
        $this->assertSame(0, Listing::count());

        $error = session('errors')->first('msisdn');
        $this->assertStringContainsString('personal', $error, 'причина отказа не объясняет, что это numeración personal');
    }

    #[Test]
    public function a_landline_number_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload(['msisdn' => '912345678']))
            ->assertSessionHasErrors('msisdn');
    }

    #[Test]
    public function a_malformed_number_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload(['msisdn' => '61234']))
            ->assertSessionHasErrors('msisdn');
    }

    #[Test]
    public function a_number_with_an_active_listing_is_rejected(): void
    {
        Listing::factory()->create([
            'user_id' => User::factory(),
            'msisdn'  => '612345678',
            'status'  => 'active',
            'slug'    => '612345678-taken',
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('seller.listings.store'), $this->payload(['msisdn' => '612345678']))
            ->assertSessionHasErrors('msisdn');

        $this->assertSame(1, Listing::where('msisdn', '612345678')->count());
    }

    // -----------------------------------------------------------------
    // Цена
    // -----------------------------------------------------------------

    #[Test]
    public function price_is_required_unless_the_listing_is_negotiable(): void
    {
        // Иначе продавцы вобьют 1 или 999999, и сортировка по цене умрёт.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload(['price' => null, 'is_negotiable' => false]))
            ->assertSessionHasErrors('price');
    }

    #[Test]
    public function a_negotiable_listing_needs_no_price(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload(['price' => null, 'is_negotiable' => true]))
            ->assertSessionDoesntHaveErrors('price');

        $listing = Listing::first();
        $this->assertNull($listing->price);
        $this->assertTrue($listing->is_negotiable);
    }

    #[Test]
    public function a_price_outside_the_configured_range_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload(['price' => 999999]))
            ->assertSessionHasErrors('price');
    }

    // -----------------------------------------------------------------
    // Permanencia
    // -----------------------------------------------------------------

    #[Test]
    public function the_commitment_end_date_is_required_when_there_is_a_commitment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('seller.listings.store'), $this->payload([
                'has_permanency' => true,
                'permanency_until' => null,
            ]))
            ->assertSessionHasErrors('permanency_until');
    }

    // -----------------------------------------------------------------
    // Контакты
    // -----------------------------------------------------------------

    #[Test]
    public function the_contact_phone_is_stored_but_never_leaks_into_the_listing_json(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('seller.listings.store'), $this->payload());

        $listing = Listing::first();

        $this->assertSame('+34655443322', $listing->contact_phone, 'контакт не сохранился');
        $this->assertStringNotContainsString('655443322', $listing->toJson(), 'контакт утёк в сериализацию');
    }
}
