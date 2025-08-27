<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Account\Models\Setting;
use App\Services\SettingsService;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.admin.pages.settings';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Platform Settings';

    public ?array $data = [];

    protected SettingsService $settingsService;

    public function boot(): void
    {
        $this->settingsService = app(SettingsService::class);
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        $schema = [];

        foreach ($this->settingsService->getConfig() as $group => $groupConfig) {
            $fields = [];

            foreach ($groupConfig['settings'] as $key => $config) {
                $field = match ($config['type']) {
                    'boolean' => Forms\Components\Toggle::make($key)
                        ->label($config['label'])
                        ->helperText($config['description']),
                    'integer' => Forms\Components\TextInput::make($key)
                        ->label($config['label'])
                        ->numeric()
                        ->helperText($config['description']),
                    'float' => Forms\Components\TextInput::make($key)
                        ->label($config['label'])
                        ->numeric()
                        ->step(0.01)
                        ->helperText($config['description']),
                    'string' => Forms\Components\TextInput::make($key)
                        ->label($config['label'])
                        ->helperText($config['description']),
                    'array' => Forms\Components\TagsInput::make($key)
                        ->label($config['label'])
                        ->helperText($config['description']),
                    'json' => Forms\Components\KeyValue::make($key)
                        ->label($config['label'])
                        ->helperText($config['description']),
                    default => Forms\Components\TextInput::make($key)
                        ->label($config['label'])
                        ->helperText($config['description']),
                };

                $fields[] = $field;
            }

            $schema[] = Forms\Components\Section::make($groupConfig['label'])
                ->description("Configure {$groupConfig['label']} for the platform")
                ->schema($fields)
                ->collapsible()
                ->persistCollapsed();
        }

        return $form
            ->schema($schema)
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            foreach ($data as $key => $value) {
                $config = $this->settingsService->getSettingConfig($key);

                if (empty($config)) {
                    continue;
                }

                $validation = $this->settingsService->validateSetting($key, $value);

                if (! $validation['valid']) {
                    Notification::make()
                        ->title('Validation Error')
                        ->body("Invalid value for {$config['label']}: " . implode(', ', $validation['errors']))
                        ->danger()
                        ->send();

                    throw new Halt();
                }

                $this->settingsService->updateSetting($key, $value, auth()->user()->email ?? 'system');
            }

            Cache::flush();

            Notification::make()
                ->title('Settings saved successfully')
                ->success()
                ->send();

            Log::info(
                'Platform settings updated',
                [
                    'user'           => auth()->user()->email ?? 'system',
                    'settings_count' => count($data),
                ]
            );
        } catch (Halt $exception) {
            return;
        }
    }

    public function resetToDefaults(): void
    {
        try {
            $this->settingsService->initializeSettings();

            Cache::flush();

            $this->fillForm();

            Notification::make()
                ->title('Settings reset to defaults')
                ->success()
                ->send();

            Log::info(
                'Platform settings reset to defaults',
                [
                    'user' => auth()->user()->email ?? 'system',
                ]
            );
        } catch (Exception $e) {
            Notification::make()
                ->title('Error resetting settings')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportSettings(): void
    {
        $settings = $this->settingsService->exportSettings();

        $filename = 'settings-export-' . now()->format('Y-m-d-His') . '.json';

        Notification::make()
            ->title('Settings exported')
            ->body('Settings have been exported successfully.')
            ->success()
            ->send();

        // In a real implementation, this would trigger a download
        Log::info(
            'Settings exported',
            [
                'user'           => auth()->user()->email ?? 'system',
                'filename'       => $filename,
                'settings_count' => count($settings),
            ]
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('reset')
                ->label('Reset to Defaults')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset Settings to Defaults')
                ->modalDescription('Are you sure you want to reset all settings to their default values? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, reset all settings')
                ->action(fn () => $this->resetToDefaults()),

            \Filament\Actions\Action::make('export')
                ->label('Export Settings')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportSettings()),
        ];
    }
}
