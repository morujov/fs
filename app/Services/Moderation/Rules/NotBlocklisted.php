<?php

namespace App\Services\Moderation\Rules;

use App\Models\BlocklistNumber;
use App\Models\Listing;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;
use Illuminate\Support\Facades\Cache;

/**
 * Номер не в блок-листе.
 *
 * Блок-лист — точечные запреты от администратора: номер организации,
 * выставленный шутником; номер из подтверждённой жалобы «это мой номер».
 * Системные правила нумерации живут не здесь, а в numbering_ranges.
 *
 * Список мал (десятки строк) и кэшируется целиком: лишний запрос к БД на
 * каждый прогон не нужен.
 */
final class NotBlocklisted implements ModerationRule
{
    private const CACHE_KEY = 'moderation.blocklist';

    public function name(): string
    {
        return 'blocklist';
    }

    public function check(Listing $listing): RuleResult
    {
        foreach ($this->patterns() as $entry) {
            if ($this->matches($listing->msisdn, $entry['pattern'])) {
                return RuleResult::reject(
                    'moderation.reasons.blocklisted',
                    payload: ['pattern' => $entry['pattern'], 'reason' => $entry['reason']]
                );
            }
        }

        return RuleResult::pass();
    }

    /** Паттерн в том же синтаксисе, что и поиск: цифры и '?'. */
    private function matches(string $msisdn, string $pattern): bool
    {
        $regex = '/^'.strtr(preg_quote($pattern, '/'), ['\?' => '\d']).'/';

        return (bool) preg_match($regex, $msisdn);
    }

    private function patterns(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => BlocklistNumber::query()
            ->where('is_active', true)
            ->get(['msisdn_pattern', 'reason'])
            ->map(fn ($b) => ['pattern' => $b->msisdn_pattern, 'reason' => $b->reason])
            ->all());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
