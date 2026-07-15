<?php

namespace App\Services\Moderation;

/**
 * Итог прогона: что решили и почему.
 */
final class Verdict
{
    /**
     * @param  array<string, RuleResult>  $results  имя правила => результат
     */
    public function __construct(
        public readonly string $status,
        public readonly int $score,
        public readonly array $results,
        public readonly ?string $reason = null,
    ) {}

    /** @return array<string, RuleResult> */
    public function rejects(): array
    {
        return array_filter($this->results, fn (RuleResult $r) => $r->outcome === RuleOutcome::Reject);
    }

    /** @return array<string, RuleResult> */
    public function flags(): array
    {
        return array_filter($this->results, fn (RuleResult $r) => $r->outcome === RuleOutcome::Flag);
    }

    /** @return array<string, RuleResult> */
    public function holds(): array
    {
        return array_filter($this->results, fn (RuleResult $r) => $r->outcome === RuleOutcome::Hold);
    }

    public function published(): bool
    {
        return $this->status === 'active';
    }
}
