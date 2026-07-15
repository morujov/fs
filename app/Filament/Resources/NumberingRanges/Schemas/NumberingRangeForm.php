<?php

namespace App\Filament\Resources\NumberingRanges\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class NumberingRangeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('prefix')
                    ->required(),
                TextInput::make('length')
                    ->required()
                    ->numeric()
                    ->default(9),
                Select::make('kind')
                    ->options([
            'mobile' => 'Mobile',
            'personal' => 'Personal',
            'fixed' => 'Fixed',
            'service' => 'Service',
            'reserved' => 'Reserved',
        ])
                    ->required(),
                Toggle::make('is_sellable')
                    ->required(),
                TextInput::make('reason_es'),
                TextInput::make('source'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
