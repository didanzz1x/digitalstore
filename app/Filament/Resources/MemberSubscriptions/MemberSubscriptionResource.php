<?php

namespace App\Filament\Resources\MemberSubscriptions;

use App\Filament\Resources\MemberSubscriptions\Pages\ListMemberSubscriptions;
use App\Models\MemberSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemberSubscriptionResource extends Resource
{
    protected static ?string $model = MemberSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Member Subscriptions';

    protected static ?string $modelLabel = 'Subscription Member';

    protected static ?string $navigationGroup = 'Membership';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return Tables\MemberSubscriptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberSubscriptions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
