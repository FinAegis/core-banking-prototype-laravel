<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WebhookResource\Pages;
use App\Filament\Admin\Resources\WebhookResource\RelationManagers;
use App\Models\Webhook;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-top-right-on-square';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Webhook Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('url')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\CheckboxList::make('events')
                            ->options(Webhook::EVENTS)
                            ->columns(2)
                            ->required()
                            ->helperText('Select the events that will trigger this webhook'),
                        Forms\Components\TextInput::make('secret')
                            ->password()
                            ->maxLength(255)
                            ->helperText('Optional secret for webhook signature verification'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
                
                Forms\Components\Section::make('Advanced Settings')
                    ->schema([
                        Forms\Components\TextInput::make('retry_attempts')
                            ->numeric()
                            ->default(3)
                            ->minValue(0)
                            ->maxValue(10),
                        Forms\Components\TextInput::make('timeout_seconds')
                            ->numeric()
                            ->default(30)
                            ->minValue(5)
                            ->maxValue(300)
                            ->suffix('seconds'),
                        Forms\Components\KeyValue::make('headers')
                            ->label('Custom Headers')
                            ->keyLabel('Header Name')
                            ->valueLabel('Header Value')
                            ->addButtonLabel('Add Header')
                            ->helperText('Optional custom headers to include in webhook requests'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->url),
                Tables\Columns\TagsColumn::make('events')
                    ->limit(3),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->numeric()
                    ->label('Failures')
                    ->color(fn ($state) => $state > 0 ? ($state >= 5 ? 'danger' : 'warning') : 'gray'),
                Tables\Columns\TextColumn::make('last_triggered_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_success_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ])
                    ->label('Status'),
                Tables\Filters\Filter::make('has_failures')
                    ->query(fn (Builder $query): Builder => $query->where('consecutive_failures', '>', 0))
                    ->label('Has Failures'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-play')
                    ->color('gray')
                    ->action(function (Webhook $record) {
                        // Trigger a test webhook
                        $record->deliveries()->create([
                            'event_type' => 'test.webhook',
                            'payload' => [
                                'event' => 'test.webhook',
                                'timestamp' => now()->toIso8601String(),
                                'message' => 'This is a test webhook delivery',
                            ],
                            'status' => 'pending',
                        ]);
                        
                        Notification::make()
                            ->title('Test webhook created')
                            ->body('A test delivery has been queued for processing.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reset_failures')
                    ->label('Reset Failures')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->consecutive_failures > 0)
                    ->requiresConfirmation()
                    ->action(fn (Webhook $record) => $record->update([
                        'consecutive_failures' => 0,
                        'is_active' => true,
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhooks::route('/'),
            'create' => Pages\CreateWebhook::route('/create'),
            'edit' => Pages\EditWebhook::route('/{record}/edit'),
            'view' => Pages\ViewWebhook::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['deliveries', 'deliveries as failed_deliveries_count' => function ($query) {
                $query->where('status', 'failed');
            }]);
    }
}