<?php

namespace App\Filament\Resources\MemberSubscriptions\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MemberSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.email')->label('User')->searchable(),
                TextColumn::make('order.order_code')->label('Order')->searchable(),
                TextColumn::make('price')->money('IDR'),
                TextColumn::make('duration_days')->suffix(' hari'),
                TextColumn::make('started_at')->dateTime(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
                BadgeColumn::make('status')->colors([
                    'success' => 'active',
                    'gray' => 'expired',
                    'danger' => 'cancelled',
                ]),
                TextColumn::make('created_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'expired' => 'Expired',
                    'cancelled' => 'Cancelled',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
