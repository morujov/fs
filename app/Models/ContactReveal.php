<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Факт раскрытия контакта. Источник правды для лимитов и детекта скрейпинга.
 *
 * UPDATED_AT нет: строка создаётся один раз и не меняется. Повторное
 * раскрытие того же объявления тем же пользователем не создаёт новую строку
 * (UNIQUE user_id+listing_id) и не расходует лимит повторно.
 */
class ContactReveal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'listing_id', 'ip', 'user_agent'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
