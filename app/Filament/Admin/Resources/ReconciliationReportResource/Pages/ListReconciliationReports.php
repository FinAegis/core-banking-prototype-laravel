<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ReconciliationReportResource\Pages;

use App\Filament\Admin\Resources\ReconciliationReportResource;
use App\Domain\Custodian\Services\DailyReconciliationService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ListReconciliationReports extends ListRecords
{
    protected static string $resource = ReconciliationReportResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_reconciliation')
                ->label('Run Reconciliation')
                ->icon('heroicon-m-play')
                ->action(function () {
                    $service = app(DailyReconciliationService::class);
                    
                    try {
                        $service->performDailyReconciliation();
                        
                        $this->notify('success', 'Reconciliation completed successfully');
                        
                        // Refresh the page
                        $this->redirect(static::getResource()::getUrl('index'));
                    } catch (\Exception $e) {
                        $this->notify('danger', 'Reconciliation failed: ' . $e->getMessage());
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Run Daily Reconciliation')
                ->modalDescription('This will perform a full balance reconciliation for all accounts. Continue?'),
        ];
    }
    
    public function getTableRecords(): Collection|LengthAwarePaginator
    {
        // Get all reconciliation reports from file system
        $files = glob(storage_path('app/reconciliation/reconciliation-*.json'));
        
        $reports = collect($files)->map(function ($file) {
            $content = json_decode(file_get_contents($file), true);
            return $content['summary'] ?? [];
        })->filter()->sortByDesc('date');
        
        // Convert to paginator
        $page = request()->get('page', 1);
        $perPage = 10;
        $slice = $reports->slice(($page - 1) * $perPage, $perPage);
        
        return new LengthAwarePaginator(
            $slice,
            $reports->count(),
            $perPage,
            $page,
            ['path' => request()->url()]
        );
    }
    
    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        // We're using file-based storage, so no query needed
        return null;
    }
}