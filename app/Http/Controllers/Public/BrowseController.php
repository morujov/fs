<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Operator;
use App\Models\Province;
use App\Models\Setting;
use App\Services\Search\ListingQuery;
use App\Services\Search\NumberPatternQuery;
use App\Services\Search\PatternTagger;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Витрина. Открыта анониму и Googlebot целиком — инвариант №4.
 *
 * Гейт стоит ровно в одном месте: ContactRevealController. Загейтить
 * просмотр значило бы убить SEO, а SEO — единственный канал трафика,
 * пока нет монетизации и денег на рекламу.
 */
class BrowseController extends Controller
{
    public function __construct(private readonly ListingQuery $query) {}

    public function index(Request $request): View
    {
        $perPage = (int) Setting::get('listing.per_page', 20);

        $listings = $this->query
            ->build($request->query())
            ->paginate($perPage)
            ->withQueryString();

        return view('browse.index', [
            'listings'  => $listings,
            'provinces' => Province::orderBy('name_es')->get(),
            'operators' => Operator::active()->get(),
            'tags'      => PatternTagger::TAGS,

            // Отдаём во вью уже санитизированным: то, что пользователь
            // ввёл, вернётся в поле поиска, и туда не должно попасть
            // ничего, кроме цифр и '?'.
            'pattern'   => NumberPatternQuery::sanitize($request->query('q')),
            'filters'   => $request->query(),
        ]);
    }

    /**
     * Карточка. Route-model binding по slug; из URL не достать чужой
     * статус, потому что здесь же и проверяем.
     */
    public function show(Listing $listing): View
    {
        // Неопубликованное объявление на витрине не существует. 404, а не
        // 403: сообщать, что объявление есть, но скрыто, — лишняя утечка.
        abort_unless($listing->status === 'active', 404);

        $listing->increment('views');

        return view('browse.show', [
            'listing' => $listing->load(['province', 'operator', 'shop']),

            // В HTML уходит ТОЛЬКО маска. Полное значение отдаёт
            // ContactRevealController после проверки сессии и лимитов.
            'contact' => $listing->maskedContact(),
        ]);
    }
}
