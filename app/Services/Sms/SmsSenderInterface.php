<?php

namespace App\Services\Sms;

/**
 * Отправка SMS. Реализации: LogSmsSender (dev), LabsMobileSmsSender (прод).
 *
 * Интерфейс намеренно узкий: единственный сценарий отправки в проекте —
 * OTP на продаваемый номер. Маркетинговых рассылок нет и не планируется,
 * поэтому ни шаблонов, ни очередей, ни батчей здесь быть не должно.
 */
interface SmsSenderInterface
{
    /**
     * @param  string  $msisdn  9 цифр, без +34
     * @param  string  $text    готовый текст на языке получателя
     *
     * @throws SmsException при ошибке провайдера
     */
    public function send(string $msisdn, string $text): void;
}
