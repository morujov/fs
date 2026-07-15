<?php

namespace Tests\Feature;

use App\Models\ContactReveal;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Гейт на раскрытии контактов — то, ради чего построено всё остальное.
 *
 * Если эти тесты покраснеют, база продавцов выкачивается скриптом, продавцы
 * получают спам и уходят, и площадки не остаётся. Маскировка, лимиты и
 * whitelist в поиске защищают ровно эти данные.
 */
class ContactRevealTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->listing = Listing::factory()->create([
            'user_id'          => User::factory(),
            'msisdn'           => '612345678',
            'status'           => 'active',
            'contact_name'     => 'Juan Martínez García',
            'contact_phone'    => '+34655443322',
            'contact_email'    => 'juan.martinez@gmail.com',
            'contact_whatsapp' => true,
            'slug'             => '612345678-abc',
        ]);
    }

    private function buyer(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    // -----------------------------------------------------------------
    // Главный инвариант: контакт не утекает в HTML
    // -----------------------------------------------------------------

    #[Test]
    public function a_guest_never_sees_the_full_contact_in_the_html(): void
    {
        // Единственный тест, который стоит между нами и выкачанной базой.
        $html = $this->get(route('listings.show', $this->listing))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('655443322', $html, 'полный телефон в HTML');
        $this->assertStringNotContainsString('juan.martinez@gmail.com', $html, 'полный email в HTML');
        $this->assertStringNotContainsString('Martínez', $html, 'полная фамилия в HTML');
        $this->assertStringNotContainsString('wa.me', $html, 'ссылка WhatsApp даёт номер целиком');
    }

    #[Test]
    public function an_authenticated_user_who_has_not_revealed_yet_also_sees_no_contact_in_the_html(): void
    {
        // Вход сам по себе не раскрывает: нужен явный клик, и он под лимитом.
        // Иначе один залогиненный бот собрал бы всё обходом страниц.
        $html = $this->actingAs($this->buyer())
            ->get(route('listings.show', $this->listing))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('655443322', $html);
        $this->assertStringNotContainsString('juan.martinez@gmail.com', $html);
    }

    #[Test]
    public function the_masked_phone_is_shown_instead(): void
    {
        $this->get(route('listings.show', $this->listing))
            ->assertOk()
            ->assertSee('6** ** ** **');
    }

    #[Test]
    public function the_number_for_sale_is_visible_to_everyone(): void
    {
        // Инвариант №1: это товар и весь SEO. Никогда не маскируется.
        $this->get(route('listings.show', $this->listing))
            ->assertOk()
            ->assertSee('612 34 56 78');
    }

    // -----------------------------------------------------------------
    // Гейт
    // -----------------------------------------------------------------

    #[Test]
    public function a_guest_cannot_reveal_the_contact(): void
    {
        $this->postJson(route('listings.contact', $this->listing))
            ->assertUnauthorized();

        $this->assertSame(0, ContactReveal::count());
    }

    #[Test]
    public function an_authenticated_user_gets_the_full_contact(): void
    {
        $this->actingAs($this->buyer())
            ->postJson(route('listings.contact', $this->listing))
            ->assertOk()
            ->assertJson([
                'name'     => 'Juan Martínez García',
                'phone'    => '+34655443322',
                'email'    => 'juan.martinez@gmail.com',
                'whatsapp' => 'https://wa.me/34655443322',
                'revealed' => true,
            ]);
    }

    #[Test]
    public function revealing_is_logged(): void
    {
        // Лог — источник правды для лимитов и единственный способ увидеть
        // скрейпера. Без записи вся защита слепа.
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson(route('listings.contact', $this->listing))->assertOk();

        $this->assertDatabaseHas('contact_reveals', [
            'user_id'    => $buyer->id,
            'listing_id' => $this->listing->id,
        ]);

        $this->assertSame(1, $this->listing->fresh()->contact_reveals);
        $this->assertSame(1, $buyer->fresh()->reveal_count_total);
        $this->assertNotNull($buyer->fresh()->last_reveal_at);
    }

    #[Test]
    public function a_blocked_user_cannot_reveal(): void
    {
        $this->actingAs($this->buyer(['status' => 'blocked']))
            ->postJson(route('listings.contact', $this->listing))
            ->assertForbidden();

        $this->assertSame(0, ContactReveal::count());
    }

    #[Test]
    public function contacts_of_an_unpublished_listing_are_not_available(): void
    {
        // Объявление в pending никто ещё не проверял — его контакты
        // не наши, чтобы ими делиться.
        $pending = Listing::factory()->create([
            'user_id' => User::factory(),
            'msisdn'  => '698765432',
            'status'  => 'pending',
            'slug'    => 'pending-'.uniqid(),
        ]);

        $this->actingAs($this->buyer())
            ->postJson(route('listings.contact', $pending))
            ->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Повтор не тратит лимит
    // -----------------------------------------------------------------

    #[Test]
    public function revealing_the_same_listing_twice_does_not_consume_the_limit(): void
    {
        // Человек закрыл вкладку и вернулся. Это не новый доступ к данным —
        // он их уже видел. Списывать лимит значило бы ломать нормальный
        // сценарий ради видимости строгости.
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson(route('listings.contact', $this->listing))->assertOk();
        $this->actingAs($buyer)->postJson(route('listings.contact', $this->listing))->assertOk();

        $this->assertSame(1, ContactReveal::where('user_id', $buyer->id)->count(), 'вторая строка в логе');
        $this->assertSame(1, $this->listing->fresh()->contact_reveals, 'счётчик накрутился на повторе');
    }

    #[Test]
    public function a_repeat_reveal_works_even_when_the_daily_limit_is_exhausted(): void
    {
        Setting::updateOrCreate(['key' => 'reveal.per_day_user'], ['value' => '1', 'type' => 'int']);
        cache()->flush();

        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson(route('listings.contact', $this->listing))->assertOk();

        // Лимит исчерпан, но это объявление он уже открывал.
        $this->actingAs($buyer)->postJson(route('listings.contact', $this->listing))->assertOk();
    }

    // -----------------------------------------------------------------
    // Лимиты
    // -----------------------------------------------------------------

    private function otherListings(int $n): array
    {
        return Listing::factory()->count($n)->create([
            'user_id' => User::factory(),
            'status'  => 'active',
        ])->all();
    }

    #[Test]
    public function the_daily_limit_is_enforced(): void
    {
        Setting::updateOrCreate(['key' => 'reveal.per_day_user'], ['value' => '2', 'type' => 'int']);
        Setting::updateOrCreate(['key' => 'reveal.per_minute_user'], ['value' => '100', 'type' => 'int']);
        cache()->flush();

        $buyer = $this->buyer();
        $listings = $this->otherListings(3);

        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[0]))->assertOk();
        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[1]))->assertOk();

        $this->actingAs($buyer)
            ->postJson(route('listings.contact', $listings[2]))
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    #[Test]
    public function the_per_minute_limit_is_enforced(): void
    {
        Setting::updateOrCreate(['key' => 'reveal.per_minute_user'], ['value' => '2', 'type' => 'int']);
        cache()->flush();

        $buyer = $this->buyer();
        $listings = $this->otherListings(3);

        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[0]))->assertOk();
        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[1]))->assertOk();

        $this->actingAs($buyer)
            ->postJson(route('listings.contact', $listings[2]))
            ->assertStatus(429);
    }

    #[Test]
    public function crossing_the_autoblock_threshold_blocks_the_account(): void
    {
        // Столько контактов за сутки человек не открывает ни при каком
        // сценарии покупки. Дальше разговор окончен.
        Setting::updateOrCreate(['key' => 'reveal.autoblock_per_day'], ['value' => '2', 'type' => 'int']);
        Setting::updateOrCreate(['key' => 'reveal.per_minute_user'], ['value' => '100', 'type' => 'int']);
        Setting::updateOrCreate(['key' => 'reveal.per_day_user'], ['value' => '100', 'type' => 'int']);
        cache()->flush();

        $buyer = $this->buyer();
        $listings = $this->otherListings(3);

        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[0]))->assertOk();
        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[1]))->assertOk();

        $this->actingAs($buyer)
            ->postJson(route('listings.contact', $listings[2]))
            ->assertForbidden();

        $this->assertSame('blocked', $buyer->fresh()->status, 'аккаунт не заблокирован');
    }

    #[Test]
    public function the_ip_limit_catches_many_accounts_from_one_machine(): void
    {
        // Google-аккаунт стоит ноль евро. Скрейпер заведёт десять и будет
        // ходить с одной машины — на это и нужен IP-лимит.
        Setting::updateOrCreate(['key' => 'reveal.per_day_ip'], ['value' => '2', 'type' => 'int']);
        cache()->flush();

        $listings = $this->otherListings(3);

        foreach ([0, 1] as $i) {
            $this->actingAs($this->buyer())
                ->postJson(route('listings.contact', $listings[$i]), [], ['REMOTE_ADDR' => '203.0.113.7'])
                ->assertOk();
        }

        $this->actingAs($this->buyer())
            ->postJson(route('listings.contact', $listings[2]), [], ['REMOTE_ADDR' => '203.0.113.7'])
            ->assertStatus(429);
    }

    #[Test]
    public function limits_are_read_from_settings_not_hardcoded(): void
    {
        // Пороги правятся из админки без деплоя — иначе на shared-хостинге
        // за сменой цифры никто не полезет.
        Setting::updateOrCreate(['key' => 'reveal.per_day_user'], ['value' => '1', 'type' => 'int']);
        cache()->flush();

        $buyer = $this->buyer();
        $listings = $this->otherListings(2);

        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[0]))->assertOk();
        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[1]))->assertStatus(429);

        Setting::updateOrCreate(['key' => 'reveal.per_day_user'], ['value' => '5', 'type' => 'int']);
        cache()->flush();

        $this->actingAs($buyer)->postJson(route('listings.contact', $listings[1]))->assertOk();
    }
}
