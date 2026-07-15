<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Строка плана нумерации. Матчинг по самому длинному префиксу —
 * см. комментарий в миграции и App\Services\Search\NumberingPlan.
 */
class NumberingRange extends Model
{
    protected $fillable = [
        'prefix', 'length', 'kind', 'is_sellable', 'reason_es', 'source', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'length'      => 'integer',
            'is_sellable' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Матчит ли префикс этот номер. */
    public function matches(string $msisdn): bool
    {
        return str_starts_with($msisdn, $this->prefix);
    }
}
