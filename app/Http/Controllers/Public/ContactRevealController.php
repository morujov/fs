<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactReveal;
use App\Models\Listing;
use App\Services\Reveal\ContactRevealLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Раскрытие контакта продавца.
 *
 * ЕДИНСТВЕННОЕ место в проекте, откуда наружу выходит полное значение
 * контакта. Всё остальное — витрина, карточка, любой Blade — отдаёт только
 * то, что вернул Listing::maskedContact(). Если полный контакт появится
 * где-то ещё, вся конструкция (гейт, лимиты, маска) перестаёт существовать,
 * и никто этого не заметит, потому что ничего не сломается.
 *
 * Инвариант №2: полный контакт не попадает в HTML. Ни разу, ни под каким
 * CSS. Рендерить значение и прятать его blur/opacity — вскрывается через
 * Ctrl+U за пять секунд.
 */
class ContactRevealController extends Controller
{
    public function __construct(private readonly ContactRevealLimiter $limiter) {}

    public function __invoke(Request $request, Listing $listing): JsonResponse
    {
        // Контакты только у опубликованных. Черновик, отклонённое или
        // ожидающее модерации объявление контактов не отдаёт: их ещё
        // никто не проверял.
        if ($listing->status !== 'active') {
            return response()->json(['message' => __('reveal.errors.not_available')], 404);
        }

        $user = $request->user();
        $ip = (string) $request->ip();

        // Заблокированный аккаунт не раскрывает контакты — даже те, что уже
        // открывал: блокировка сильнее правила «повтор бесплатно», проверка
        // должна стоять до short-circuit ниже, иначе бан обходится ранее
        // раскрытым объявлением. 403, а не 429: это «нельзя», а не «частишь».
        // Лимитер тоже проверяет isBlocked (он переиспользуем, напр. в
        // предпросмотре админки) — здесь это HTTP-граница, как authorize() в S2.
        if ($user->isBlocked()) {
            return response()->json(['message' => __('reveal.errors.blocked')], 403);
        }

        // Повторное раскрытие того же объявления не тратит лимит и не
        // пишет вторую строку. Человек закрыл вкладку и вернулся — это
        // не новый доступ к данным, он их уже видел. Наказывать за это
        // значит ломать нормальный сценарий ради видимости строгости.
        $existing = ContactReveal::where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->first();

        if ($existing !== null) {
            return response()->json($listing->fullContact());
        }

        $decision = $this->limiter->check($user, $ip);

        if (! $decision->allowed) {
            $this->applySanctions($user, $decision, $ip);

            return response()
                ->json(
                    ['message' => $decision->message()],
                    $decision->blockAccount ? 403 : 429
                )
                ->withHeaders($decision->retryAfter ? ['Retry-After' => $decision->retryAfter] : []);
        }

        DB::transaction(function () use ($user, $listing, $ip, $request) {
            ContactReveal::create([
                'user_id'    => $user->id,
                'listing_id' => $listing->id,
                'ip'         => $ip,
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            $listing->increment('contact_reveals');

            $user->increment('reveal_count_total');
            $user->forceFill(['last_reveal_at' => now()])->save();
        });

        // Много аккаунтов с одного IP — не повод отказывать (за NAT сидит
        // пол-оператора), но повод показать модератору.
        if ($this->limiter->ipLooksShared($ip)) {
            Log::info('[reveal] много аккаунтов с одного IP', [
                'ip'      => $ip,
                'user_id' => $user->id,
            ]);
        }

        return response()->json($listing->fullContact());
    }

    /**
     * Санкции применяем ДО ответа: если отдать 429 и забыть пометить
     * аккаунт, скрейпер будет получать 429 вечно и вечно же оставаться
     * невидимым для модератора.
     */
    private function applySanctions($user, $decision, string $ip): void
    {
        if ($decision->blockAccount) {
            $user->update(['status' => 'blocked']);

            Log::warning('[reveal] аккаунт заблокирован автоматически', [
                'user_id' => $user->id,
                'ip'      => $ip,
                'payload' => $decision->payload,
            ]);

            return;
        }

        if ($decision->flagAccount && $user->status === 'active') {
            $user->update(['status' => 'flagged']);

            Log::warning('[reveal] аккаунт помечен на разбор', [
                'user_id' => $user->id,
                'ip'      => $ip,
                'payload' => $decision->payload,
            ]);
        }
    }
}
