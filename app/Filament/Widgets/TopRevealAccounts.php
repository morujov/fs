<?php

namespace App\Filament\Widgets;

use App\Models\ContactReveal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Топ аккаунтов по раскрытиям контактов за 24 часа.
 *
 * Ради этого виджета всё и затевалось: это единственное место, где скрейпера
 * видно в первый день, ещё до того как сработает автоблок. Много раскрытий с
 * одного аккаунта и/или с многих IP — сигнал, что базу выкачивают.
 *
 * Показываем email аккаунта-покупателя (его Google-логин) — это НЕ контакт
 * продавца, инвариант №2 сюда не относится: он про contact_* объявления.
 */
class TopRevealAccounts extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.top_reveals.heading'))
            ->query(
                fn (): Builder => ContactReveal::query()
                    // user_id как ключ строки: агрегат группируется по нему,
                    // и он уникален на группу — Livewire нужен стабильный key.
                    ->selectRaw('user_id as id, user_id')
                    ->selectRaw('COUNT(*) as reveals')
                    ->selectRaw('COUNT(DISTINCT ip) as ips')
                    ->selectRaw('MAX(created_at) as last_reveal_at')
                    ->where('created_at', '>=', now()->subDay())
                    ->groupBy('user_id')
                    ->orderByDesc('reveals')
                    ->limit(10)
            )
            ->paginated(false)
            // Строки — агрегат с GROUP BY user_id. Filament иначе доклеивает
            // тай-брейк `ORDER BY contact_reveals.id`, а id не в GROUP BY —
            // only_full_group_by это отвергает. Ключ строки берём из alias
            // `user_id as id`, стабильный порядок задаёт orderByDesc(reveals).
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('user.email')
                    ->label(__('admin.top_reveals.columns.account')),
                TextColumn::make('reveals')
                    ->label(__('admin.top_reveals.columns.reveals'))
                    ->numeric()
                    ->badge()
                    ->color('danger'),
                TextColumn::make('ips')
                    ->label(__('admin.top_reveals.columns.ips'))
                    ->numeric(),
                TextColumn::make('last_reveal_at')
                    ->label(__('admin.top_reveals.columns.last'))
                    ->dateTime()
                    ->since(),
            ]);
    }
}
