<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Доступ в админку.
 *
 * Панель — единственное место, где видно жалобы, очередь модерации и
 * скрейпера (виджет топ-раскрытий). Вход в неё не парольный: гость улетает
 * на Google. Права разведены по ролям через авторизацию ресурсов, а не
 * скрытием пунктов меню — спрятанная ссылка не защищает URL.
 */
class AdminAccessTest extends TestCase
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

    private string $listingsUrl = '/admin/listings';

    private string $settingsUrl = '/admin/settings';

    #[Test]
    public function a_guest_is_redirected_to_google_not_shown_a_login_form(): void
    {
        // Своей страницы входа у панели нет (инвариант №5): гость упирается
        // в Authenticate и улетает на Google. Не 500 и не форма пароля.
        $this->get('/admin')
            ->assertRedirect(route('auth.google.redirect'));
    }

    #[Test]
    public function a_user_without_a_role_cannot_enter_the_panel(): void
    {
        // role = NULL по умолчанию: доступ не достаётся случайно.
        $this->actingAs(User::factory()->create(['role' => null]))
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_blocked_superadmin_cannot_enter_the_panel(): void
    {
        // Блокировка сильнее привилегии: скомпрометированный аккаунт
        // модератора не должен сохранять доступ после бана.
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'blocked']))
            ->get('/admin')
            ->assertForbidden();
    }

    #[Test]
    public function a_moderator_can_see_listings_but_not_settings(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->actingAs($moderator)->get($this->listingsUrl)->assertOk();
        $this->actingAs($moderator)->get($this->settingsUrl)->assertForbidden();
    }

    #[Test]
    public function a_superadmin_can_see_both(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin)->get($this->listingsUrl)->assertOk();
        $this->actingAs($superadmin)->get($this->settingsUrl)->assertOk();
    }

    #[Test]
    public function the_admin_listing_table_never_renders_the_full_contact(): void
    {
        // Инвариант №2 без исключения для админки: экспорт/таблица с полным
        // контактом — готовая утечка базы. Показываем только маску.
        $listing = Listing::factory()->create([
            'user_id'       => User::factory(),
            'msisdn'        => '612345678',
            'status'        => 'active',
            'contact_phone' => '+34655443322',
            'contact_email' => 'juan.martinez@gmail.com',
            'contact_name'  => 'Juan Martínez García',
            'slug'          => '612345678-adm',
        ]);

        $html = $this->actingAs(User::factory()->create(['role' => 'moderator']))
            ->get($this->listingsUrl)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('655443322', $html, 'полный телефон в таблице админки');
        $this->assertStringNotContainsString('juan.martinez@gmail.com', $html, 'email в таблице админки');
        $this->assertStringNotContainsString('Martínez', $html, 'полная фамилия в таблице админки');
    }

    #[Test]
    public function the_admin_shop_table_never_renders_the_full_business_phone(): void
    {
        // Магазин — тоже продавец. Инвариант №2 без исключения: полный
        // бизнес-контакт не рендерится в админке (ни таблица, ни экспорт).
        // NIF/CIF модератор проверяет алгоритмом, телефон для этого не нужен.
        Shop::create([
            'user_id'       => User::factory()->create()->id,
            'name'          => 'Números Pro SL',
            'slug'          => 'numeros-pro',
            'nif_cif'       => 'B12345674',
            'city'          => 'Madrid',
            'contact_phone' => '+34611998877',
            'status'        => 'pending',
        ]);

        $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))
            ->get('/admin/shops')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('611998877', $html, 'полный телефон магазина в таблице админки');
        $this->assertStringContainsString('6** ** ** **', $html, 'маска телефона магазина не показана');
    }
}
