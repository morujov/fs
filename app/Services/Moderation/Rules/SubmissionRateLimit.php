<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Models\Setting;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Продавец не заваливает площадку объявлениями.
 *
 * Порог низкий (3 в сутки) намеренно: обычный человек продаёт один-два
 * номера, а не десять. Десять за день — либо магазин (тогда пусть
 * регистрируется магазином), либо бот.
 *
 * Исход — flag, а не reject. Массовая подача сама по себе не преступление,
 * и отклонять её автоматически значило бы отсекать легитимные магазины,
 * которые ещё не прошли верификацию. Пусть модератор посмотрит.
 *
 * Считаем по аккаунту. Лимита по IP здесь нет намеренно: IP объявления мы
 * не храним (GDPR — данные, которые не нужны, лучше не собирать), а
 * IP-лимит на подачу естественнее применить на уровне HTTP-мидлвары.
 */
final class SubmissionRateLimit implements ModerationRule
{
    public function name(): string
    {
        return 'rate_limit';
    }

    public function check(Listing $listing): RuleResult
    {
        $max = (int) Setting::get('listing.per_day_user', 3);

        $count = Listing::where('user_id', $listing->user_id)
            ->where('id', '!=', $listing->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($count < $max) {
            return RuleResult::pass(['submitted_24h' => $count]);
        }

        return RuleResult::flag(
            'moderation.reasons.rate_limit',
            score: 2,
            params: ['max' => $max],
            payload: ['submitted_24h' => $count, 'limit' => $max]
        );
    }
}
