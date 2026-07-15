<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OTP для подтверждения владения продаваемым номером.
 * Код хранится хэшем — утечка таблицы не должна давать возможности
 * подтвердить чужие объявления.
 */
class OtpCode extends Model
{
    protected $fillable = [
        'listing_id', 'msisdn', 'code_hash', 'attempts', 'sends',
        'expires_at', 'consumed_at',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired()
            && $this->consumed_at === null
            && $this->attempts < 5;
    }
}
