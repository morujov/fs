<?php

namespace App\Filament\Resources\BlocklistNumbers;

use App\Filament\Resources\BlocklistNumbers\Pages\CreateBlocklistNumber;
use App\Filament\Resources\BlocklistNumbers\Pages\EditBlocklistNumber;
use App\Filament\Resources\BlocklistNumbers\Pages\ListBlocklistNumbers;
use App\Filament\Resources\BlocklistNumbers\Schemas\BlocklistNumberForm;
use App\Filament\Resources\BlocklistNumbers\Tables\BlocklistNumbersTable;
use App\Models\BlocklistNumber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BlocklistNumberResource extends Resource
{
    protected static ?string $model = BlocklistNumber::class;

    use \App\Filament\Concerns\SuperadminOnly;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BlocklistNumberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlocklistNumbersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlocklistNumbers::route('/'),
            'create' => CreateBlocklistNumber::route('/create'),
            'edit' => EditBlocklistNumber::route('/{record}/edit'),
        ];
    }
}
