<?php

namespace App\Filament\Admin\Pages;

use App\Services\SubProductService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class SubProducts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    
    protected static string $view = 'filament.admin.pages.sub-products';
    
    protected static ?string $navigationGroup = 'System';
    
    protected static ?int $navigationSort = 90;
    
    protected static ?string $title = 'Sub-Product Configuration';
    
    public ?array $data = [];
    
    protected SubProductService $subProductService;
    
    public function boot(): void
    {
        $this->subProductService = app(SubProductService::class);
    }
    
    public function mount(): void
    {
        $this->fillForm();
    }
    
    protected function fillForm(): void
    {
        $formData = [];
        $subProducts = $this->subProductService->getAllSubProducts();
        
        foreach ($subProducts as $key => $config) {
            $formData["{$key}_enabled"] = $config['is_enabled'];
            
            foreach ($config['features'] as $feature => $default) {
                $formData["{$key}_{$feature}"] = $this->subProductService->isFeatureEnabled($key, $feature);
            }
        }
        
        $this->form->fill($formData);
    }
    
    public function form(Form $form): Form
    {
        $schema = [];
        $subProducts = config('sub_products', []);
        
        foreach ($subProducts as $key => $config) {
            $featureFields = [];
            
            foreach ($config['features'] as $feature => $default) {
                $featureFields[] = Forms\Components\Toggle::make("{$key}_{$feature}")
                    ->label(str($feature)->replace('_', ' ')->title())
                    ->helperText("Enable {$feature} functionality")
                    ->disabled(fn (Forms\Get $get) => !$get("{$key}_enabled"))
                    ->columnSpan(1);
            }
            
            $schema[] = Forms\Components\Section::make($config['name'])
                ->description($config['description'])
                ->icon($config['icon'] ?? 'heroicon-o-squares-2x2')
                ->schema([
                    Forms\Components\Toggle::make("{$key}_enabled")
                        ->label('Enable ' . $config['name'])
                        ->helperText("Enable or disable the entire {$config['name']} sub-product")
                        ->reactive()
                        ->afterStateUpdated(function ($state, Forms\Set $set) use ($key, $config) {
                            if (!$state) {
                                // Disable all features when sub-product is disabled
                                foreach ($config['features'] as $feature => $default) {
                                    $set("{$key}_{$feature}", false);
                                }
                            }
                        })
                        ->columnSpanFull(),
                    
                    Forms\Components\Grid::make(2)
                        ->schema($featureFields)
                        ->visible(fn (Forms\Get $get) => $get("{$key}_enabled")),
                    
                    Forms\Components\Placeholder::make("{$key}_licenses")
                        ->label('Required Licenses')
                        ->content(implode(', ', $config['licenses']))
                        ->visible(fn (Forms\Get $get) => $get("{$key}_enabled")),
                    
                    Forms\Components\Placeholder::make("{$key}_status")
                        ->label('Status')
                        ->content(fn (Forms\Get $get) => $get("{$key}_enabled") ? 
                            '<span class="text-success-600">Active</span>' : 
                            '<span class="text-danger-600">Inactive</span>')
                        ->extraAttributes(['class' => 'font-semibold'])
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->persistCollapsed();
        }
        
        return $form
            ->schema($schema)
            ->statePath('data');
    }
    
    public function save(): void
    {
        $data = $this->form->getState();
        $subProducts = config('sub_products', []);
        $currentUser = auth()->user()->email ?? 'system';
        
        foreach ($subProducts as $key => $config) {
            // Handle sub-product enable/disable
            $isEnabled = $data["{$key}_enabled"] ?? false;
            
            if ($isEnabled && !$this->subProductService->isEnabled($key)) {
                $this->subProductService->enableSubProduct($key, $currentUser);
            } elseif (!$isEnabled && $this->subProductService->isEnabled($key)) {
                $this->subProductService->disableSubProduct($key, $currentUser);
            }
            
            // Handle features
            foreach ($config['features'] as $feature => $default) {
                $featureEnabled = $data["{$key}_{$feature}"] ?? false;
                
                if ($isEnabled && $featureEnabled && !$this->subProductService->isFeatureEnabled($key, $feature)) {
                    $this->subProductService->enableFeature($key, $feature, $currentUser);
                } elseif ((!$isEnabled || !$featureEnabled) && $this->subProductService->isFeatureEnabled($key, $feature)) {
                    $this->subProductService->disableFeature($key, $feature, $currentUser);
                }
            }
        }
        
        $this->subProductService->clearCache();
        
        Notification::make()
            ->title('Sub-product configuration saved')
            ->success()
            ->send();
            
        Log::info('Sub-product configuration updated', [
            'user' => $currentUser,
            'changes' => $data,
        ]);
    }
    
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('view_status')
                ->label('View API Status')
                ->icon('heroicon-o-eye')
                ->modalContent(fn () => view('filament.modals.sub-product-status', [
                    'status' => $this->subProductService->getApiStatus(),
                ]))
                ->modalWidth('2xl'),
        ];
    }
}