<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Пользователь.
 *
 * Авторизация только через Google OAuth. Ни password, ни email_verified_at:
 * пароля не существует, email от Google приходит верифицированным.
 * Поэтому здесь нет ни MustVerifyEmail, ни хэширования.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'google_id', 'name', 'email', 'avatar_url',
        'seller_type', 'phone', 'locale', 'status',
    ];

    protected $hidden = [
        'google_id', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'last_reveal_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------
    // Связи
    // ---------------------------------------------------------------

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    public function reveals(): HasMany
    {
        return $this->hasMany(ContactReveal::class);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    // ---------------------------------------------------------------
    // Состояние
    // ---------------------------------------------------------------

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /** Магазин считается таковым только после верификации NIF/CIF. */
    public function isVerifiedShop(): bool
    {
        return $this->seller_type === 'shop'
            && $this->shop?->status === 'verified';
    }

    /** Сколько контактов аккаунт раскрыл за последние сутки. */
    public function revealsLast24h(): int
    {
        return $this->reveals()
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }
}
