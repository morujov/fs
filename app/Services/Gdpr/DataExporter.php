<?php

namespace App\Services\Gdpr;

use App\Models\User;

/**
 * Право на доступ и переносимость — GDPR, ст. 15 и 20.
 *
 * Человек имеет право знать, что о нём хранится, и получить это в
 * машиночитаемом виде. JSON подходит: ст. 20 требует «structured, commonly
 * used and machine-readable», а не красивый PDF.
 *
 * Отдаём ровно то, что есть, без прикрас. Если в выгрузке человек увидит
 * список из 40 IP-адресов и удивится — значит, мы плохо объяснили это
 * в политике конфиденциальности, а не значит, что список надо прятать.
 */
final class DataExporter
{
    public function export(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'notice'      => __('gdpr.export_notice'),

            'account' => [
                'name'       => $user->name,
                'email'      => $user->email,
                'avatar_url' => $user->avatar_url,
                'phone'      => $user->phone,
                'locale'     => $user->locale,
                'seller_type' => $user->seller_type,
                'status'     => $user->status,
                'registered_at' => $user->created_at?->toIso8601String(),
                // google_id не отдаём: это идентификатор, а не данные о
                // человеке, и в чужих руках он лишний.
            ],

            'listings' => $user->listings()->withTrashed()->get()->map(fn ($l) => [
                'number'        => $l->msisdn,
                'price'         => $l->price,
                'status'        => $l->status,
                'description'   => $l->description,
                'contact_name'  => $l->contact_name,
                'contact_phone' => $l->contact_phone,
                'contact_email' => $l->contact_email,
                'created_at'    => $l->created_at?->toIso8601String(),
                'published_at'  => $l->published_at?->toIso8601String(),
                'views'         => $l->views,
                'contact_reveals' => $l->contact_reveals,
            ])->all(),

            // Показываем честно: да, мы пишем IP каждого раскрытия.
            // Именно это чаще всего удивляет — и именно поэтому обязано
            // быть в выгрузке.
            'contacts_you_revealed' => $user->reveals()->with('listing:id,msisdn')->get()->map(fn ($r) => [
                'number'     => $r->listing?->msisdn,
                'revealed_at' => $r->created_at?->toIso8601String(),
                'ip'         => $r->ip,
            ])->all(),

            'saved_searches' => $user->savedSearches()->get()->map(fn ($s) => [
                'label'   => $s->label,
                'pattern' => $s->pattern,
                'filters' => $s->filters,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all(),

            'favorites' => $user->favorites()->with('listing:id,msisdn')->get()
                ->map(fn ($f) => $f->listing?->msisdn)->filter()->values()->all(),
        ];
    }
}
