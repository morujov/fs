<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListingRequest;
use App\Models\Listing;
use App\Models\Operator;
use App\Models\Province;
use App\Models\Setting;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use App\Services\Search\PatternTagger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Кабинет продавца: подача и список своих объявлений.
 *
 * Объявление доходит только до `pending`. Публикует его конвейер модерации
 * (S3) — и только после того, как OTP подтвердит владение номером.
 */
class ListingController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function index(Request $request): View
    {
        $listings = $request->user()->listings()
            ->with(['province', 'operator'])
            ->latest()
            ->paginate(20);

        return view('seller.listings.index', compact('listings'));
    }

    public function create(): View
    {
        return view('seller.listings.create', [
            'provinces' => Province::orderBy('name_es')->get(),
            'operators' => Operator::active()->get(),
            'priceMin'  => (int) Setting::get('listing.price_min', 1),
            'priceMax'  => (int) Setting::get('listing.price_max', 50000),
        ]);
    }

    public function store(StoreListingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Антидубль по active_msisdn гарантирует БД, но пользователю лучше
        // объяснить до вставки, чем показать SQL-ошибку.
        $taken = Listing::active()->where('msisdn', $data['msisdn'])->exists();

        if ($taken) {
            return back()
                ->withInput()
                ->withErrors(['msisdn' => __('listing.validation.msisdn_already_listed')]);
        }

        $listing = DB::transaction(function () use ($user, $data) {
            // Тип продавца выясняется при первой подаче, а не при входе:
            // на входе мы не знаем, покупатель это или продавец.
            if ($user->seller_type === null) {
                $user->update(['seller_type' => $data['seller_type']]);
            }

            return Listing::create([
                'user_id' => $user->id,
                'shop_id' => $user->isVerifiedShop() ? $user->shop->id : null,

                'msisdn'        => $data['msisdn'],
                'price'         => $data['is_negotiable'] ?? false ? null : $data['price'],
                'is_negotiable' => $data['is_negotiable'] ?? false,

                'operator_id'      => $data['operator_id'],
                'line_type'        => $data['line_type'],
                'has_permanency'   => $data['has_permanency'] ?? false,
                'permanency_until' => $data['has_permanency'] ?? false ? $data['permanency_until'] : null,
                'condition'        => $data['condition'],

                'pattern_tags' => PatternTagger::tag($data['msisdn']),

                'province_id' => $data['province_id'],
                'city'        => $data['city'] ?? null,

                'description'      => $data['description'] ?? null,
                'description_lang' => app()->getLocale(),

                'contact_name'     => $data['contact_name'],
                'contact_phone'    => $data['contact_phone'],
                'contact_email'    => $data['contact_email'] ?? null,
                'contact_whatsapp' => $data['contact_whatsapp'] ?? false,

                // pending, не active. Публикует конвейер модерации (S3),
                // и только после OTP.
                'status' => 'pending',
                'slug'   => $this->slug($data['msisdn']),
            ]);
        });

        // Если OTP выключен фича-флагом (только dev) — сразу к модерации.
        if (! Setting::get('features.otp_enabled', true)) {
            $listing->update(['phone_verified_at' => now()]);

            return redirect()
                ->route('seller.listings.index')
                ->with('status', __('listing.submitted_without_otp'));
        }

        try {
            $this->otp->issue($listing);
        } catch (OtpException $e) {
            return redirect()
                ->route('seller.listings.otp.show', $listing)
                ->with('error', $e->userMessage());
        }

        return redirect()->route('seller.listings.otp.show', $listing);
    }

    /**
     * Slug должен быть уникален, а номер в нём — не случайный: он и есть
     * то, что ищут в Google. Суффикс нужен, потому что один номер может
     * пройти через несколько объявлений за свою жизнь.
     */
    private function slug(string $msisdn): string
    {
        do {
            $slug = $msisdn.'-'.Str::lower(Str::random(6));
        } while (Listing::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }
}
