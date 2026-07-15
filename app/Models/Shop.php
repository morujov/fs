<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Магазин. Отдельная сущность с витриной, а не галочка в users.
 */
class Shop extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'nif_cif', 'address', 'city',
        'province_id', 'website', 'contact_phone', 'logo_path',
        'description', 'status', 'verified_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('status', 'verified');
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Маска телефона магазина — до первой цифры, как у продавца-физлица
     * (Listing::maskedPhone). Инвариант №2 не делает исключения: полный
     * контакт не рендерится в HTML нигде, включая админку и её экспорт.
     *
     * Магазин — тоже продавец. Проверку NIF/CIF модератор делает алгоритмом
     * по самому NIF/CIF (пробел №28), телефон для этого не нужен. Если в S6
     * у витрины магазина появится осознанно публичный контакт — это будет
     * отдельное решение снять маску там, а не побочный эффект генератора.
     */
    public function maskedPhone(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->contact_phone);
        $first  = $digits !== '' ? substr($digits, -9, 1) : '6';

        return $first.'** ** ** **';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
