<?php

namespace App\Services\Gdpr;

use App\Models\ContactReveal;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Report;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Право на удаление — GDPR, ст. 17.
 *
 * ── Почему не просто DELETE FROM users ──────────────────────────────────
 * Право на забвение не абсолютно. Ст. 17(3) прямо перечисляет исключения,
 * и два из них наши:
 *
 *  1. Данные, нужные для установления и защиты правовых требований.
 *     Если по объявлению шла жалоба «это мой номер» — стереть всё значит
 *     уничтожить доказательства в пользу пострадавшего третьего лица.
 *
 *  2. Данные, нужные для соблюдения обязанностей.
 *     Аудит действий администратора — подотчётность, и она не про удобство
 *     администратора.
 *
 * Поэтому: аккаунт стирается, следы обезличиваются, история сделок
 * остаётся без личности.
 *
 * ── Что важнее формальности ─────────────────────────────────────────────
 * Человеку нужно честно сказать, что останется и почему, ДО того как он
 * нажмёт кнопку. «Мы всё удалим» и потом «ну кроме вот этого» — хуже,
 * чем сразу объяснить.
 */
final class AccountEraser
{
    /**
     * @return array<string, int> что и сколько тронули — для отчёта человеку
     */
    public function erase(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $stats = [];

            // Активные объявления снимаем: продавца больше нет, отвечать
            // на звонки некому. Оставить их значило бы обмануть покупателей.
            $stats['listings_withdrawn'] = Listing::where('user_id', $user->id)
                ->whereIn('status', ['active', 'pending', 'draft'])
                ->update(['status' => 'archived']);

            // Контакты во всех его объявлениях — стереть. Сами объявления
            // остаются как рыночная история, но без личности.
            $stats['listings_anonymized'] = Listing::withTrashed()
                ->where('user_id', $user->id)
                ->toBase()
                ->update([
                    'contact_phone'    => '',
                    'contact_email'    => null,
                    'contact_name'     => '',
                    'contact_whatsapp' => false,
                    'user_id'          => null,
                ]);

            // Раскрытия: строки нужны статистике объявлений (сколько раз
            // смотрели контакт), но привязка к человеку — нет.
            $stats['reveals_anonymized'] = ContactReveal::where('user_id', $user->id)
                ->toBase()
                ->update(['user_id' => null, 'ip' => null, 'user_agent' => null]);

            // Жалобы: отвязываем от аккаунта, но НЕ удаляем. Ст. 17(3)(e) —
            // жалоба может быть доказательством в чужом споре, и стирать её
            // по просьбе того, на кого жаловались, было бы прямым вредом.
            $stats['reports_detached'] = Report::where('user_id', $user->id)
                ->toBase()
                ->update(['user_id' => null, 'reporter_email' => null]);

            // Личные вещи — под нож без остатка. Никакой ценности для
            // третьих лиц в них нет.
            $stats['saved_searches_deleted'] = SavedSearch::where('user_id', $user->id)->delete();
            $stats['favorites_deleted'] = Favorite::where('user_id', $user->id)->delete();

            // Сам аккаунт. Не soft-delete: «удалить» должно означать удалить.
            //
            // google_id забиваем случайным до удаления: если у таблицы
            // останется UNIQUE-конфликт при повторном входе того же Google,
            // человек не сможет завести аккаунт заново. Право на удаление
            // не должно превращаться в пожизненный бан.
            $user->forceFill([
                'google_id'  => 'erased-'.Str::random(32),
                'email'      => 'erased-'.Str::random(32).'@invalid',
                'name'       => '',
                'avatar_url' => null,
                'phone'      => null,
            ])->save();

            $user->delete();

            $stats['account_deleted'] = 1;

            return $stats;
        });
    }

    /**
     * Что человек потеряет — показать ДО кнопки.
     *
     * @return array<string, int>
     */
    public function preview(User $user): array
    {
        return [
            'active_listings' => Listing::where('user_id', $user->id)->active()->count(),
            'total_listings'  => Listing::where('user_id', $user->id)->count(),
            'reveals'         => ContactReveal::where('user_id', $user->id)->count(),
            'saved_searches'  => SavedSearch::where('user_id', $user->id)->count(),
            'favorites'       => Favorite::where('user_id', $user->id)->count(),
        ];
    }
}
