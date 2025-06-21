<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Custodian\Services\CustodianHealthMonitor;
use App\Domain\Custodian\Services\CustodianRegistry;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Illuminate\Support\Collection;

class BankOperations extends Page implements HasTable
{
    use InteractsWithTable;
    
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    
    protected static ?string $navigationGroup = 'Banking';
    
    protected static ?int $navigationSort = 7;
    
    protected static ?string $title = 'Bank Operations Center';
    
    protected static string $view = 'filament.admin.pages.bank-operations';
    
    public function mount(): void
    {
        // Initialize any needed data
    }
    
    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(fn () => $this->getBankOperationsQuery())
            ->columns([
                Tables\Columns\TextColumn::make('custodian')
                    ->label('Bank')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Health Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'healthy' => 'success',
                        'degraded' => 'warning',
                        'unhealthy' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('overall_failure_rate')
                    ->label('Failure Rate')
                    ->suffix('%')
                    ->color(fn ($state) => $state > 50 ? 'danger' : ($state > 20 ? 'warning' : 'success')),
                    
                Tables\Columns\ViewColumn::make('circuit_breakers')
                    ->label('Circuit Breakers')
                    ->view('filament.admin.tables.columns.circuit-breakers'),
                    
                Tables\Columns\TextColumn::make('availability_24h')
                    ->label('24h Availability')
                    ->suffix('%')
                    ->getStateUsing(fn ($record) => $this->get24hAvailability($record['custodian'])),
                    
                Tables\Columns\TextColumn::make('last_check')
                    ->label('Last Check')
                    ->dateTime('Y-m-d H:i:s')
                    ->description(fn ($state) => now()->diffForHumans($state, true) . ' ago'),
            ])
            ->actions([
                Tables\Actions\Action::make('health_check')
                    ->label('Check Health')
                    ->icon('heroicon-m-heart')
                    ->action(function ($record) {
                        $monitor = app(CustodianHealthMonitor::class);
                        $health = $monitor->getCustodianHealth($record['custodian']);
                        
                        $this->notify(
                            $health['status'] === 'healthy' ? 'success' : 'warning',
                            "{$record['custodian']} is {$health['status']}"
                        );
                    }),
                    
                Tables\Actions\Action::make('reset_circuit')
                    ->label('Reset Circuit')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $registry = app(CustodianRegistry::class);
                        $connector = $registry->getConnector($record['custodian']);
                        $connector->resetCircuitBreaker();
                        
                        $this->notify('success', "Circuit breaker reset for {$record['custodian']}");
                    }),
                    
                Tables\Actions\Action::make('view_logs')
                    ->label('View Logs')
                    ->icon('heroicon-m-document-text')
                    ->url(fn ($record) => "/admin/logs?custodian={$record['custodian']}")
                    ->openUrlInNewTab(),
            ])
            ->poll('10s');
    }
    
    protected function getBankOperationsQuery()
    {
        $monitor = app(CustodianHealthMonitor::class);
        $healthData = $monitor->getAllCustodiansHealth();
        
        // Convert to collection for table
        return collect($healthData)->map(function ($health, $custodian) {
            return array_merge($health, [
                'id' => $custodian, // Add ID for table
            ]);
        });
    }
    
    protected function get24hAvailability(string $custodian): float
    {
        $monitor = app(CustodianHealthMonitor::class);
        $metrics = $monitor->getAvailabilityMetrics($custodian, 24);
        
        return $metrics['availability_percentage'] ?? 0.0;
    }
    
    public function getCustodianRegistry(): CustodianRegistry
    {
        return app(CustodianRegistry::class);
    }
    
    public function getHealthMonitor(): CustodianHealthMonitor
    {
        return app(CustodianHealthMonitor::class);
    }
}