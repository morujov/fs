<?php

namespace App\Filament\Resources\Reports\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('listing_id')
                    ->relationship('listing', 'id')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('reporter_ip')
                    ->required(),
                TextInput::make('reporter_email')
                    ->email(),
                Select::make('reason')
                    ->options([
            'not_mine' => 'Not mine',
            'fraud' => 'Fraud',
            'wrong_info' => 'Wrong info',
            'spam' => 'Spam',
            'sold' => 'Sold',
            'other' => 'Other',
        ])
                    ->required(),
                Textarea::make('comment')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
            'open' => 'Open',
            'reviewing' => 'Reviewing',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
        ])
                    ->default('open')
                    ->required(),
                Textarea::make('resolution_note')
                    ->columnSpanFull(),
                TextInput::make('resolved_by')
                    ->numeric(),
                DateTimePicker::make('resolved_at'),
            ]);
    }
}
