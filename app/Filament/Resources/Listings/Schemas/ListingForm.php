<?php

namespace App\Filament\Resources\Listings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('shop_id')
                    ->relationship('shop', 'name'),
                TextInput::make('msisdn')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                Toggle::make('is_negotiable')
                    ->required(),
                Select::make('operator_id')
                    ->relationship('operator', 'name'),
                Select::make('line_type')
                    ->options(['prepago' => 'Prepago', 'contrato' => 'Contrato'])
                    ->default('prepago')
                    ->required(),
                Toggle::make('has_permanency')
                    ->required(),
                DatePicker::make('permanency_until'),
                Select::make('condition')
                    ->options(['new' => 'New', 'used' => 'Used'])
                    ->default('used')
                    ->required(),
                TextInput::make('pattern_tags'),
                Select::make('province_id')
                    ->relationship('province', 'id')
                    ->required(),
                TextInput::make('city'),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('description_lang'),
                // Полей полного контакта продавца в форме админки нет:
                // рендерить их в input — это тот же полный контакт в HTML
                // (инвариант №2), просто на странице редактирования. Контакт
                // задаёт продавец при подаче; модератор его не правит. Маска
                // видна в таблице. Понадобится полный — отдельное осознанное
                // решение с логированием, а не поле формы по умолчанию.
                Toggle::make('contact_whatsapp')
                    ->required(),
                Select::make('status')
                    ->options([
            'draft' => 'Draft',
            'pending' => 'Pending',
            'active' => 'Active',
            'rejected' => 'Rejected',
            'sold' => 'Sold',
            'expired' => 'Expired',
            'archived' => 'Archived',
        ])
                    ->default('draft')
                    ->required(),
                TextInput::make('moderation_score')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('rejection_reason'),
                DateTimePicker::make('phone_verified_at'),
                DateTimePicker::make('published_at'),
                DateTimePicker::make('expires_at'),
                TextInput::make('views')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('contact_reveals')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('active_msisdn'),
            ]);
    }
}
