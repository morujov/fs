<?php

namespace App\Filament\Resources\Shops\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('nif_cif')
                    ->required(),
                TextInput::make('address'),
                TextInput::make('city'),
                Select::make('province_id')
                    ->relationship('province', 'id'),
                TextInput::make('website')
                    ->url(),
                TextInput::make('contact_phone')
                    ->tel(),
                TextInput::make('logo_path'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'])
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('verified_at'),
                TextInput::make('rejection_reason'),
            ]);
    }
}
