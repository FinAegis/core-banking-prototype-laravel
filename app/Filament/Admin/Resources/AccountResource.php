<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Account\Models\Account;
use App\Filament\Admin\Resources\AccountResource\Pages;
use App\Filament\Admin\Resources\AccountResource\RelationManagers;
use Exception;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Bank Accounts';

    protected static ?string $modelLabel = 'Bank Account';

    protected static ?string $navigationGroup = 'Banking';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Section::make('Account Information')
                        ->description('Basic account details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('uuid')
                                    ->label('Account UUID')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visibleOn('edit'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Account Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., John Doe Savings'),
                                Forms\Components\TextInput::make('user_uuid')
                                    ->label('User UUID')
                                    ->required()
                                    ->placeholder('UUID of the account owner')
                                    ->helperText('The unique identifier of the user who owns this account'),
                            ]
                        )->columns(2),

                    Section::make('Financial Details')
                        ->description('Account balance and status')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('balance')
                                    ->label('Current Balance')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Balance can only be modified through transactions'),
                                Forms\Components\Toggle::make('frozen')
                                    ->label('Account Frozen')
                                    ->helperText('Frozen accounts cannot perform transactions')
                                    ->reactive()
                                    ->afterStateUpdated(
                                        function ($state, $old) {
                                            if ($state !== $old && $old !== null) {
                                                Notification::make()
                                                    ->title($state ? 'Account will be frozen' : 'Account will be unfrozen')
                                                    ->warning()
                                                    ->send();
                                            }
                                        }
                                    ),
                            ]
                        )->columns(2),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('uuid')
                        ->label('Account ID')
                        ->copyable()
                        ->copyMessage('Account ID copied')
                        ->copyMessageDuration(1500)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('name')
                        ->label('Account Name')
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('user_uuid')
                        ->label('User ID')
                        ->copyable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('balance')
                        ->label('Balance')
                        ->money('USD', 100)
                        ->sortable()
                        ->color(fn ($state): string => $state < 0 ? 'danger' : 'success')
                        ->weight('bold'),
                    Tables\Columns\IconColumn::make('frozen')
                        ->label('Status')
                        ->boolean()
                        ->trueIcon('heroicon-o-lock-closed')
                        ->falseIcon('heroicon-o-lock-open')
                        ->trueColor('danger')
                        ->falseColor('success'),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime('M j, Y g:i A')
                        ->sortable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('updated_at')
                        ->label('Last Updated')
                        ->dateTime('M j, Y g:i A')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]
            )
            ->defaultSort('created_at', 'desc')
            ->filters(
                [
                    SelectFilter::make('frozen')
                        ->label('Account Status')
                        ->options(
                            [
                                '0' => 'Active',
                                '1' => 'Frozen',
                            ]
                        ),
                    Filter::make('balance')
                        ->form(
                            [
                                Forms\Components\Select::make('balance_operator')
                                    ->label('Balance')
                                    ->options(
                                        [
                                            '>' => 'Greater than',
                                            '<' => 'Less than',
                                            '=' => 'Equal to',
                                        ]
                                    )
                                    ->default('>')
                                    ->required(),
                                Forms\Components\TextInput::make('balance_amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('$')
                                    ->required(),
                            ]
                        )
                        ->query(
                            function (Builder $query, array $data): Builder {
                                return $query
                                    ->when(
                                        $data['balance_amount'] ?? null,
                                        fn (Builder $query, $amount): Builder => $query->where(
                                            'balance',
                                            $data['balance_operator'] ?? '>',
                                            $amount * 100
                                        ),
                                    );
                            }
                        )
                        ->indicateUsing(
                            function (array $data): ?string {
                                if (! $data['balance_amount']) {
                                    return null;
                                }

                                return 'Balance ' . $data['balance_operator'] . ' $' . number_format($data['balance_amount'], 2);
                            }
                        ),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('deposit')
                        ->label('Deposit')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->form(
                            [
                                Forms\Components\TextInput::make('amount')
                                    ->label('Deposit Amount')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->prefix('$')
                                    ->helperText('Enter the amount to deposit'),
                            ]
                        )
                        ->action(
                            function (Account $record, array $data): void {
                                try {
                                    DB::beginTransaction();

                                    $accountService = app(\App\Domain\Account\Services\AccountService::class);
                                    $accountService->deposit($record->uuid, (int) ($data['amount'] * 100));

                                    DB::commit();

                                    Notification::make()
                                        ->title('Deposit Successful')
                                        ->success()
                                        ->body('$' . number_format($data['amount'], 2) . ' has been deposited.')
                                        ->send();
                                } catch (Exception $e) {
                                    DB::rollBack();

                                    Notification::make()
                                        ->title('Deposit Failed')
                                        ->danger()
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            }
                        )
                        ->visible(fn (Account $record): bool => ! $record->frozen),
                    Tables\Actions\Action::make('withdraw')
                        ->label('Withdraw')
                        ->icon('heroicon-o-minus-circle')
                        ->color('warning')
                        ->form(
                            [
                                Forms\Components\TextInput::make('amount')
                                    ->label('Withdrawal Amount')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->prefix('$')
                                    ->helperText('Enter the amount to withdraw'),
                            ]
                        )
                        ->action(
                            function (Account $record, array $data): void {
                                try {
                                    DB::beginTransaction();

                                    $accountService = app(\App\Domain\Account\Services\AccountService::class);
                                    $accountService->withdraw($record->uuid, (int) ($data['amount'] * 100));

                                    DB::commit();

                                    Notification::make()
                                        ->title('Withdrawal Successful')
                                        ->success()
                                        ->body('$' . number_format($data['amount'], 2) . ' has been withdrawn.')
                                        ->send();
                                } catch (Exception $e) {
                                    DB::rollBack();

                                    Notification::make()
                                        ->title('Withdrawal Failed')
                                        ->danger()
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            }
                        )
                        ->visible(fn (Account $record): bool => ! $record->frozen && $record->balance > 0),
                    Tables\Actions\Action::make('freeze')
                        ->label('Freeze')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Freeze Account')
                        ->modalDescription('Are you sure you want to freeze this account? This will prevent all transactions.')
                        ->modalSubmitActionLabel('Yes, freeze account')
                        ->action(
                            function (Account $record): void {
                                try {
                                    $accountService = app(\App\Domain\Account\Services\AccountService::class);
                                    $accountService->freeze($record->uuid);

                                    Notification::make()
                                        ->title('Account Frozen')
                                        ->success()
                                        ->body('The account has been frozen successfully.')
                                        ->send();
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Failed to Freeze Account')
                                        ->danger()
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            }
                        )
                        ->visible(fn (Account $record): bool => ! $record->frozen),
                    Tables\Actions\Action::make('unfreeze')
                        ->label('Unfreeze')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Unfreeze Account')
                        ->modalDescription('Are you sure you want to unfreeze this account? This will allow transactions again.')
                        ->modalSubmitActionLabel('Yes, unfreeze account')
                        ->action(
                            function (Account $record): void {
                                try {
                                    $accountService = app(\App\Domain\Account\Services\AccountService::class);
                                    $accountService->unfreeze($record->uuid);

                                    Notification::make()
                                        ->title('Account Unfrozen')
                                        ->success()
                                        ->body('The account has been unfrozen successfully.')
                                        ->send();
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Failed to Unfreeze Account')
                                        ->danger()
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            }
                        )
                        ->visible(fn (Account $record): bool => $record->frozen),
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkActionGroup::make(
                        [
                            Tables\Actions\BulkAction::make('freeze')
                                ->label('Freeze Selected')
                                ->icon('heroicon-o-lock-closed')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->action(
                                    function ($records): void {
                                        $accountService = app(\App\Domain\Account\Services\AccountService::class);
                                        $success = 0;
                                        $failed = 0;

                                        foreach ($records as $record) {
                                            if (! $record->frozen) {
                                                try {
                                                    $accountService->freeze($record->uuid);
                                                    $success++;
                                                } catch (Exception $e) {
                                                    $failed++;
                                                }
                                            }
                                        }

                                        if ($success > 0) {
                                            Notification::make()
                                                ->title('Accounts Frozen')
                                                ->success()
                                                ->body("{$success} account(s) frozen successfully.")
                                                ->send();
                                        }

                                        if ($failed > 0) {
                                            Notification::make()
                                                ->title('Some Freezes Failed')
                                                ->warning()
                                                ->body("{$failed} account(s) could not be frozen.")
                                                ->send();
                                        }
                                    }
                                ),
                        ]
                    ),
                ]
            );
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\TurnoversRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            AccountResource\Widgets\AccountStatsOverview::class,
            AccountResource\Widgets\AccountBalanceChart::class,
            AccountResource\Widgets\RecentTransactionsChart::class,
            AccountResource\Widgets\TurnoverTrendChart::class,
            AccountResource\Widgets\AccountGrowthChart::class,
            AccountResource\Widgets\SystemHealthWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit'   => Pages\EditAccount::route('/{record}/edit'),
            'view'   => Pages\ViewAccount::route('/{record}'),
        ];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'User'    => $record->user_uuid,
            'Balance' => '$' . number_format($record->balance / 100, 2),
            'Status'  => $record->frozen ? 'Frozen' : 'Active',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['uuid', 'name', 'user_uuid'];
    }
}
