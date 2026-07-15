<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Report;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Жалоба на объявление — denunciar anuncio.
 *
 * ── Почему без входа ────────────────────────────────────────────────────
 * Самая важная жалоба звучит «это мой номер, я его не продаю». Её оставляет
 * человек, который на нашу площадку не заходил, аккаунта не имеет и иметь
 * не хочет — он вообще узнал о нас из чужого звонка. Потребовать от него
 * Google-вход значит не узнать о чужом номере никогда.
 *
 * Это единственное исключение из гейта, и оно правильное: здесь человек
 * данные ОТДАЁТ, а не забирает. Скрейпить тут нечего.
 *
 * ── Блокер №8 блюпринта ─────────────────────────────────────────────────
 * Обязательный элемент доски объявлений и наша юридическая защита: при
 * претензии мы обязаны показать, что механизм реагирования существует
 * и что по жалобе принято решение.
 */
class ReportController extends Controller
{
    public function store(Request $request, Listing $listing): RedirectResponse
    {
        // На неопубликованное жаловаться не на что — его никто не видит.
        abort_unless($listing->status === 'active', 404);

        $data = $request->validate([
            'reason'  => ['required', Rule::in(['not_mine', 'fraud', 'wrong_info', 'spam', 'sold', 'other'])],
            'comment' => ['nullable', 'string', 'max:1000'],
            'reporter_email' => ['nullable', 'email:rfc', 'max:190'],
        ], [], __('report.attributes'));

        try {
            Report::create([
                'listing_id'     => $listing->id,
                'user_id'        => $request->user()?->id,
                'reporter_ip'    => (string) $request->ip(),
                'reporter_email' => $data['reporter_email'] ?? null,
                'reason'         => $data['reason'],
                'comment'        => $data['comment'] ?? null,
                'status'         => 'open',
            ]);
        } catch (QueryException $e) {
            // UNIQUE(listing_id, reporter_ip) — антифлуд. Повторная жалоба
            // с того же IP не ошибка пользователя: он мог не заметить, что
            // первая ушла. Благодарим и молчим, вместо того чтобы показывать
            // SQL-ошибку за попытку нам помочь.
            if (! $this->isDuplicate($e)) {
                throw $e;
            }
        }

        return back()->with('status', __('report.thanks'));
    }

    private function isDuplicate(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1062], true);
    }
}
