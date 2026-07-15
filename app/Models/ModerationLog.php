<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Строка лога конвейера модерации: одно сработавшее правило.
 */
class ModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['listing_id', 'rule', 'result', 'payload', 'actor'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
