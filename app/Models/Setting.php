<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Настройка. Пороги лимитов, TTL, фича-флаги — всё правится из админки
 * без деплоя: на shared-хостинге каждый деплой это ручной git pull
 * в рвущемся терминале.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    protected static function booted(): void
    {
        static::saved(fn (self $s) => Cache::forget('setting.'.$s->key));
        static::deleted(fn (self $s) => Cache::forget('setting.'.$s->key));
    }

    /** Читается на каждый запрос — держим в кэше. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('setting.'.$key, function () use ($key, $default) {
            $s = static::find($key);

            return $s ? $s->typed() : $default;
        });
    }

    public function typed(): mixed
    {
        return match ($this->type) {
            'int'   => (int) $this->value,
            'float' => (float) $this->value,
            'bool'  => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'json'  => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
