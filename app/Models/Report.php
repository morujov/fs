<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Жалоба на объявление.
 */
class Report extends Model
{
    protected $fillable = [
        'listing_id', 'user_id', 'reporter_ip', 'reporter_email',
        'reason', 'comment', 'status', 'resolution_note',
        'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', 'open');
    }

    /** «Это мой номер» и мошенничество разбираются первыми. */
    public function scopeUrgent(Builder $q): Builder
    {
        return $q->whereIn('reason', ['not_mine', 'fraud']);
    }
}
