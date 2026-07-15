<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Ресурс только для суперадмина: пороги, роли, план нумерации, блок-лист.
 *
 * Проверку роли ставим в авторизацию ресурса, а НЕ в скрытие пункта меню:
 * спрятанная ссылка не защищает URL, модератор дошёл бы до /admin/settings
 * руками. canAccess закрывает и навигацию, и лист-страницу (см. gate в
 * ListRecords ниже по стеку), can* — record-действия.
 *
 * Fail-closed: null-роль (обычный продавец) и заблокированный сюда не проходят
 * (canAccessPanel их не пустит вовсе), а isSuperadmin() строго проверяет строку.
 */
trait SuperadminOnly
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperadmin();
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
