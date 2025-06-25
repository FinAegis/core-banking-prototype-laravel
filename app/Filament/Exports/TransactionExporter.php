<?php

namespace App\Filament\Exports;

use App\Models\Transaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('processed_at')
                ->label('Date'),
            ExportColumn::make('account.name')
                ->label('Account'),
            ExportColumn::make('type')
                ->label('Type')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'deposit' => 'Deposit',
                    'withdrawal' => 'Withdrawal',
                    'transfer_in' => 'Transfer In',
                    'transfer_out' => 'Transfer Out',
                    default => $state,
                }),
            ExportColumn::make('amount')
                ->label('Amount')
                ->formatStateUsing(function ($state, $record) {
                    $formatted = number_format($state / 100, 2);
                    $sign = $record->getDirection() === 'credit' ? '+' : '-';
                    return $sign . $formatted;
                }),
            ExportColumn::make('asset_code')
                ->label('Currency'),
            ExportColumn::make('description')
                ->label('Description'),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (string $state): string => ucfirst($state)),
            ExportColumn::make('initiator.name')
                ->label('Initiated By'),
            ExportColumn::make('uuid')
                ->label('Transaction ID'),
            ExportColumn::make('hash')
                ->label('Hash'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your transaction export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}