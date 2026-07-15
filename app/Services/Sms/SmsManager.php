<?php

namespace App\Services\Sms;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Выбор драйвера SMS по конфигу.
 *
 * Отдельный класс, а не match в сервис-провайдере, ради одной проверки:
 * драйвер log не должен молча оказаться в проде. Иначе OTP «работает»,
 * коды никуда не уходят, продавцы не могут подтвердить номера, и никто
 * не понимает почему — потому что ошибок нет, всё зелёное.
 */
final class SmsManager
{
    public function __construct(private readonly Application $app) {}

    public function driver(?string $name = null): SmsSenderInterface
    {
        $name ??= config('sms.driver');

        if ($name === 'log' && $this->app->environment('production')) {
            throw new InvalidArgumentException(
                'SMS_DRIVER=log в продакшене: OTP-коды никуда не уйдут, и '
                .'продавцы не смогут подтвердить владение номерами. '
                .'Укажите боевой драйвер или выключите features.otp_enabled сознательно.'
            );
        }

        return match ($name) {
            'log' => new LogSmsSender,
            'labsmobile' => new LabsMobileSmsSender(
                (string) config('sms.labsmobile.username'),
                (string) config('sms.labsmobile.token'),
                (string) config('sms.from'),
            ),
            default => throw new InvalidArgumentException("Неизвестный SMS-драйвер: {$name}"),
        };
    }
}
