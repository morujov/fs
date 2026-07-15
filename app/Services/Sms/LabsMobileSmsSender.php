<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

/**
 * LabsMobile — испанский провайдер, ~0.05 €/SMS, работает без договора.
 *
 * Заготовка: включается, когда дойдём до реальных отправок. Пока боевого
 * аккаунта нет, драйвер остаётся невыбранным (SMS_DRIVER=log).
 */
final class LabsMobileSmsSender implements SmsSenderInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $token,
        private readonly string $from,
    ) {}

    public function send(string $msisdn, string $text): void
    {
        // Испанский номер в международном формате — провайдер требует код страны.
        $to = '34'.$msisdn;

        $response = Http::withBasicAuth($this->username, $this->token)
            ->asJson()
            ->timeout(15)
            ->post('https://api.labsmobile.com/json/send', [
                'message'   => $text,
                'tpoa'      => $this->from,
                'recipient' => [['msisdn' => $to]],
            ]);

        if ($response->failed()) {
            throw new SmsException(
                'LabsMobile вернул '.$response->status().': '.$response->body()
            );
        }

        // У LabsMobile HTTP 200 не означает успех — код ответа внутри тела.
        $code = $response->json('code');

        if ((string) $code !== '0') {
            throw new SmsException(
                'LabsMobile отказал, code='.$code.': '.$response->json('message', '')
            );
        }
    }
}
