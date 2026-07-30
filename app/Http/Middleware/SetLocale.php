<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Применяет локаль к запросу.
 *
 * Приоритет: явный выбор в сессии → сохранённый выбор вошедшего → дефолт
 * приложения (config app.locale, у нас es). Каждый шаг проверяется по
 * списку config numeros.locales.active; невалидное отбрасывается —
 * fail-safe: кривой код локали в сессии или в профиле не роняет страницу.
 *
 * ── Почему НЕ Accept-Language ────────────────────────────────────────────
 * Авто-детект по заголовку браузера здесь сознательно НЕ делаем. Сайт
 * испаноязычный, и для анонима и для Googlebot язык по умолчанию обязан
 * быть стабильно испанским — это канон и весь SEO (инвариант №4). Если бы
 * язык менялся от Accept-Language, краулер с англоязычной локалью получал
 * бы английскую страницу по тому же URL, и канон размывался бы. Английский —
 * явный опт-ин через переключатель, а не догадка по заголовку.
 *
 * Список активных локалей — в config (иммутабельно), строки — в lang/.
 * Новый язык = папка lang/ + код в active, без правки этого класса.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $active = config('numeros.locales.active', []);

        $locale = $this->pick(session('locale'), $active)
            ?? $this->pick($request->user()?->locale, $active)
            ?? config('app.locale');

        app()->setLocale($locale);

        return $next($request);
    }

    /** Вернуть локаль, если она строка и есть в списке активных, иначе null. */
    private function pick(mixed $locale, array $active): ?string
    {
        return is_string($locale) && in_array($locale, $active, true) ? $locale : null;
    }
}
