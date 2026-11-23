<?php

namespace App\Filament\Exports;

use App\Domain\Account\Models\Account;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AccountExporter extends Exporter
{
    protected static ?string $model = Account::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid')
                ->label('Account ID'),
            ExportColumn::make('name')
                ->label('Account Name'),
            ExportColumn::make('user_uuid')
                ->label('User ID'),
            ExportColumn::make('balance')
                ->label('Balance (USD)')
                ->formatStateUsing(fn ($state) => number_format($state / 100, 2)),
            ExportColumn::make('frozen')
                ->label('Status')
                ->formatStateUsing(fn ($state) => $state ? 'Frozen' : 'Active'),
            ExportColumn::make('created_at')
                ->label('Created Date'),
            ExportColumn::make('updated_at')
                ->label('Last Updated'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $rows = (int) $export->successful_rows;
        $body = 'Your account export is complete. ' . number_format($rows) . ' ' . str('row')->plural($rows)
            . ' ' . ($rows === 1 ? 'was' : 'were') . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' could not be exported.';
        }

        return $body;
    }
}
