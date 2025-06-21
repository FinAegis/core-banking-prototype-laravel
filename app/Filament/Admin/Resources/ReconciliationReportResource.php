<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Custodian\Services\DailyReconciliationService;
use App\Filament\Admin\Resources\ReconciliationReportResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ReconciliationReportResource extends Resource
{
    protected static ?string $model = null; // We'll use a virtual model
    
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    
    protected static ?string $navigationGroup = 'Banking';
    
    protected static ?int $navigationSort = 6;
    
    protected static ?string $navigationLabel = 'Reconciliation Reports';
    
    public static function getModelLabel(): string
    {
        return 'Reconciliation Report';
    }
    
    public static function getPluralModelLabel(): string
    {
        return 'Reconciliation Reports';
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('accounts_checked')
                    ->label('Accounts')
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('discrepancies_found')
                    ->label('Discrepancies')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->icon(fn ($state) => $state > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle'),
                    
                Tables\Columns\TextColumn::make('total_discrepancy_amount')
                    ->label('Total Discrepancy')
                    ->money('USD')
                    ->getStateUsing(fn ($record) => $record['total_discrepancy_amount'] / 100)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->numeric()
                    ->suffix(' min')
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View Report')
                    ->icon('heroicon-m-eye')
                    ->modalHeading('Reconciliation Report Details')
                    ->modalContent(function ($record): string {
                        return view('filament.admin.resources.reconciliation-report-details', [
                            'report' => $record,
                        ])->render();
                    })
                    ->modalWidth('7xl'),
                    
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(function ($record) {
                        $filename = "reconciliation-{$record['date']}.json";
                        
                        return response()->json($record)
                            ->header('Content-Disposition', "attachment; filename={$filename}");
                    }),
            ])
            ->bulkActions([]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReconciliationReports::route('/'),
        ];
    }
}