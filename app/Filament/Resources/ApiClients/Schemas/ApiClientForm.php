<?php

namespace App\Filament\Resources\ApiClients\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ApiClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(120)
                ->helperText('Nama identifikasi client, mis. "Mitra Reseller A".'),
            Toggle::make('is_active')->default(true),
            TextInput::make('rate_limit_per_minute')
                ->numeric()
                ->minValue(1)
                ->maxValue(10000)
                ->default(60)
                ->helperText('Rate limit per menit per client.'),
            Textarea::make('allowed_ips')
                ->label('Allowed IPs (newline-separated, kosongkan = semua IP)')
                ->rows(3)
                ->helperText('Satu IP per baris. Kosongkan untuk izinkan semua.'),
            TextInput::make('api_key_prefix')
                ->disabled()
                ->dehydrated(false)
                ->visible(fn (?\App\Models\ApiClient $record) => $record !== null)
                ->helperText('8 karakter pertama dari API key untuk identifikasi (raw key tidak ditampilkan).'),
        ]);
    }
}
