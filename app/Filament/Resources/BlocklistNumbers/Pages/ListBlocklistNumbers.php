<?php

namespace App\Filament\Resources\BlocklistNumbers\Pages;

use App\Filament\Resources\BlocklistNumbers\BlocklistNumberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlocklistNumbers extends ListRecords
{
    protected static string $resource = BlocklistNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
