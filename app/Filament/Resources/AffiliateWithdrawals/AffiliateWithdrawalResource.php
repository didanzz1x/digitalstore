<?php

namespace App\Filament\Resources\AffiliateWithdrawals;

use App\Filament\Resources\AffiliateWithdrawals\Pages\ListAffiliateWithdrawals;
use App\Models\AffiliateWithdrawal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AffiliateWithdrawalResource extends Resource
{
    protected static ?string $model = AffiliateWithdrawal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Affiliate Withdrawals';

    protected static ?string $modelLabel = 'Withdrawal Affiliate';

    protected static \UnitEnum|string|null $navigationGroup = 'Affiliate';

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return Schemas\AffiliateWithdrawalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\AffiliateWithdrawalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateWithdrawals::route('/'),
        ];
    }
}
