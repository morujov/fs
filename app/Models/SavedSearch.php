<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Сохранённый поиск с email-алертом.
 * Главный механизм возврата аудитории — см. блюпринт, пробел №15.
 */
class SavedSearch extends Model
{
    protected $fillable = [
        'user_id', 'label', 'pattern', 'filters', 'frequency',
        'is_active', 'last_notified_listing_id', 'last_notified_at',
        'unsubscribe_token',
    ];

    protected function casts(): array
    {
        return [
            'filters'          => 'array',
            'is_active'        => 'boolean',
            'last_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->unsubscribe_token ??= Str::random(64);

            // Санитизация маски на входе. Продублировано с FormRequest
            // намеренно: '%' от пользователя превратил бы алерт в выгрузку
            // всей базы, и полагаться на один слой тут нельзя.
            if ($s->pattern !== null) {
                $s->pattern = substr(preg_replace('/[^0-9?]/', '', $s->pattern), 0, 9);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
