<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('google_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('avatar_url')
                    ->url(),
                Select::make('seller_type')
                    ->options(['private' => 'Private', 'shop' => 'Shop']),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('locale')
                    ->required()
                    ->default('es'),
                Select::make('status')
                    ->options(['active' => 'Active', 'flagged' => 'Flagged', 'blocked' => 'Blocked'])
                    ->default('active')
                    ->required(),
                Select::make('role')
                    ->options(['moderator' => 'Moderator', 'superadmin' => 'Superadmin']),
                TextInput::make('reveal_count_total')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('last_reveal_at'),
            ]);
    }
}
