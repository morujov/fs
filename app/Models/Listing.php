<?php

namespace App\Models;

use App\Services\Search\NumberPatternQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'shop_id', 'msisdn', 'price', 'is_negotiable',
        'operator_id', 'line_type', 'has_permanency', 'permanency_until',
        'condition', 'pattern_tags', 'province_id', 'city',
        'description', 'description_lang',
        'contact_name', 'contact_phone', 'contact_email', 'contact_whatsapp',
        'status', 'moderation_score', 'rejection_reason',
        'phone_verified_at', 'published_at', 'expires_at', 'slug',
        'expiry_notified_at', 'renewals_count', 'sold_at',
    ];

    /**
     * Контакты продавца скрыты от сериализации по умолчанию.
     *
     * Это вторая линия обороны, а не основная: если кто-то случайно вернёт
     * модель через ->toJson() или отдаст её в Blade целиком, контакты не
     * утекут. Основная линия — маскировка на сервере и проверка сессии
     * в ContactRevealController. См. блюпринт, раздел 4A.
     */
    protected $hidden = [
        'contact_phone', 'contact_email', 'contact_name',
    ];

    protected function casts(): array
    {
        return [
            'price'             => 'decimal:2',
            'is_negotiable'     => 'boolean',
            'has_permanency'    => 'boolean',
            'permanency_until'  => 'date',
            'contact_whatsapp'  => 'boolean',
            'pattern_tags'      => 'array',
            'phone_verified_at' => 'datetime',
            'published_at'       => 'datetime',
            'expires_at'         => 'datetime',
            'expiry_notified_at' => 'datetime',
            'sold_at'            => 'datetime',
        ];
    }

    // ---------------------------------------------------------------
    // Связи
    // ---------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function reveals(): HasMany
    {
        return $this->hasMany(ContactReveal::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /**
     * Wildcard-поиск по номеру.
     *
     * Пользовательский синтаксис: цифры и '?' (одна любая цифра).
     * '6??12??34' → LIKE '6__12__34'.
     *
     * Санитизация делегирована NumberPatternQuery и НЕ дублируется здесь.
     * Раньше эти строки были скопированы в оба места «для надёжности» — но
     * два экземпляра защиты от '%' означают ровно одно: однажды поправят
     * один и забудут другой, а цена ошибки — выгрузка всей базы контактов
     * одним запросом. Один источник правды, покрытый тестами.
     */
    public function scopeMatchingPattern(Builder $q, ?string $pattern): Builder
    {
        $like = NumberPatternQuery::toLike($pattern);

        // null = искать не по чему. Не фильтруем, но и не подставляем
        // шаблон «на всё»: решение показывать всё принимает вызывающий.
        if ($like === null) {
            return $q;
        }

        return $q->where('msisdn', 'LIKE', $like);
    }

    // ---------------------------------------------------------------
    // Маскировка контактов
    // ---------------------------------------------------------------

    /**
     * Телефон продавца для анонима: '6** ** ** **'.
     *
     * Прячем всё, кроме первой цифры. Это ничего не стоит: продаваемый номер
     * (msisdn) виден целиком и всегда — он и есть товар. Телефон продавца —
     * лишь канал связи, для оценки товара он не нужен. Значит показывать его
     * хвост было бы бессмысленным подарком скрейперу.
     *
     * Метод возвращает СТРОКУ С МАСКОЙ, а не полное значение. Полное значение
     * уходит только из ContactRevealController после проверки сессии.
     * Рендерить полный телефон и прятать его CSS-ом (blur/opacity) нельзя:
     * так делает половина досок объявлений, и это вскрывается через Ctrl+U.
     */
    public function maskedPhone(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->contact_phone);
        $first  = $digits !== '' ? substr($digits, -9, 1) : '6';

        return $first.'** ** ** **';
    }

    /** Имя продавца для анонима: 'Juan M.' */
    public function maskedName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->contact_name), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === []) {
            return '—';
        }

        $name = array_shift($parts);

        return $parts === []
            ? $name
            : $name.' '.mb_strtoupper(mb_substr($parts[0], 0, 1)).'.';
    }

    /** Email продавца для анонима: 'j••••@gmail.com' */
    public function maskedEmail(): ?string
    {
        if (! $this->contact_email) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $this->contact_email, 2), 2, '');

        return mb_substr($local, 0, 1).'••••@'.$domain;
    }

    /**
     * Замаскированный набор — то, что уходит в HTML всем без разбора,
     * включая Googlebot.
     */
    public function maskedContact(): array
    {
        return [
            'name'     => $this->maskedName(),
            'phone'    => $this->maskedPhone(),
            'email'    => $this->maskedEmail(),
            'whatsapp' => null,
            'revealed' => false,
        ];
    }

    /**
     * Полный набор. Вызывать ТОЛЬКО после проверки авторизации и лимитов.
     */
    public function fullContact(): array
    {
        $digits = preg_replace('/\D/', '', (string) $this->contact_phone);

        return [
            'name'     => $this->contact_name,
            'phone'    => $this->contact_phone,
            'email'    => $this->contact_email,
            'whatsapp' => $this->contact_whatsapp ? 'https://wa.me/'.$digits : null,
            'revealed' => true,
        ];
    }

    // ---------------------------------------------------------------
    // Прочее
    // ---------------------------------------------------------------

    /**
     * Продаваемый номер для витрины: '612 34 56 78'.
     *
     * Испанская запись мобильного — группами 3-2-2-2, а не 3-3-3.
     * Никогда не маскируется: это товар и весь SEO.
     */
    public function formattedMsisdn(): string
    {
        $n = $this->msisdn;

        return substr($n, 0, 3).' '.substr($n, 3, 2).' '.substr($n, 5, 2).' '.substr($n, 7, 2);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
