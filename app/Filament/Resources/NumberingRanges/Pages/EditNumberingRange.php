<?php

namespace App\Filament\Resources\NumberingRanges\Pages;

use App\Filament\Resources\NumberingRanges\NumberingRangeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNumberingRange extends EditRecord
{
    protected static string $resource = NumberingRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
