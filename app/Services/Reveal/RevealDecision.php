<?php

namespace App\Services\Reveal;

/**
 * Решение лимитера: пускать или нет, и если нет — почему.
 */
final class RevealDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reasonKey = null,
        public readonly array $reasonParams = [],
        /** Через сколько секунд имеет смысл повторить. null = не имеет. */
        public readonly ?int $retryAfter = null,
        /** Аккаунт нужно пометить для ручного разбора. */
        public readonly bool $flagAccount = false,
        /** Аккаунт нужно заблокировать. */
        public readonly bool $blockAccount = false,
        public readonly array $payload = [],
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(
        string $reasonKey,
        array $params = [],
        ?int $retryAfter = null,
        bool $flagAccount = false,
        bool $blockAccount = false,
        array $payload = [],
    ): self {
        return new self(false, $reasonKey, $params, $retryAfter, $flagAccount, $blockAccount, $payload);
    }

    public function message(): ?string
    {
        return $this->reasonKey ? __($this->reasonKey, $this->reasonParams) : null;
    }
}
