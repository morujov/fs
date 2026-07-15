<?php

namespace App\Providers;

use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsSenderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsManager::class);

        // Драйвер выбирается менеджером по конфигу. Потребители (OtpService)
        // зависят от интерфейса, а не от менеджера: так отправку можно
        // подменить в тесте одной строкой, не изображая менеджер.
        $this->app->bind(SmsSenderInterface::class, fn ($app) => $app->make(SmsManager::class)->driver());
    }

    public function boot(): void
    {
        //
    }
}
