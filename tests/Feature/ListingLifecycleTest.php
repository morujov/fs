<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ListingExpired;
use App\Notifications\ListingExpiringSoon;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Жизненный цикл объявления — блокеры №5 и №6 блюпринта.
 *
 * До этого `expires_at` заполнялся, но никто его не читал: объявления
 * не истекали никогда. Доска, где ничего не истекает и ничего нельзя
 * отметить проданным, за полгода превращается в список неактуальных
 * номеров, по которым никто не отвечает.
 */
class ListingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);

        Notification::fake();
    }

    private function listing(array $attrs = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'user_id'            => User::factory(),
            'msisdn'             => '612345678',
            'status'             => 'active',
            'phone_verified_at'  => now(),
            'expires_at'         => now()->addDays(60),
            'expiry_notified_at' => null,
            'slug'               => 'l-'.uniqid(),
        ], $attrs));
    }

    // -----------------------------------------------------------------
    // Истечение
    // -----------------------------------------------------------------

    #[Test]
    public function an_expired_listing_leaves_the_storefront(): void
    {
        $listing = $this->listing(['expires_at' => now()->subDay()]);

        $this->artisan('listings:expire')->assertSuccessful();

        $this->assertSame('expired', $listing->fresh()->status);
        $this->get(route('listings.show', $listing))->assertNotFound();
    }

    #[Test]
    public function expiring_frees_the_number_for_someone_else(): void
    {
        // Ради этого генерируемая колонка и делалась: забытое объявление
        // не должно держать номер занятым вечно.
        $listing = $this->listing(['expires_at' => now()->subDay()]);

        $this->artisan('listings:expire');

        $this->assertNull($listing->fresh()->active_msisdn);

        // Номер свободен — кто-то другой может его выставить.
        $other = $this->listing(['msisdn' => '612345678', 'slug' => 'other-'.uniqid()]);
        $this->assertSame('active', $other->status);
    }

    #[Test]
    public function a_listing_that_has_not_expired_yet_is_untouched(): void
    {
        $listing = $this->listing(['expires_at' => now()->addDays(30)]);

        $this->artisan('listings:expire');

        $this->assertSame('active', $listing->fresh()->status);
    }

    #[Test]
    public function only_active_listings_expire(): void
    {
        // Проданное не должно становиться «истёкшим» — это разные истории,
        // и в статистике они значат разное.
        $sold = $this->listing(['status' => 'sold', 'expires_at' => now()->subDay(), 'slug' => 's-'.uniqid()]);

        $this->artisan('listings:expire');

        $this->assertSame('sold', $sold->fresh()->status);
    }

    #[Test]
    public function the_seller_is_warned_before_expiry(): void
    {
        $listing = $this->listing(['expires_at' => now()->addDays(3)]);

        $this->artisan('listings:expire');

        Notification::assertSentTo($listing->user, ListingExpiringSoon::class);
        $this->assertNotNull($listing->fresh()->expiry_notified_at);
    }

    #[Test]
    public function the_warning_is_sent_only_once(): void
    {
        // Иначе письмо уходит каждый день все семь дней, человек отписывается,
        // и потом мы не достучимся до него ничем — включая алерты по
        // сохранённым поискам, единственный механизм возврата аудитории.
        $listing = $this->listing(['expires_at' => now()->addDays(3)]);

        $this->artisan('listings:expire');
        $this->artisan('listings:expire');

        Notification::assertSentToTimes($listing->user, ListingExpiringSoon::class, 1);
    }

    #[Test]
    public function the_seller_is_notified_when_the_listing_expires(): void
    {
        $listing = $this->listing(['expires_at' => now()->subDay()]);

        $this->artisan('listings:expire');

        Notification::assertSentTo($listing->user, ListingExpired::class);
    }

    #[Test]
    public function dry_run_changes_nothing(): void
    {
        $listing = $this->listing(['expires_at' => now()->subDay()]);

        $this->artisan('listings:expire --dry-run')->assertSuccessful();

        $this->assertSame('active', $listing->fresh()->status);
        Notification::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // Продано
    // -----------------------------------------------------------------

    #[Test]
    public function the_seller_can_mark_a_listing_sold(): void
    {
        $listing = $this->listing();

        $this->actingAs($listing->user)
            ->post(route('seller.listings.sold', $listing))
            ->assertRedirect();

        $fresh = $listing->fresh();
        $this->assertSame('sold', $fresh->status);
        $this->assertNotNull($fresh->sold_at);
    }

    #[Test]
    public function marking_sold_frees_the_number(): void
    {
        // Покупатель должен смочь выставить номер от своего имени, когда
        // портация завершится.
        $listing = $this->listing();

        $this->actingAs($listing->user)->post(route('seller.listings.sold', $listing));

        $this->assertNull($listing->fresh()->active_msisdn);
    }

    #[Test]
    public function a_stranger_cannot_mark_someone_elses_listing_sold(): void
    {
        $listing = $this->listing();

        $this->actingAs(User::factory()->create())
            ->post(route('seller.listings.sold', $listing))
            ->assertNotFound();

        $this->assertSame('active', $listing->fresh()->status);
    }

    #[Test]
    public function a_guest_cannot_mark_a_listing_sold(): void
    {
        $listing = $this->listing();

        $this->post(route('seller.listings.sold', $listing))->assertRedirect();

        $this->assertSame('active', $listing->fresh()->status);
    }

    #[Test]
    public function the_seller_can_withdraw_a_listing(): void
    {
        $listing = $this->listing();

        $this->actingAs($listing->user)
            ->post(route('seller.listings.archive', $listing))
            ->assertRedirect();

        $this->assertSame('archived', $listing->fresh()->status);
    }

    // -----------------------------------------------------------------
    // Продление
    // -----------------------------------------------------------------

    #[Test]
    public function the_seller_can_renew_from_the_panel(): void
    {
        Setting::updateOrCreate(['key' => 'listing.ttl_days'], ['value' => '60', 'type' => 'int']);
        cache()->flush();

        $listing = $this->listing(['expires_at' => now()->addDay()]);

        $this->actingAs($listing->user)
            ->get(route('seller.listings.renew', $listing))
            ->assertRedirect();

        $fresh = $listing->fresh();
        $this->assertEqualsWithDelta(60, now()->diffInDays($fresh->expires_at), 1);
        $this->assertSame(1, $fresh->renewals_count);
    }

    #[Test]
    public function a_signed_link_lets_the_seller_renew_without_signing_in(): void
    {
        // Ссылка приходит письмом. Заставлять человека логиниться и искать
        // объявление ради одной кнопки — гарантировать, что он этого
        // не сделает, и объявление умрёт не потому, что неактуально.
        $listing = $this->listing(['status' => 'expired', 'expires_at' => now()->subDay()]);

        $url = URL::signedRoute('seller.listings.renew', ['listing' => $listing]);

        $this->get($url)->assertRedirect();

        $this->assertSame('active', $listing->fresh()->status);
    }

    #[Test]
    public function an_unsigned_link_does_not_let_a_guest_renew(): void
    {
        $listing = $this->listing(['status' => 'expired']);

        $this->get(route('seller.listings.renew', $listing))->assertForbidden();

        $this->assertSame('expired', $listing->fresh()->status);
    }

    #[Test]
    public function a_tampered_signature_is_rejected(): void
    {
        $listing = $this->listing(['status' => 'expired']);

        $url = URL::signedRoute('seller.listings.renew', ['listing' => $listing]).'x';

        $this->get($url)->assertForbidden();
    }

    #[Test]
    public function renewing_resets_the_expiry_warning(): void
    {
        // Иначе после продления человек больше никогда не получит
        // предупреждение — и объявление умрёт молча.
        $listing = $this->listing([
            'expires_at'         => now()->addDay(),
            'expiry_notified_at' => now()->subDay(),
        ]);

        $this->actingAs($listing->user)->get(route('seller.listings.renew', $listing));

        $this->assertNull($listing->fresh()->expiry_notified_at);
    }

    #[Test]
    public function renewing_an_expired_listing_runs_the_moderation_pipeline_again(): void
    {
        // За 60 дней могло измениться что угодно: номер попал в блок-лист,
        // диапазон закрыли. Продление — это повторная публикация, и
        // проверяться она обязана как публикация.
        $listing = $this->listing([
            'status'            => 'expired',
            'phone_verified_at' => null,
        ]);

        $this->actingAs($listing->user)->get(route('seller.listings.renew', $listing));

        // OTP не пройден → конвейер держит в pending, а не публикует.
        $this->assertSame('pending', $listing->fresh()->status);
    }

    #[Test]
    public function an_expired_listing_cannot_be_renewed_if_someone_took_the_number(): void
    {
        $mine = $this->listing(['status' => 'expired', 'msisdn' => '612345678']);

        // Пока моё лежало истёкшим, номер выставил другой.
        $this->listing(['msisdn' => '612345678', 'slug' => 'other-'.uniqid()]);

        $this->actingAs($mine->user)
            ->get(route('seller.listings.renew', $mine))
            ->assertRedirect();

        $this->assertSame('expired', $mine->fresh()->status, 'продление затёрло чужое активное объявление');
    }

    #[Test]
    public function a_sold_listing_cannot_be_renewed(): void
    {
        $listing = $this->listing(['status' => 'sold']);

        $this->actingAs($listing->user)
            ->get(route('seller.listings.renew', $listing))
            ->assertNotFound();
    }
}
