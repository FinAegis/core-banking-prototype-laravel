<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserBankPreferenceResource\Pages;
use App\Filament\Admin\Resources\UserBankPreferenceResource\RelationManagers;
use App\Models\UserBankPreference;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserBankPreferenceResource extends Resource
{
    protected static ?string $model = UserBankPreference::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    
    protected static ?string $navigationLabel = 'Bank Allocations';
    
    protected static ?string $modelLabel = 'Bank Allocation';
    
    protected static ?string $pluralModelLabel = 'Bank Allocations';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_uuid')
                    ->label('User')
                    ->options(User::all()->pluck('name', 'uuid'))
                    ->searchable()
                    ->required()
                    ->disabled(fn ($context) => $context === 'edit'),
                Forms\Components\Select::make('bank_code')
                    ->label('Bank')
                    ->options(collect(UserBankPreference::AVAILABLE_BANKS)->mapWithKeys(function ($bank) {
                        return [$bank['code'] => $bank['name'] . ' (' . $bank['country'] . ')'];
                    }))
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state && isset(UserBankPreference::AVAILABLE_BANKS[$state])) {
                            $bank = UserBankPreference::AVAILABLE_BANKS[$state];
                            $set('bank_name', $bank['name']);
                            $set('metadata', $bank);
                        }
                    }),
                Forms\Components\Hidden::make('bank_name'),
                Forms\Components\TextInput::make('allocation_percentage')
                    ->label('Allocation %')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->helperText('Percentage of funds to allocate to this bank'),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary Bank')
                    ->helperText('Primary bank will be used for urgent transfers'),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                    ])
                    ->default('active')
                    ->required(),
                Forms\Components\KeyValue::make('metadata')
                    ->label('Bank Information')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->searchable()
                    ->description(fn (UserBankPreference $record): string => 
                        $record->metadata['country'] ?? $record->bank_code
                    ),
                Tables\Columns\TextColumn::make('allocation_percentage')
                    ->label('Allocation')
                    ->numeric()
                    ->sortable()
                    ->suffix('%')
                    ->color(fn (UserBankPreference $record): string => 
                        $record->allocation_percentage > 50 ? 'warning' : 'success'
                    ),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'pending',
                        'danger' => 'suspended',
                    ]),
                Tables\Columns\TextColumn::make('metadata.deposit_insurance')
                    ->label('Insurance')
                    ->money('EUR')
                    ->getStateUsing(fn (UserBankPreference $record): ?int => 
                        $record->metadata['deposit_insurance'] ?? null
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                    ]),
                Tables\Filters\Filter::make('is_primary')
                    ->query(fn (Builder $query): Builder => $query->where('is_primary', true))
                    ->label('Primary Banks Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('setPrimary')
                    ->label('Set as Primary')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (UserBankPreference $record): bool => !$record->is_primary && $record->status === 'active')
                    ->action(function (UserBankPreference $record) {
                        $service = app(\App\Domain\Account\Services\BankAllocationService::class);
                        $service->setPrimaryBank($record->user, $record->bank_code);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('user.name')
            ->groups([
                'user.name',
                'bank_code',
                'status',
            ]);
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
            'index' => Pages\ListUserBankPreferences::route('/'),
            'create' => Pages\CreateUserBankPreference::route('/create'),
            'edit' => Pages\EditUserBankPreference::route('/{record}/edit'),
        ];
    }
}
