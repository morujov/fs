<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Models\Listing;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('shop.name')
                    ->searchable(),
                TextColumn::make('msisdn')
                    ->searchable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable(),
                IconColumn::make('is_negotiable')
                    ->boolean(),
                TextColumn::make('operator.name')
                    ->searchable(),
                TextColumn::make('line_type')
                    ->badge(),
                IconColumn::make('has_permanency')
                    ->boolean(),
                TextColumn::make('permanency_until')
                    ->date()
                    ->sortable(),
                TextColumn::make('condition')
                    ->badge(),
                TextColumn::make('province.id')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('description_lang')
                    ->searchable(),
                // Инвариант №2: полный контакт продавца не рендерится в
                // админке — ни в таблице, ни в экспорте. Модератору для разбора
                // жалобы он не нужен, а CSV с контактами — готовая утечка базы
                // одним файлом. Показываем ту же маску, что и на витрине; она
                // считается на сервере и не ищется по реальной колонке (иначе
                // поиск по полному телефону стал бы каналом проверки «есть ли»).
                TextColumn::make('contact_masked_name')
                    ->label(__('listing.attributes.contact_name'))
                    ->state(fn (Listing $record): string => $record->maskedName()),
                TextColumn::make('contact_masked_phone')
                    ->label(__('listing.attributes.contact_phone'))
                    ->state(fn (Listing $record): string => $record->maskedPhone()),
                IconColumn::make('contact_whatsapp')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('moderation_score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rejection_reason')
                    ->searchable(),
                TextColumn::make('phone_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('views')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('contact_reveals')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('active_msisdn')
                    ->searchable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
