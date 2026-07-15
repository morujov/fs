<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Ресурс для модератора и суперадмина: объявления, жалобы, магазины.
 *
 * isModerator() истинно и для суперадмина (см. User::isModerator) — суперадмин
 * видит всё, что видит модератор. Заблокированный не проходит canAccessPanel,
 * так что до этих проверок не доходит. Гейт в авторизации ресурса, не в меню.
 */
trait ModeratorAccess
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isModerator();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDeleteAny(): bool
    {
        return static::canAccess();
    }
}
