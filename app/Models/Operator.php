<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Оператор мобильной связи.
 */
class Operator extends Model
{
    protected $fillable = [
        'name', 'slug', 'is_mvno', 'host_network', 'logo_path',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_mvno'   => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
