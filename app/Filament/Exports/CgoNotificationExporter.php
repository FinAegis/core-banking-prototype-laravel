<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domain\Cgo\Models\CgoNotification;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Exports the CGO early access sign-ups as a mailing list: the email and
 * when the person signed up. IP address and user agent stay out of the file.
 */
class CgoNotificationExporter extends Exporter
{
    protected static ?string $model = CgoNotification::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('email')
                ->label('Email'),
            ExportColumn::make('created_at')
                ->label('Signed up'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $rows = (int) $export->successful_rows;
        $body = 'Your CGO early access export is complete. ' . number_format($rows) . ' ' . str('row')->plural($rows)
            . ' ' . ($rows === 1 ? 'was' : 'were') . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' could not be exported.';
        }

        return $body;
    }
}
