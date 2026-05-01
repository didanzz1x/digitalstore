<?php

namespace App\Filament\Resources\AffiliateWithdrawals\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AffiliateWithdrawalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('amount')->disabled(),
            TextInput::make('method')->disabled(),
            TextInput::make('bank_name'),
            TextInput::make('bank_account_no'),
            TextInput::make('bank_account_name'),
            TextInput::make('status')->disabled(),
            Textarea::make('admin_note')->rows(3),
        ]);
    }
}
