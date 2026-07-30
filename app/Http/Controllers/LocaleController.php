<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Переключение языка интерфейса.
 *
 * Выбор кладём в сессию (работает для анонима) и, если человек вошёл, —
 * в профиль: тогда язык переживёт протухание сессии и смену устройства.
 * Применяет выбор уже SetLocale на следующем запросе.
 */
class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $active = config('numeros.locales.active', []);

        // Неизвестную локаль молча игнорируем (fail-safe): кто-то подставил
        // в URL произвольный код — не язык меняем, а просто не падаем.
        if (in_array($locale, $active, true)) {
            session(['locale' => $locale]);

            $user = $request->user();
            if ($user !== null && $user->locale !== $locale) {
                $user->update(['locale' => $locale]);
            }
        }

        // back(): возвращаемся на ту же страницу, откуда переключили.
        // Fallback на главную, если реферера нет.
        return redirect()->back(fallback: route('home'));
    }
}
