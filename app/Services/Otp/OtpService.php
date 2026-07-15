<?php

namespace App\Services\Otp;

use App\Models\Listing;
use App\Models\OtpCode;
use App\Models\Setting;
use App\Services\Sms\SmsSenderInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Подтверждение владения ПРОДАВАЕМЫМ номером.
 *
 * ── Зачем, если есть Google-вход ────────────────────────────────────────
 * Google подтверждает личность продавца. Он ничего не говорит о том, что
 * продаваемый номер принадлежит этому человеку. Без OTP любой залогиненный
 * пользователь выставит чужой номер — прямой ущерб третьему лицу и
 * репутационная смерть площадки на второй месяц. Это разные проверки,
 * одна другую не заменяет (блюпринт, пробел №1).
 *
 * ── Что защищаем ───────────────────────────────────────────────────────
 *  - Код хранится хэшем: утечка таблицы не должна давать возможности
 *    подтвердить чужие объявления.
 *  - Лимит попыток: шестизначный код перебирается за миллион запросов,
 *    а без лимита это минуты.
 *  - Лимит отправок: кнопка «отправить ещё раз» без счётчика — это способ
 *    выставить нам счёт за SMS.
 *  - Пороги в settings, а не в коде: правятся из админки без деплоя.
 */
final class OtpService
{
    public function __construct(
        private readonly SmsSenderInterface $sms,
    ) {}

    /**
     * Выпустить и отправить код.
     *
     * @throws OtpException если исчерпан лимит отправок
     */
    public function issue(Listing $listing): OtpCode
    {
        $maxSends = (int) Setting::get('moderation.otp_max_sends', 3);

        $sent = OtpCode::where('listing_id', $listing->id)
            ->where('msisdn', $listing->msisdn)
            ->sum('sends');

        if ($sent >= $maxSends) {
            throw OtpException::tooManySends($maxSends);
        }

        // Гасим предыдущие коды: иначе старый код останется валидным,
        // и «перевыпустить» превратится в «расширить окно атаки».
        OtpCode::where('listing_id', $listing->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode();
        $ttl  = (int) Setting::get('moderation.otp_ttl_minutes', 10);

        $otp = OtpCode::create([
            'listing_id' => $listing->id,
            'msisdn'     => $listing->msisdn,
            'code_hash'  => Hash::make($code),
            'attempts'   => 0,
            'sends'      => 1,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->sms->send(
            $listing->msisdn,
            __('otp.sms_text', ['code' => $code, 'minutes' => $ttl])
        );

        return $otp;
    }

    /**
     * Проверить код и, если верен, отметить номер подтверждённым.
     *
     * Возвращает true при успехе. Ошибки — исключениями, потому что
     * вызывающему нужно различать «неверный код» и «код протух»:
     * это разные сообщения пользователю.
     *
     * @throws OtpException
     */
    public function verify(Listing $listing, string $code): bool
    {
        $otp = OtpCode::where('listing_id', $listing->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($otp === null) {
            throw OtpException::notFound();
        }

        if ($otp->isExpired()) {
            throw OtpException::expired();
        }

        $maxAttempts = (int) Setting::get('moderation.otp_max_attempts', 5);

        if ($otp->attempts >= $maxAttempts) {
            throw OtpException::tooManyAttempts($maxAttempts);
        }

        // Инкремент ДО сравнения: иначе прерванный запрос обнулит счётчик
        // и лимит попыток перестанет что-либо ограничивать.
        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            throw OtpException::invalid($maxAttempts - $otp->attempts);
        }

        $otp->update(['consumed_at' => now()]);
        $listing->update(['phone_verified_at' => now()]);

        return true;
    }

    /**
     * Шестизначный код.
     *
     * random_int, а не rand/mt_rand: последние предсказуемы, а предсказуемый
     * OTP — это отсутствие OTP.
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
