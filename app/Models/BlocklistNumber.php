<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Запись блок-листа. Паттерн в том же синтаксисе, что и поиск: цифры и '?'.
 */
class BlocklistNumber extends Model
{
    protected $fillable = ['msisdn_pattern', 'reason', 'created_by', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * Матчит ли номер этот паттерн.
     *
     * Сравнение в PHP, а не через SQL LIKE: блок-лист мал (десятки строк),
     * кэшируется целиком, и один лишний запрос к БД на каждую подачу
     * объявления не нужен.
     */
    public function matches(string $msisdn): bool
    {
        $regex = '/^'.strtr(preg_quote($this->msisdn_pattern, '/'), ['\?' => '\d']).'/';

        return (bool) preg_match($regex, $msisdn);
    }
}
