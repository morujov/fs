<?php

namespace App\Filament\Resources\BlocklistNumbers\Pages;

use App\Filament\Resources\BlocklistNumbers\BlocklistNumberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBlocklistNumber extends EditRecord
{
    protected static string $resource = BlocklistNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
