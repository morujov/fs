<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('value')
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(['string' => 'String', 'int' => 'Int', 'float' => 'Float', 'bool' => 'Bool', 'json' => 'Json'])
                    ->default('string')
                    ->required(),
                TextInput::make('group')
                    ->required()
                    ->default('general'),
                TextInput::make('description'),
            ]);
    }
}
