<?php

namespace App\Services\Moderation;

/**
 * Результат одного правила: исход, вес и объяснение.
 */
final class RuleResult
{
    private function __construct(
        public readonly RuleOutcome $outcome,
        public readonly int $score = 0,
        public readonly ?string $reasonKey = null,
        public readonly array $reasonParams = [],
        public readonly array $payload = [],
    ) {}

    public static function pass(array $payload = []): self
    {
        return new self(RuleOutcome::Pass, payload: $payload);
    }

    /**
     * @param  int  $score  вес подозрения. Порог ручной очереди — в settings
     *                      (moderation.manual_threshold), по умолчанию 3.
     *                      Правило с весом 3 в одиночку отправляет на разбор.
     */
    public static function flag(string $reasonKey, int $score = 1, array $params = [], array $payload = []): self
    {
        return new self(RuleOutcome::Flag, $score, $reasonKey, $params, $payload);
    }

    public static function reject(string $reasonKey, array $params = [], array $payload = []): self
    {
        return new self(RuleOutcome::Reject, 0, $reasonKey, $params, $payload);
    }

    public static function hold(string $reasonKey, array $payload = []): self
    {
        return new self(RuleOutcome::Hold, 0, $reasonKey, payload: $payload);
    }

    /** Сообщение продавцу на его языке. */
    public function message(): ?string
    {
        return $this->reasonKey ? __($this->reasonKey, $this->reasonParams) : null;
    }
}
