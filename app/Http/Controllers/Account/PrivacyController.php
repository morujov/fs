<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Gdpr\AccountEraser;
use App\Services\Gdpr\DataExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Права субъекта данных — GDPR, ст. 15 и 17.
 *
 * Это не «фича», а обязанность. И она должна быть выполнима без переписки
 * с поддержкой: право, которым можно воспользоваться, только написав письмо
 * и подождав месяц, — это право на бумаге.
 */
class PrivacyController extends Controller
{
    public function __construct(
        private readonly AccountEraser $eraser,
        private readonly DataExporter $exporter,
    ) {}

    /** Страница «мои данные»: что храним, что можно сделать. */
    public function show(Request $request): View
    {
        return view('account.privacy', [
            'preview' => $this->eraser->preview($request->user()),
        ]);
    }

    /** Ст. 15 и 20 — выгрузка. */
    public function export(Request $request): JsonResponse
    {
        $data = $this->exporter->export($request->user());

        return response()
            ->json($data, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="mis-datos-'.now()->format('Y-m-d').'.json"',
            ]);
    }

    /**
     * Ст. 17 — удаление.
     *
     * Подтверждение словом, а не галочкой: удаление необратимо, и человек
     * должен успеть понять, что делает. Галочку ставят не глядя.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'in:BORRAR,DELETE'],
        ], [
            'confirm.in' => __('gdpr.confirm_error'),
        ]);

        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->eraser->erase($user);

        return redirect()->route('home')->with('status', __('gdpr.erased'));
    }
}
