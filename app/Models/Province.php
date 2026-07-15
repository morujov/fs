<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Провинция Испании. Справочник, 52 записи, правится только сидером.
 */
class Province extends Model
{
    protected $fillable = [
        'code', 'name_es', 'name_ca', 'name_gl', 'name_eu', 'name_en',
        'community', 'slug',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    /**
     * Название на текущей локали с откатом на кастильский.
     *
     * Это не перевод, а официальная топонимика: Girona/Gerona,
     * A Coruña/La Coruña, Araba/Álava — разные официальные названия,
     * а не разные написания одного.
     */
    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $this->{'name_'.$locale} ?? $this->name_es;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
