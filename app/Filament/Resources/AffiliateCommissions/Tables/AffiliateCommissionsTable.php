<?php

namespace App\Filament\Resources\AffiliateCommissions\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliateCommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.email')->label('Referrer')->searchable(),
                TextColumn::make('referee.email')->label('Referee')->searchable(),
                TextColumn::make('order.order_code')->label('Order')->searchable(),
                TextColumn::make('percent')->suffix('%'),
                TextColumn::make('amount')->money('IDR')->sortable(),
                BadgeColumn::make('status')->colors([
                    'success' => 'credited',
                    'gray' => 'reverted',
                ]),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'credited' => 'Credited',
                    'reverted' => 'Reverted',
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
