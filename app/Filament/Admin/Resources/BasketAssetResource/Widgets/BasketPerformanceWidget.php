<?php

namespace App\Filament\Admin\Resources\BasketAssetResource\Widgets;

use App\Domain\Basket\Services\BasketPerformanceService;
use App\Models\BasketAsset;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class BasketPerformanceWidget extends BaseWidget
{
    protected static ?string $heading = 'Performance History';
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '400px';

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->record
                    ? $this->record->performances()->getQuery()
                    : \App\Models\BasketPerformance::query()->whereRaw('1 = 0')
            )
            ->columns([
                Tables\Columns\TextColumn::make('period_type')
                    ->label('Period')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hour' => 'gray',
                        'day' => 'info',
                        'week' => 'primary',
                        'month' => 'success',
                        'quarter' => 'warning',
                        'year' => 'danger',
                        default => 'secondary',
                    }),
                
                Tables\Columns\TextColumn::make('period_end')
                    ->label('Date')
                    ->dateTime('M j, Y')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('formatted_return')
                    ->label('Return')
                    ->html()
                    ->color(fn ($record): string => $record->return_percentage >= 0 ? 'success' : 'danger'),
                
                Tables\Columns\TextColumn::make('volatility')
                    ->label('Volatility')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) . '%' : '-')
                    ->color(fn ($state): string => match (true) {
                        $state <= 5 => 'success',
                        $state <= 10 => 'primary',
                        $state <= 20 => 'warning',
                        default => 'danger',
                    }),
                
                Tables\Columns\TextColumn::make('sharpe_ratio')
                    ->label('Sharpe Ratio')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 2) : '-')
                    ->color(fn ($state): string => match (true) {
                        $state >= 2 => 'success',
                        $state >= 1 => 'primary',
                        $state >= 0 => 'warning',
                        default => 'danger',
                    }),
                
                Tables\Columns\TextColumn::make('max_drawdown')
                    ->label('Max Drawdown')
                    ->formatStateUsing(fn ($state) => $state ? '-' . number_format($state, 2) . '%' : '-')
                    ->color(fn ($state): string => match (true) {
                        $state <= 5 => 'success',
                        $state <= 10 => 'primary',
                        $state <= 20 => 'warning',
                        default => 'danger',
                    }),
                
                Tables\Columns\TextColumn::make('performance_rating')
                    ->label('Rating')
                    ->badge()
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', $state))
                    ->color(fn (string $state): string => match ($state) {
                        'excellent' => 'success',
                        'good' => 'primary',
                        'neutral' => 'warning',
                        'poor' => 'danger',
                        'very_poor' => 'gray',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('period_type')
                    ->options([
                        'hour' => 'Hourly',
                        'day' => 'Daily',
                        'week' => 'Weekly',
                        'month' => 'Monthly',
                        'quarter' => 'Quarterly',
                        'year' => 'Yearly',
                    ])
                    ->multiple(),
            ])
            ->defaultSort('period_end', 'desc')
            ->actions([
                Tables\Actions\Action::make('view_components')
                    ->label('Components')
                    ->icon('heroicon-m-list-bullet')
                    ->modalHeading(fn ($record) => "Component Performance - {$record->period_type}")
                    ->modalContent(function ($record) {
                        $components = $record->componentPerformances()
                            ->orderBy('contribution_percentage', 'desc')
                            ->get();
                        
                        return view('filament.admin.resources.basket-asset-resource.widgets.component-performance-modal', [
                            'components' => $components,
                        ]);
                    })
                    ->modalFooterActions([]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calculate_all')
                    ->label('Calculate All Periods')
                    ->icon('heroicon-m-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        $service = app(BasketPerformanceService::class);
                        $performances = $service->calculateAllPeriods($this->record);
                        
                        $this->dispatch('notify', [
                            'type' => 'success',
                            'message' => "Calculated {$performances->count()} performance periods",
                        ]);
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}