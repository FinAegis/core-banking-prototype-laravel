<?php

namespace App\Filament\Exports;

use App\Domain\Account\Models\Transaction;
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
                ->formatStateUsing(
                    fn (string $state): string => match ($state) {
                        'deposit'      => 'Deposit',
                        'withdrawal'   => 'Withdrawal',
                        'transfer_in'  => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        default        => $state,
                    }
                ),
            ExportColumn::make('amount')
                ->label('Amount')
                ->formatStateUsing(
                    function ($state, $record) {
                        $formatted = number_format($state / 100, 2);
                        $sign = $record->getDirection() === 'credit' ? '+' : '-';

                        return $sign . $formatted;
                    }
                ),
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
        $rows = (int) $export->successful_rows;
        $body = 'Your transaction export is complete. ' . number_format($rows) . ' ' . str('row')->plural($rows)
            . ' ' . ($rows === 1 ? 'was' : 'were') . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' could not be exported.';
        }

        return $body;
    }
}
