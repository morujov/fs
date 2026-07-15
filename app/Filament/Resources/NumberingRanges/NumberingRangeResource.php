<?php

namespace App\Filament\Resources\NumberingRanges;

use App\Filament\Resources\NumberingRanges\Pages\CreateNumberingRange;
use App\Filament\Resources\NumberingRanges\Pages\EditNumberingRange;
use App\Filament\Resources\NumberingRanges\Pages\ListNumberingRanges;
use App\Filament\Resources\NumberingRanges\Schemas\NumberingRangeForm;
use App\Filament\Resources\NumberingRanges\Tables\NumberingRangesTable;
use App\Models\NumberingRange;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NumberingRangeResource extends Resource
{
    protected static ?string $model = NumberingRange::class;

    use \App\Filament\Concerns\SuperadminOnly;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return NumberingRangeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NumberingRangesTable::configure($table);
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
            'index' => ListNumberingRanges::route('/'),
            'create' => CreateNumberingRange::route('/create'),
            'edit' => EditNumberingRange::route('/{record}/edit'),
        ];
    }
}
