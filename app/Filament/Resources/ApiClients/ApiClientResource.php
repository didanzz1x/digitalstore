<?php

namespace App\Filament\Resources\ApiClients;

use App\Filament\Resources\ApiClients\Pages\CreateApiClient;
use App\Filament\Resources\ApiClients\Pages\EditApiClient;
use App\Filament\Resources\ApiClients\Pages\ListApiClients;
use App\Models\ApiClient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApiClientResource extends Resource
{
    protected static ?string $model = ApiClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'API Clients';

    protected static ?string $modelLabel = 'API Client';

    protected static ?string $navigationGroup = 'Public API';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return Schemas\ApiClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\ApiClientsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiClients::route('/'),
            'create' => CreateApiClient::route('/create'),
            'edit' => EditApiClient::route('/{record}/edit'),
        ];
    }
}
