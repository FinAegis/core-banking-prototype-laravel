<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    
    protected static ?string $navigationGroup = 'System';
    
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'API Keys';
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Key Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->rows(3),
                        Forms\Components\TextInput::make('key_prefix')
                            ->label('Key Preview')
                            ->disabled(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active'),
                    ]),
                    
                Forms\Components\Section::make('Permissions & Security')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->options([
                                'read' => 'Read',
                                'write' => 'Write',
                                'delete' => 'Delete',
                                '*' => 'All Permissions',
                            ])
                            ->columns(2),
                        Forms\Components\TagsInput::make('allowed_ips')
                            ->label('IP Whitelist')
                            ->placeholder('Add IP address'),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiration Date'),
                    ]),
                    
                Forms\Components\Section::make('Usage Information')
                    ->schema([
                        Forms\Components\Placeholder::make('user')
                            ->label('Owner')
                            ->content(fn (ApiKey $record): string => $record->user->name ?? 'N/A'),
                        Forms\Components\Placeholder::make('last_used_at')
                            ->label('Last Used')
                            ->content(fn (ApiKey $record): string => $record->last_used_at?->diffForHumans() ?? 'Never'),
                        Forms\Components\Placeholder::make('request_count')
                            ->label('Total Requests')
                            ->content(fn (ApiKey $record): string => number_format($record->request_count)),
                    ])
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key_prefix')
                    ->label('Key')
                    ->formatStateUsing(fn (string $state): string => $state . '...'),
                Tables\Columns\BadgeColumn::make('permissions')
                    ->colors([
                        'success' => 'read',
                        'warning' => 'write',
                        'danger' => 'delete',
                        'primary' => '*',
                    ]),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('request_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('expired')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<', now())),
                Tables\Filters\Filter::make('never_used')
                    ->query(fn (Builder $query): Builder => $query->whereNull('last_used_at')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('revoke')
                    ->action(fn (ApiKey $record) => $record->revoke())
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-m-x-circle')
                    ->visible(fn (ApiKey $record): bool => $record->is_active),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('revoke')
                        ->action(fn ($records) => $records->each->revoke())
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-m-x-circle'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiKeys::route('/'),
            'view' => Pages\ViewApiKey::route('/{record}'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}