<?php

namespace App\Filament\Exports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class UserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid')
                ->label('User ID'),
            ExportColumn::make('name')
                ->label('Full Name'),
            ExportColumn::make('email')
                ->label('Email Address'),
            ExportColumn::make('email_verified_at')
                ->label('Email Verified'),
            ExportColumn::make('accounts_count')
                ->label('Number of Accounts')
                ->counts('accounts'),
            ExportColumn::make('created_at')
                ->label('Registration Date'),
            ExportColumn::make('updated_at')
                ->label('Last Updated'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $rows = (int) $export->successful_rows;
        $body = 'Your user export is complete. ' . number_format($rows) . ' ' . str('row')->plural($rows)
            . ' ' . ($rows === 1 ? 'was' : 'were') . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' could not be exported.';
        }

        return $body;
    }
}
