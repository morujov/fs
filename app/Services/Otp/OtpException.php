<?php

namespace App\Services\Otp;

use RuntimeException;

/**
 * Ошибки OTP. Каждая несёт готовый ключ перевода: сообщения показываются
 * продавцу, а инвариант №7 запрещает пользовательские строки в коде.
 */
class OtpException extends RuntimeException
{
    public function __construct(
        public readonly string $translationKey,
        public readonly array $replacements = [],
    ) {
        parent::__construct($translationKey);
    }

    public function userMessage(): string
    {
        return __($this->translationKey, $this->replacements);
    }

    public static function notFound(): self
    {
        return new self('otp.errors.not_found');
    }

    public static function expired(): self
    {
        return new self('otp.errors.expired');
    }

    public static function invalid(int $left): self
    {
        return new self('otp.errors.invalid', ['left' => max($left, 0)]);
    }

    public static function tooManyAttempts(int $max): self
    {
        return new self('otp.errors.too_many_attempts', ['max' => $max]);
    }

    public static function tooManySends(int $max): self
    {
        return new self('otp.errors.too_many_sends', ['max' => $max]);
    }
}
