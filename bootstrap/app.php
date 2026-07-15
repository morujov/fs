<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Вход только через Google, страницы `login` нет. Неаутентифицированного
        // гостя на защищённом маршруте отправляем на инициатор Google-входа;
        // исходный URL фреймворк сам кладёт в `url.intended` (redirect()->guest),
        // и callback вернёт туда через redirect()->intended().
        $middleware->redirectGuestsTo(fn () => route('auth.google.redirect'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
