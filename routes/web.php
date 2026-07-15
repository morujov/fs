<?php

use App\Http\Controllers\Auth\GoogleAuthController;
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

Route::get('/', fn () => view('welcome'))->name('home');

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
