<?php

namespace App\Filament\Resources\BlocklistNumbers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BlocklistNumberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('msisdn_pattern')
                    ->required(),
                TextInput::make('reason')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
