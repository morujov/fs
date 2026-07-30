<?php

namespace Tests\Feature;

use App\Models\ContactReveal;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Report;
use App\Models\SavedSearch;
use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Права субъекта данных — GDPR ст. 15 и 17. Блокер №3 блюпринта.
 *
 * Мы пять этапов строили защиту контактов продавца от посторонних — и
 * параллельно вечно копили IP покупателей без единого способа их стереть.
 * Эти тесты про вторую половину: данные защищены не только от чужих,
 * но и от нас.
 */
class GdprTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    private function seller(): User
    {
        return User::factory()->create([
            'name'  => 'Juan Martínez',
            'email' => 'juan@gmail.com',
            'phone' => '+34655443322',
        ]);
    }

    private function listing(User $user, array $attrs = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'user_id'       => $user->id,
            'status'        => 'active',
            'contact_name'  => 'Juan Martínez',
            'contact_phone' => '+34655443322',
            'contact_email' => 'juan@gmail.com',
            'slug'          => 'l-'.uniqid(),
        ], $attrs));
    }

    // -----------------------------------------------------------------
    // Доступ
    // -----------------------------------------------------------------

    #[Test]
    public function a_guest_cannot_reach_the_privacy_page(): void
    {
        $this->get(route('account.privacy.show'))->assertRedirect();
    }

    #[Test]
    public function a_user_sees_what_we_store(): void
    {
        $user = $this->seller();
        $this->listing($user);

        $this->actingAs($user)
            ->get(route('account.privacy.show'))
            ->assertOk()
            ->assertSee(__('gdpr.stored_title'));
    }

    // -----------------------------------------------------------------
    // Ст. 15 и 20 — выгрузка
    // -----------------------------------------------------------------

    #[Test]
    public function a_user_can_export_their_data(): void
    {
        $user = $this->seller();
        $this->listing($user);

        $this->actingAs($user)
            ->get(route('account.privacy.export'))
            ->assertOk()
            ->assertHeader('content-disposition')
            ->assertJsonStructure(['exported_at', 'account', 'listings', 'contacts_you_revealed']);
    }

    #[Test]
    public function the_export_shows_the_ips_we_recorded(): void
    {
        // Именно это чаще всего удивляет людей — и именно поэтому обязано
        // быть в выгрузке. Прятать неудобное значит врать в политике.
        $user = $this->seller();
        $other = $this->listing(User::factory()->create(), ['slug' => 'other-'.uniqid()]);

        ContactReveal::create([
            'user_id'    => $user->id,
            'listing_id' => $other->id,
            'ip'         => '203.0.113.7',
        ]);

        $this->actingAs($user)
            ->get(route('account.privacy.export'))
            ->assertOk()
            ->assertJsonFragment(['ip' => '203.0.113.7']);
    }

    #[Test]
    public function the_export_does_not_leak_the_google_id(): void
    {
        // Это идентификатор, а не данные о человеке. В чужих руках лишний.
        $user = $this->seller();

        $json = $this->actingAs($user)->get(route('account.privacy.export'))->getContent();

        $this->assertStringNotContainsString($user->google_id, $json);
    }

    #[Test]
    public function a_user_can_only_export_their_own_data(): void
    {
        $mine = $this->seller();
        $stranger = User::factory()->create(['email' => 'stranger@gmail.com']);
        $this->listing($stranger, ['msisdn' => '698765432']);

        $json = $this->actingAs($mine)->get(route('account.privacy.export'))->getContent();

        $this->assertStringNotContainsString('stranger@gmail.com', $json);
        $this->assertStringNotContainsString('698765432', $json);
    }

    // -----------------------------------------------------------------
    // Ст. 17 — удаление
    // -----------------------------------------------------------------

    #[Test]
    public function deleting_requires_typing_the_confirmation_word(): void
    {
        // Галочку ставят не глядя. Удаление необратимо — человек должен
        // успеть понять, что делает.
        $user = $this->seller();

        $this->actingAs($user)
            ->delete(route('account.privacy.destroy'), ['confirm' => 'si'])
            ->assertSessionHasErrors('confirm');

        $this->assertNotNull($user->fresh());
    }

    #[Test]
    public function a_user_can_delete_their_account(): void
    {
        $user = $this->seller();

        $this->actingAs($user)
            ->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR'])
            ->assertRedirect(route('home'));

        $this->assertNull(User::find($user->id));
        $this->assertGuest();
    }

    #[Test]
    public function deleting_scrubs_the_identity(): void
    {
        $user = $this->seller();

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR']);

        $this->assertDatabaseMissing('users', ['email' => 'juan@gmail.com']);
        $this->assertDatabaseMissing('users', ['phone' => '+34655443322']);
    }

    #[Test]
    public function deleting_withdraws_active_listings(): void
    {
        // Продавца больше нет — отвечать на звонки некому. Оставить
        // объявление активным значило бы обмануть покупателей.
        $user = $this->seller();
        $listing = $this->listing($user);

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR']);

        $this->assertSame('archived', $listing->fresh()->status);
    }

    #[Test]
    public function deleting_does_not_destroy_the_listing_history(): void
    {
        // Схема была NOT NULL + cascadeOnDelete: удаление аккаунта снесло бы
        // все объявления каскадом. Право на удаление превращалось в выбор
        // между «нарушить GDPR» и «уничтожить рыночные данные».
        $user = $this->seller();
        $listing = $this->listing($user);

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR']);

        $this->assertNotNull($listing->fresh(), 'объявление снесло каскадом');
    }

    #[Test]
    public function deleting_scrubs_contacts_from_listings(): void
    {
        $user = $this->seller();
        $listing = $this->listing($user);

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR']);

        $fresh = $listing->fresh();
        $this->assertSame('', $fresh->contact_phone);
        $this->assertNull($fresh->contact_email);
        $this->assertNull($fresh->user_id, 'объявление осталось привязано к удалённому аккаунту');
    }

    #[Test]
    public function deleting_anonymizes_reveals_but_keeps_the_listing_counter(): void
    {
        $user = $this->seller();
        $other = $this->listing(User::factory()->create(), ['slug' => 'other-'.uniqid()]);

        $reveal = ContactReveal::create([
            'user_id'    => $user->id,
            'listing_id' => $other->id,
            'ip'         => '203.0.113.7',
        ]);

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR']);

        $fresh = $reveal->fresh();
        $this->assertNotNull($fresh, 'раскрытие снесло каскадом — статистика объявления потеряна');
        $this->assertNull($fresh->user_id);
        $this->assertNull($fresh->ip);
    }

    #[Test]
    public function deleting_keeps_reports_but_detaches_them(): void
    {
        // Ст. 17(3)(e): жалоба может быть доказательством в чужом споре.
        // Стереть её по просьбе того, на кого жаловались, — прямой вред
        // пострадавшему.
        $user = $this->seller();
        $listing = $this->listing(User::factory()->create(), ['slug' => 'o-'.uniqid()]);

        $report = Report::create([
            'listing_id'  => $listing->id,
            'user_id'     => $user->id,
            'reporter_ip' => '203.0.113.7',
            'reason'      => 'not_mine',
            'status'      => 'open',
        ]);

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR']);

        $fresh = $report->fresh();
        $this->assertNotNull($fresh, 'жалоба удалена — доказательство в чужом споре уничтожено');
        $this->assertNull($fresh->user_id);
    }

    #[Test]
    public function deleting_removes_personal_odds_and_ends(): void
    {
        $user = $this->seller();
        $listing = $this->listing(User::factory()->create(), ['slug' => 'o-'.uniqid()]);

        SavedSearch::create(['user_id' => $user->id, 'pattern' => '6??77??77']);
        Favorite::create(['user_id' => $user->id, 'listing_id' => $listing->id]);

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR']);

        $this->assertSame(0, SavedSearch::where('user_id', $user->id)->count());
        $this->assertSame(0, Favorite::where('user_id', $user->id)->count());
    }

    #[Test]
    public function a_deleted_user_can_sign_up_again_with_the_same_google_account(): void
    {
        // Право на удаление не должно превращаться в пожизненный бан:
        // google_id уникален, и если оставить его как есть, повторная
        // регистрация упрётся в конфликт.
        $user = $this->seller();
        $googleId = $user->google_id;

        $this->actingAs($user)->delete(route('account.privacy.destroy'), ['confirm' => 'BORRAR']);

        $fresh = User::factory()->create(['google_id' => $googleId]);

        $this->assertNotNull($fresh->id);
    }
}
