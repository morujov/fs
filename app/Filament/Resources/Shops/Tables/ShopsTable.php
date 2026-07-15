<?php

namespace App\Filament\Resources\Shops\Tables;

use App\Models\Shop;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('nif_cif')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('province.id')
                    ->searchable(),
                TextColumn::make('website')
                    ->searchable(),
                // Инвариант №2: маска, а не полный телефон магазина. Не
                // searchable — поиск шёл бы по реальной колонке и стал бы
                // каналом проверки «есть ли такой номер».
                TextColumn::make('contact_masked_phone')
                    ->label(__('shop.attributes.contact_phone'))
                    ->state(fn (Shop $record): string => $record->maskedPhone()),
                TextColumn::make('logo_path')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('rejection_reason')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
