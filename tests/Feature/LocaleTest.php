<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Язык интерфейса: дефолт, переключатель, сессия, профиль.
 *
 * Инвариант №4: язык по умолчанию стабильно испанский — это канон и SEO.
 * Английский только явным выбором, не по заголовку браузера.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    private const ES = 'Números móviles en venta';

    private const EN = 'Mobile numbers for sale';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    #[Test]
    public function the_default_language_is_spanish(): void
    {
        $this->get('/')->assertOk()->assertSee(self::ES)->assertDontSee(self::EN);
    }

    #[Test]
    public function the_default_ignores_an_english_accept_language_header(): void
    {
        // SEO: аноним и Googlebot видят испанский по умолчанию независимо от
        // заголовка браузера. Иначе канон размывался бы по Accept-Language.
        $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertSee(self::ES);
    }

    #[Test]
    public function switching_records_the_choice_in_the_session(): void
    {
        $this->get(route('locale.switch', 'en'))
            ->assertRedirect()
            ->assertSessionHas('locale', 'en');
    }

    #[Test]
    public function a_session_choice_renders_the_interface_in_english(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee(self::EN)
            ->assertDontSee(self::ES);
    }

    #[Test]
    public function an_unknown_locale_is_ignored_and_does_not_break(): void
    {
        $this->get(route('locale.switch', 'zz'))
            ->assertRedirect()
            ->assertSessionMissing('locale');

        $this->get('/')->assertOk()->assertSee(self::ES);
    }

    #[Test]
    public function a_signed_in_users_choice_is_saved_to_their_profile(): void
    {
        $user = User::factory()->create(['locale' => 'es']);

        $this->actingAs($user)->get(route('locale.switch', 'en'))->assertRedirect();

        $this->assertSame('en', $user->fresh()->locale);
    }

    #[Test]
    public function a_signed_in_user_sees_their_saved_language_without_a_session(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get('/')->assertOk()->assertSee(self::EN);
    }

    #[Test]
    public function the_session_choice_beats_the_saved_profile_language(): void
    {
        // Явное «сейчас хочу по-испански» сильнее сохранённого профиля.
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->withSession(['locale' => 'es'])
            ->get('/')->assertOk()->assertSee(self::ES);
    }

    #[Test]
    public function the_switcher_offers_the_other_language(): void
    {
        // На испанской странице есть ссылка переключения на английский,
        // и она nofollow — краулеру ходить по ней незачем.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(route('locale.switch', 'en'), $html);
        $this->assertStringContainsString('Español', $html);
        $this->assertStringContainsString('English', $html);
        $this->assertMatchesRegularExpression('/rel="nofollow"[^>]*idioma\/en|idioma\/en[^>]*rel="nofollow"/', $html);
    }
}
