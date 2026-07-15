<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Public\BrowseController;
use App\Http\Controllers\Public\ContactRevealController;
use App\Http\Controllers\Seller\ListingController;
use App\Http\Controllers\Seller\OtpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Публичная часть
|--------------------------------------------------------------------------
| Инвариант №4: поиск, фильтры, листинг и карточка открыты анониму и
| Googlebot. Гейт стоит только на раскрытии контакта (S4).
| Загейтить просмотр = убить SEO = убить проект: органика — единственный
| канал трафика, пока нет монетизации.
*/

Route::get('/', [BrowseController::class, 'index'])->name('home');
Route::get('/numero/{listing}', [BrowseController::class, 'show'])->name('listings.show');

/*
|--------------------------------------------------------------------------
| Раскрытие контакта — единственный гейт
|--------------------------------------------------------------------------
| Полное значение контакта выходит наружу ТОЛЬКО отсюда и только после
| проверки сессии и лимитов. Всё остальное отдаёт маску.
|
| throttle поверх ContactRevealLimiter намеренно: HTTP-троттл дешёвый и
| отсекает совсем грубый перебор, не доходя до БД. Лимитер — вторая линия,
| он умнее и умеет блокировать аккаунт, но стоит запросов.
*/

Route::post('/api/listings/{listing}/contact', ContactRevealController::class)
    ->middleware(['auth', 'throttle:30,1'])
    ->name('listings.contact');

/*
|--------------------------------------------------------------------------
| Вход — только Google
|--------------------------------------------------------------------------
| Паролей нет: ни формы регистрации, ни восстановления.
*/

Route::controller(GoogleAuthController::class)->group(function () {
    Route::get('/auth/google/redirect', 'redirect')->name('auth.google.redirect');
    Route::get('/auth/google/callback', 'callback')->name('auth.google.callback');
    Route::post('/logout', 'logout')->middleware('auth')->name('logout');
});

/*
|--------------------------------------------------------------------------
| Кабинет продавца
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('mis-anuncios')->name('seller.')->group(function () {
    Route::get('/', [ListingController::class, 'index'])->name('listings.index');
    Route::get('/nuevo', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/', [ListingController::class, 'store'])->name('listings.store');

    // OTP: подтверждение владения продаваемым номером.
    // throttle защищает от перебора шестизначного кода на уровне HTTP —
    // лимит попыток в OtpService это второй рубеж, а не единственный.
    Route::get('/{listing}/verificar', [OtpController::class, 'show'])->name('listings.otp.show');
    Route::post('/{listing}/verificar', [OtpController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('listings.otp.verify');
    Route::post('/{listing}/reenviar', [OtpController::class, 'resend'])
        ->middleware('throttle:3,10')
        ->name('listings.otp.resend');
});
