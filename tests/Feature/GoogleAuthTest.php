<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Вход через Google — единственный способ попасть в систему.
 *
 * Паролей нет, поэтому здесь нет тестов на регистрацию, восстановление
 * и подтверждение email: этого в проекте не существует.
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    /** Подсовываем Socialite фейковый профиль вместо похода в Google. */
    private function fakeGoogleUser(array $attrs = []): void
    {
        $g = Mockery::mock(SocialiteUser::class);
        $g->shouldReceive('getId')->andReturn($attrs['id'] ?? '109876543210987654321');
        $g->shouldReceive('getName')->andReturn($attrs['name'] ?? 'Juan Martínez');
        $g->shouldReceive('getEmail')->andReturn($attrs['email'] ?? 'juan@gmail.com');
        $g->shouldReceive('getAvatar')->andReturn($attrs['avatar'] ?? 'https://lh3.googleusercontent.com/a/abc');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($g);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function a_first_time_sign_in_creates_the_user(): void
    {
        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))->assertRedirect();

        $user = User::first();

        $this->assertNotNull($user);
        $this->assertSame('109876543210987654321', $user->google_id);
        $this->assertSame('juan@gmail.com', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function the_new_user_has_no_seller_type_yet(): void
    {
        // На входе мы не знаем, покупатель это или продавец, и не спрашиваем.
        // Роль выясняется при первой подаче объявления.
        $this->fakeGoogleUser();
        $this->get(route('auth.google.callback'));

        $this->assertNull(User::first()->seller_type);
    }

    #[Test]
    public function signing_in_again_does_not_create_a_second_account(): void
    {
        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'));
        $this->post(route('logout'));
        $this->get(route('auth.google.callback'));

        $this->assertSame(1, User::count());
    }

    #[Test]
    public function a_changed_google_email_updates_the_account_instead_of_duplicating_it(): void
    {
        // Ищем по google_id (`sub`), а не по email: email в Google-аккаунте
        // меняется, sub — нет. Поиск по email означал бы, что смена почты
        // создаёт второй аккаунт и теряет все объявления продавца.
        $existing = User::factory()->create([
            'google_id' => '109876543210987654321',
            'email'     => 'old@gmail.com',
        ]);

        $this->fakeGoogleUser(['email' => 'new@gmail.com']);
        $this->get(route('auth.google.callback'));

        $this->assertSame(1, User::count());
        $this->assertSame('new@gmail.com', $existing->fresh()->email);
    }

    #[Test]
    public function signing_in_does_not_overwrite_fields_google_knows_nothing_about(): void
    {
        $user = User::factory()->create([
            'google_id'   => '109876543210987654321',
            'seller_type' => 'shop',
            'phone'       => '+34600111222',
            'status'      => 'active',
        ]);

        $this->fakeGoogleUser();
        $this->get(route('auth.google.callback'));

        $fresh = $user->fresh();

        $this->assertSame('shop', $fresh->seller_type, 'Google затёр тип продавца');
        $this->assertSame('+34600111222', $fresh->phone, 'Google затёр телефон');
    }

    #[Test]
    public function a_blocked_user_is_not_logged_in(): void
    {
        User::factory()->create([
            'google_id' => '109876543210987654321',
            'status'    => 'blocked',
        ]);

        $this->fakeGoogleUser();
        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    #[Test]
    public function a_failed_google_callback_does_not_crash_the_page(): void
    {
        // Сюда попадают отмена входа пользователем и протухший state.
        // Показывать stack trace продавцу незачем.
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('invalid state'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    #[Test]
    public function logging_out_works(): void
    {
        $this->fakeGoogleUser();
        $this->get(route('auth.google.callback'));

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    #[Test]
    public function the_intended_redirect_only_accepts_internal_paths(): void
    {
        // Открытый редирект на чужой домен — классическая дыра фишинга:
        // ссылка выглядит нашей, а после входа уводит на подделку.
        // Сам поход в Google здесь не нужен — проверяем только, что
        // попадает в сессию.
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.redirect', ['intended' => 'https://evil.example.com']));
        $this->assertNull(session('url.intended'), 'абсолютный чужой URL попал в редирект после входа');

        $this->get(route('auth.google.redirect', ['intended' => '//evil.example.com']));
        $this->assertNull(session('url.intended'), 'протокол-относительный URL уводит на чужой домен');

        $this->get(route('auth.google.redirect', ['intended' => '/mis-anuncios']));
        $this->assertSame('/mis-anuncios', session('url.intended'));
    }
}
