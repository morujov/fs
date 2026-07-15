<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Единственный вход в систему.
 *
 * Паролей нет: ни формы регистрации, ни восстановления, ни хранения хэшей,
 * ни credential stuffing. Email от Google приходит верифицированным, поэтому
 * подтверждать его нечем и незачем.
 *
 * Scopes строго openid/email/profile — «базовый вход». Он не требует аудита
 * Google при верификации приложения и публичной политики конфиденциальности
 * на этапе Testing. Любой лишний scope ломает и то, и другое.
 */
class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        // Куда вернуть после входа. Только внутренние пути: открытый
        // редирект на чужой домен — классическая дыра фишинга.
        $intended = $request->query('intended');

        if (is_string($intended) && str_starts_with($intended, '/') && ! str_starts_with($intended, '//')) {
            session(['url.intended' => $intended]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Сюда попадают отмена входа пользователем, протухший state,
            // неверные креды. Показывать stack trace продавцу незачем.
            Log::warning('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('home')->with('error', __('auth.google_failed'));
        }

        $user = $this->upsert($googleUser);

        if ($user->isBlocked()) {
            // Не логиним. Сообщение нейтральное: подробности блокировки —
            // подсказка для того, кто её обходит.
            return redirect()->route('home')->with('error', __('auth.account_blocked'));
        }

        Auth::login($user, remember: true);

        // Смена ID сессии после входа — защита от session fixation.
        request()->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Создать или обновить пользователя из Google-профиля.
     *
     * Ищем по google_id, а не по email: email в Google-аккаунте меняется,
     * а `sub` (google_id) — нет. Поиск по email означал бы, что смена
     * почты создаёт второй аккаунт и теряет все объявления.
     */
    private function upsert(\Laravel\Socialite\Contracts\User $g): User
    {
        $user = User::where('google_id', $g->getId())->first();

        if ($user === null) {
            // Тот же email мог войти раньше — например, если аккаунт
            // заводили до появления google_id. Подхватываем, а не плодим.
            $user = User::where('email', $g->getEmail())->first();
        }

        if ($user === null) {
            return User::create([
                'google_id'  => $g->getId(),
                'name'       => $g->getName() ?: __('auth.default_name'),
                'email'      => $g->getEmail(),
                'avatar_url' => $g->getAvatar(),
                'locale'     => app()->getLocale(),
                'status'     => 'active',
            ]);
        }

        // Обновляем только то, что приходит от Google. seller_type, phone
        // и status — наши, Google о них ничего не знает и затирать их нельзя.
        $user->update([
            'google_id'  => $g->getId(),
            'name'       => $g->getName() ?: $user->name,
            'email'      => $g->getEmail(),
            'avatar_url' => $g->getAvatar(),
        ]);

        return $user;
    }
}
