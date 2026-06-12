<?php

namespace App\Filament\Resources\People\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PersonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('display_name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->default(null),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                DatePicker::make('dob'),
                TextInput::make('sex')
                    ->default(null),
                TextInput::make('height_cm')
                    ->numeric()
                    ->default(null),
                TextInput::make('locale')
                    ->required()
                    ->default('en'),
                TextInput::make('unit_system')
                    ->required()
                    ->default('metric'),
                TextInput::make('timezone')
                    ->required()
                    ->default('UTC'),
                TextInput::make('country')
                    ->default(null),
                TextInput::make('health_screen_status')
                    ->required()
                    ->default('none'),
                Textarea::make('onboarding_state')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
