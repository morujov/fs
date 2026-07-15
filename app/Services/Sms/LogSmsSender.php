<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Драйвер по умолчанию: пишет в лог вместо отправки.
 *
 * Каждая реальная SMS стоит ~0.05 €. Цикл отладки формы подачи — это
 * десятки отправок в день, и боевой драйвер, включённый «на минутку»,
 * незаметно съедает бюджет. Поэтому SMS_DRIVER=log по умолчанию, и
 * переключается сознательно.
 *
 * Код пишется в лог целиком: это dev-окружение, и разработчику нужно
 * его прочитать. В проде этот драйвер использоваться не должен — за этим
 * следит проверка в SmsManager.
 */
final class LogSmsSender implements SmsSenderInterface
{
    public function send(string $msisdn, string $text): void
    {
        Log::info('[SMS:log] SMS не отправлена — драйвер log', [
            'to'   => $msisdn,
            'text' => $text,
        ]);
    }
}
