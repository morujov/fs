<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ввод OTP-кода, пришедшего на продаваемый номер.
 *
 * Пока код не введён, объявление висит в `pending` и на витрину не попадает,
 * даже если конвейер модерации не нашёл к нему претензий.
 */
class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(Request $request, Listing $listing): View|RedirectResponse
    {
        $this->authorizeOwner($request, $listing);

        if ($listing->phone_verified_at !== null) {
            return redirect()->route('seller.listings.index');
        }

        return view('seller.listings.otp', [
            'listing' => $listing,
            // Показываем замаскированным: если продавец ошибся цифрой,
            // он увидит это здесь, а не после десяти неудачных попыток.
            'masked'  => $listing->formattedMsisdn(),
        ]);
    }

    public function verify(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwner($request, $listing);

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [], __('otp.attributes'));

        try {
            $this->otp->verify($listing, $validated['code']);
        } catch (OtpException $e) {
            return back()->withErrors(['code' => $e->userMessage()]);
        }

        return redirect()
            ->route('seller.listings.index')
            ->with('status', __('otp.verified'));
    }

    public function resend(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorizeOwner($request, $listing);

        try {
            $this->otp->issue($listing);
        } catch (OtpException $e) {
            return back()->withErrors(['code' => $e->userMessage()]);
        }

        return back()->with('status', __('otp.resent'));
    }

    /**
     * Объявление принадлежит этому пользователю.
     *
     * Без этой проверки любой залогиненный подтвердил бы чужое объявление,
     * зная его id — и вся защита от выставления чужих номеров испарилась бы.
     */
    private function authorizeOwner(Request $request, Listing $listing): void
    {
        if ($listing->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
