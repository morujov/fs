<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Setting;
use App\Services\Moderation\ModerationPipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Что продавец делает с объявлением после публикации.
 *
 * Блокеры №5 и №6 блюпринта. Без «продано» доска зарастает проданными
 * номерами: покупатель звонит по десяти, все неактуальны, и он не
 * возвращается. Без продления объявление умирает молча, и продавец узнаёт
 * об этом, только когда решит проверить.
 */
class ListingLifecycleController extends Controller
{
    public function __construct(private readonly ModerationPipeline $pipeline) {}

    /**
     * Отметить проданным.
     *
     * Освобождает номер: active_msisdn станет NULL, и покупатель сможет
     * выставить его от своего имени, когда портация завершится. Ради этого
     * генерируемая колонка и делалась.
     */
    public function markSold(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwner($request, $listing);

        abort_unless(in_array($listing->status, ['active', 'pending'], true), 404);

        $listing->update([
            'status'  => 'sold',
            'sold_at' => now(),
        ]);

        return back()->with('status', __('listing.marked_sold'));
    }

    /** Снять с публикации, не продавая. */
    public function archive(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwner($request, $listing);

        abort_unless(in_array($listing->status, ['active', 'pending'], true), 404);

        $listing->update(['status' => 'archived']);

        return back()->with('status', __('listing.archived'));
    }

    /**
     * Продлить.
     *
     * ── Почему подписанная ссылка, а не обычный роут ────────────────────
     * Продление приходит письмом «объявление скоро истечёт». Заставлять
     * человека логиниться и искать объявление в кабинете ради одной кнопки
     * значит гарантировать, что он этого не сделает — и объявление умрёт
     * не потому, что неактуально, а потому что мы сделали кнопку неудобной.
     *
     * Подпись Laravel привязана к URL и сроку, подделать её нельзя.
     * Поэтому роут доступен и гостю: владение доказывает подпись, а не сессия.
     */
    public function renew(Request $request, Listing $listing): RedirectResponse
    {
        // Из письма — по подписи. Из кабинета — по сессии. Хватает одного.
        if (! $request->hasValidSignature()) {
            $this->authorizeOwner($request, $listing);
        }

        abort_unless(
            in_array($listing->status, ['active', 'expired'], true),
            404
        );

        // Номер мог занять кто-то другой, пока объявление лежало истёкшим.
        // Тогда продлевать нечего: активным он быть не может.
        $taken = Listing::active()
            ->where('msisdn', $listing->msisdn)
            ->where('id', '!=', $listing->id)
            ->exists();

        if ($taken) {
            return redirect()
                ->route('seller.listings.index')
                ->with('error', __('listing.renew_number_taken'));
        }

        $ttl = (int) Setting::get('listing.ttl_days', 60);

        $listing->forceFill([
            'expires_at'         => now()->addDays($ttl),
            'expiry_notified_at' => null,
            'renewals_count'     => $listing->renewals_count + 1,
        ])->save();

        // Прогоняем конвейер заново, а не просто ставим active. За 60 дней
        // могло измениться что угодно: номер попал в блок-лист, диапазон
        // закрыли, магазин потерял верификацию. Продление — это повторная
        // публикация, и проверяться она обязана как публикация.
        if ($listing->status === 'expired') {
            $this->pipeline->run($listing->fresh());
        }

        return redirect()
            ->route('seller.listings.index')
            ->with('status', __('listing.renewed', ['days' => $ttl]));
    }

    private function authorizeOwner(Request $request, Listing $listing): void
    {
        abort_if($request->user() === null, 403);
        abort_if($listing->user_id !== $request->user()->id, 404);
    }
}
