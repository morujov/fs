<?php

namespace App\Services\Reveal;

use App\Models\ContactReveal;
use App\Models\Setting;
use App\Models\User;

/**
 * Лимиты на раскрытие контактов.
 *
 * ── Зачем это вообще ────────────────────────────────────────────────────
 * Google-аккаунт стоит ноль евро и заводится за минуту. Поэтому OAuth сам
 * по себе скрейпинг НЕ останавливает — он только поднимает цену входа.
 * Останавливают лимиты, и живут они здесь. Если этот класс сломается,
 * вся конструкция — гейт, маскировка, whitelist в поиске — теряет смысл:
 * база контактов выкачивается скриптом с одним аккаунтом.
 *
 * ── Порядок проверок ────────────────────────────────────────────────────
 * От самого тяжёлого к самому лёгкому: блокировка → автоблок → суточный
 * лимит → минутный → IP. Пользователю показываем первую сработавшую
 * причину, и она должна быть самой существенной.
 *
 * ── Чего здесь нет ──────────────────────────────────────────────────────
 * Записи в БД. Лимитер только считает и решает. Пишет вызывающий
 * (ContactRevealController) — иначе «проверить лимит» имело бы побочный
 * эффект, и его нельзя было бы позвать дважды, например для предпросмотра
 * в админке.
 */
final class ContactRevealLimiter
{
    public function check(User $user, string $ip): RevealDecision
    {
        if ($user->isBlocked()) {
            return RevealDecision::deny('reveal.errors.blocked');
        }

        if ($d = $this->autoblock($user)) {
            return $d;
        }

        if ($d = $this->perDayUser($user)) {
            return $d;
        }

        if ($d = $this->perMinuteUser($user)) {
            return $d;
        }

        if ($d = $this->perDayIp($ip)) {
            return $d;
        }

        if ($d = $this->botSignature($user)) {
            return $d;
        }

        // «Много аккаунтов с одного IP» сознательно НЕ проверяется здесь.
        // За одним IP сидит семья, офис, кафе, весь мобильный оператор
        // за NAT. Отказывать по этому признаку — отсекать легитимных
        // покупателей пачками. Это сигнал модератору (ipLooksShared),
        // а не причина отказа.

        return RevealDecision::allow();
    }

    /**
     * Порог, за которым разговор окончен: столько контактов за сутки
     * человек не открывает ни при каком сценарии покупки.
     */
    private function autoblock(User $user): ?RevealDecision
    {
        $max = (int) Setting::get('reveal.autoblock_per_day', 50);
        $count = $this->countUser($user, now()->subDay());

        if ($count < $max) {
            return null;
        }

        return RevealDecision::deny(
            'reveal.errors.blocked',
            blockAccount: true,
            payload: ['reveals_24h' => $count, 'limit' => $max]
        );
    }

    /** Суточный лимит. Дальше — Turnstile, а не отказ навсегда. */
    private function perDayUser(User $user): ?RevealDecision
    {
        $max = (int) Setting::get('reveal.per_day_user', 20);
        $count = $this->countUser($user, now()->subDay());

        if ($count < $max) {
            return null;
        }

        // Считаем, когда освободится место: сутки от самого старого
        // раскрытия в окне. Не «через 24 часа» — это неправда и злит.
        $oldest = ContactReveal::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->oldest('created_at')
            ->value('created_at');

        $retry = $oldest ? max(1, now()->diffInSeconds($oldest->addDay(), false)) : 3600;

        return RevealDecision::deny(
            'reveal.errors.daily_limit',
            ['max' => $max],
            retryAfter: (int) $retry,
            payload: ['reveals_24h' => $count, 'limit' => $max]
        );
    }

    /** Минутный лимит — против скрипта, а не против человека. */
    private function perMinuteUser(User $user): ?RevealDecision
    {
        $max = (int) Setting::get('reveal.per_minute_user', 5);
        $count = $this->countUser($user, now()->subMinute());

        if ($count < $max) {
            return null;
        }

        return RevealDecision::deny(
            'reveal.errors.slow_down',
            retryAfter: 60,
            payload: ['reveals_1m' => $count, 'limit' => $max]
        );
    }

    /**
     * Лимит на IP. Ловит того, кто завёл десять Google-аккаунтов и
     * ходит с одной машины.
     */
    private function perDayIp(string $ip): ?RevealDecision
    {
        $max = (int) Setting::get('reveal.per_day_ip', 40);

        $count = ContactReveal::where('ip', $ip)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($count < $max) {
            return null;
        }

        // Причина нейтральная: подсказывать скрейперу, что сработал
        // именно IP-лимит, значит подсказать сменить IP.
        return RevealDecision::deny(
            'reveal.errors.daily_limit',
            ['max' => (int) Setting::get('reveal.per_day_user', 20)],
            retryAfter: 3600,
            payload: ['ip_reveals_24h' => $count, 'limit' => $max]
        );
    }

    /**
     * Сигнатура бота: раскрытия идут чаще, чем человек успевает читать.
     *
     * Три интервала подряд короче порога. Один короткий интервал — это
     * человек, промахнувшийся мимо кнопки; три подряд — цикл.
     */
    private function botSignature(User $user): ?RevealDecision
    {
        $minMs = (int) Setting::get('reveal.bot_interval_ms', 2000);

        $recent = ContactReveal::where('user_id', $user->id)
            ->latest('created_at')
            ->limit(4)
            ->pluck('created_at');

        if ($recent->count() < 4) {
            return null;
        }

        // abs(): в Carbon 3 diff знаковый, а порядок здесь от новых к старым.
        // Без abs интервалы вышли бы отрицательными и всегда «быстрее порога» —
        // лимитер стал бы блокировать всех подряд.
        $fast = 0;
        for ($i = 0; $i < 3; $i++) {
            $gapMs = abs($recent[$i]->diffInMilliseconds($recent[$i + 1]));
            if ($gapMs < $minMs) {
                $fast++;
            }
        }

        if ($fast < 3) {
            return null;
        }

        return RevealDecision::deny(
            'reveal.errors.slow_down',
            retryAfter: 60,
            flagAccount: true,
            payload: ['signature' => 'fast_interval', 'threshold_ms' => $minMs]
        );
    }

    /**
     * Сколько контактов аккаунт раскрыл с момента $since.
     *
     * Считаем по contact_reveals, а не по денормализованному счётчику
     * в users: тот копит за всё время, а нам нужно окно.
     */
    private function countUser(User $user, \DateTimeInterface $since): int
    {
        return ContactReveal::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * Много ли аккаунтов ходит с этого IP — для флага в админке.
     * Отдельно от check(), потому что это не причина отказа.
     */
    public function ipLooksShared(string $ip): bool
    {
        $max = (int) Setting::get('reveal.accounts_per_ip', 3);

        return ContactReveal::where('ip', $ip)
            ->where('created_at', '>=', now()->subDay())
            ->distinct()
            ->count('user_id') > $max;
    }
}
