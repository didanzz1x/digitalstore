<?php

namespace App\Filament\Resources\AffiliateWithdrawals\Tables;

use App\Models\AffiliateWithdrawal;
use App\Services\AffiliateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AffiliateWithdrawalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.email')->label('User')->searchable(),
                TextColumn::make('amount')->money('IDR')->sortable(),
                BadgeColumn::make('method')->colors([
                    'gray' => 'wallet',
                    'primary' => 'bank',
                ]),
                BadgeColumn::make('status')->colors([
                    'warning' => 'pending',
                    'info' => 'approved',
                    'success' => 'paid',
                    'danger' => 'rejected',
                ]),
                TextColumn::make('bank_name')->toggleable(),
                TextColumn::make('bank_account_no')->toggleable(),
                TextColumn::make('bank_account_name')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('processed_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'paid' => 'Paid',
                    'rejected' => 'Rejected',
                ]),
                SelectFilter::make('method')->options([
                    'wallet' => 'Wallet',
                    'bank' => 'Bank',
                ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Tandai Paid')
                    ->visible(fn (AffiliateWithdrawal $record) => $record->isPending() && $record->method === AffiliateWithdrawal::METHOD_BANK)
                    ->requiresConfirmation()
                    ->schema([Textarea::make('admin_note')->rows(2)])
                    ->action(function (AffiliateWithdrawal $record, array $data) {
                        app(AffiliateService::class)->markWithdrawalPaid($record, Auth::user(), $data['admin_note'] ?? null);
                        Notification::make()->title('Withdrawal ditandai sudah dibayar')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->visible(fn (AffiliateWithdrawal $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->schema([Textarea::make('admin_note')->rows(2)])
                    ->action(function (AffiliateWithdrawal $record, array $data) {
                        app(AffiliateService::class)->rejectWithdrawal($record, Auth::user(), $data['admin_note'] ?? null);
                        Notification::make()->title('Withdrawal di-reject, saldo affiliate dikembalikan')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
