<?php

namespace App\Filament\Resources\NumberingRanges\Pages;

use App\Filament\Resources\NumberingRanges\NumberingRangeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNumberingRanges extends ListRecords
{
    protected static string $resource = NumberingRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
