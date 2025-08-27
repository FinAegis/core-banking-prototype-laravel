<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Services\GovernanceService;
use App\Filament\Admin\Resources\PollResource\Pages;
use App\Filament\Admin\Resources\PollResource\RelationManagers;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PollResource extends Resource
{
    protected static ?string $model = Poll::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Governance';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Poll Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('type')
                                    ->required()
                                    ->options(
                                        [
                                            PollType::YES_NO->value          => 'Yes/No',
                                            PollType::SINGLE_CHOICE->value   => 'Single Choice',
                                            PollType::MULTIPLE_CHOICE->value => 'Multiple Choice',
                                            PollType::WEIGHTED_CHOICE->value => 'Weighted Choice',
                                            PollType::RANKED_CHOICE->value   => 'Ranked Choice',
                                        ]
                                    )
                                    ->reactive()
                                    ->afterStateUpdated(
                                        function ($state, callable $set) {
                                            // Reset options when type changes
                                            if ($state === PollType::YES_NO->value) {
                                                $set(
                                                    'options',
                                                    [
                                                        ['id' => 'yes', 'label' => 'Yes', 'description' => 'I support this proposal'],
                                                        ['id' => 'no', 'label' => 'No', 'description' => 'I do not support this proposal'],
                                                    ]
                                                );
                                            } else {
                                                $set('options', []);
                                            }
                                        }
                                    ),

                                Forms\Components\Select::make('voting_power_strategy')
                                    ->required()
                                    ->options(
                                        [
                                            'one_user_one_vote'   => 'One User One Vote',
                                            'asset_weighted_vote' => 'Asset Weighted Vote',
                                        ]
                                    )
                                    ->default('one_user_one_vote'),

                                Forms\Components\TextInput::make('required_participation')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->helperText('Minimum participation rate required for execution'),
                            ]
                        )
                        ->columns(2),

                    Forms\Components\Section::make('Poll Options')
                        ->schema(
                            [
                                Forms\Components\Repeater::make('options')
                                    ->schema(
                                        [
                                            Forms\Components\TextInput::make('id')
                                                ->required()
                                                ->helperText('Unique identifier for this option'),

                                            Forms\Components\TextInput::make('label')
                                                ->required()
                                                ->helperText('Display name for this option'),

                                            Forms\Components\TextInput::make('description')
                                                ->helperText('Optional description for this option'),
                                        ]
                                    )
                                    ->columns(3)
                                    ->required()
                                    ->minItems(1)
                                    ->addActionLabel('Add Option')
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]
                        ),

                    Forms\Components\Section::make('Schedule & Execution')
                        ->schema(
                            [
                                Forms\Components\DateTimePicker::make('start_date')
                                    ->required()
                                    ->seconds(false)
                                    ->default(now()->addHour()),

                                Forms\Components\DateTimePicker::make('end_date')
                                    ->required()
                                    ->seconds(false)
                                    ->default(now()->addWeek())
                                    ->after('start_date'),

                                Forms\Components\Select::make('execution_workflow')
                                    ->options(
                                        [
                                            'AddAssetWorkflow'            => 'Add Asset Workflow',
                                            'FeatureToggleWorkflow'       => 'Feature Toggle Workflow',
                                            'UpdateConfigurationWorkflow' => 'Update Configuration Workflow',
                                        ]
                                    )
                                    ->helperText('Optional workflow to execute when poll passes'),

                                Forms\Components\Select::make('status')
                                    ->required()
                                    ->options(
                                        [
                                            PollStatus::DRAFT->value     => 'Draft',
                                            PollStatus::ACTIVE->value    => 'Active',
                                            PollStatus::CLOSED->value    => 'Closed',
                                            PollStatus::CANCELLED->value => 'Cancelled',
                                        ]
                                    )
                                    ->default(PollStatus::DRAFT->value),
                            ]
                        )
                        ->columns(2),

                    Forms\Components\Section::make('Metadata')
                        ->schema(
                            [
                                Forms\Components\KeyValue::make('metadata')
                                    ->keyLabel('Key')
                                    ->valueLabel('Value')
                                    ->addActionLabel('Add metadata')
                                    ->columnSpanFull(),
                            ]
                        )
                        ->collapsible()
                        ->collapsed(),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('title')
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),

                    Tables\Columns\BadgeColumn::make('type')
                        ->colors(
                            [
                                'primary' => PollType::YES_NO->value,
                                'success' => PollType::SINGLE_CHOICE->value,
                                'warning' => PollType::MULTIPLE_CHOICE->value,
                                'danger'  => PollType::WEIGHTED_CHOICE->value,
                                'gray'    => PollType::RANKED_CHOICE->value,
                            ]
                        ),

                    Tables\Columns\BadgeColumn::make('status')
                        ->colors(
                            [
                                'gray'    => PollStatus::DRAFT->value,
                                'success' => PollStatus::ACTIVE->value,
                                'danger'  => PollStatus::CLOSED->value,
                                'warning' => PollStatus::CANCELLED->value,
                            ]
                        ),

                    Tables\Columns\TextColumn::make('creator.name')
                        ->label('Created By')
                        ->sortable()
                        ->toggleable(),

                    Tables\Columns\TextColumn::make('votes_count')
                        ->label('Votes')
                        ->counts('votes')
                        ->sortable(),

                    Tables\Columns\TextColumn::make('total_voting_power')
                        ->label('Total Power')
                        ->getStateUsing(fn (Poll $record) => $record->getTotalVotingPower())
                        ->sortable(false),

                    Tables\Columns\TextColumn::make('start_date')
                        ->label('Starts')
                        ->dateTime('M j, Y H:i')
                        ->sortable()
                        ->toggleable(),

                    Tables\Columns\TextColumn::make('end_date')
                        ->label('Ends')
                        ->dateTime('M j, Y H:i')
                        ->sortable()
                        ->color(fn (Poll $record) => $record->isExpired() ? 'danger' : 'success'),

                    Tables\Columns\TextColumn::make('voting_power_strategy')
                        ->label('Strategy')
                        ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state)))
                        ->toggleable(isToggledHiddenByDefault: true),

                    Tables\Columns\TextColumn::make('execution_workflow')
                        ->label('Workflow')
                        ->toggleable(isToggledHiddenByDefault: true),
                ]
            )
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            [
                                PollStatus::DRAFT->value     => 'Draft',
                                PollStatus::ACTIVE->value    => 'Active',
                                PollStatus::CLOSED->value    => 'Closed',
                                PollStatus::CANCELLED->value => 'Cancelled',
                            ]
                        ),

                    Tables\Filters\SelectFilter::make('type')
                        ->options(
                            [
                                PollType::YES_NO->value          => 'Yes/No',
                                PollType::SINGLE_CHOICE->value   => 'Single Choice',
                                PollType::MULTIPLE_CHOICE->value => 'Multiple Choice',
                                PollType::WEIGHTED_CHOICE->value => 'Weighted Choice',
                                PollType::RANKED_CHOICE->value   => 'Ranked Choice',
                            ]
                        ),

                    Tables\Filters\Filter::make('active')
                        ->query(fn (Builder $query) => $query->active())
                        ->label('Active Polls'),

                    Tables\Filters\Filter::make('expired')
                        ->query(fn (Builder $query) => $query->expired())
                        ->label('Expired Polls'),
                ]
            )
            ->actions(
                [
                    Tables\Actions\Action::make('activate')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (Poll $record) => $record->status === PollStatus::DRAFT)
                        ->requiresConfirmation()
                        ->action(
                            function (Poll $record) {
                                try {
                                    app(GovernanceService::class)->activatePoll($record);
                                    Notification::make()
                                        ->title('Poll Activated')
                                        ->success()
                                        ->send();
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Failed to Activate Poll')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        ),

                    Tables\Actions\Action::make('cancel')
                        ->icon('heroicon-o-x-mark')
                        ->color('warning')
                        ->visible(fn (Poll $record) => in_array($record->status, [PollStatus::DRAFT, PollStatus::ACTIVE]))
                        ->requiresConfirmation()
                        ->form(
                            [
                                Forms\Components\Textarea::make('reason')
                                    ->label('Cancellation Reason')
                                    ->required()
                                    ->rows(3),
                            ]
                        )
                        ->action(
                            function (Poll $record, array $data) {
                                try {
                                    app(GovernanceService::class)->cancelPoll($record, $data['reason']);
                                    Notification::make()
                                        ->title('Poll Cancelled')
                                        ->success()
                                        ->send();
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Failed to Cancel Poll')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        ),

                    Tables\Actions\Action::make('complete')
                        ->icon('heroicon-o-check')
                        ->color('primary')
                        ->visible(fn (Poll $record) => $record->status === PollStatus::ACTIVE && $record->isExpired())
                        ->requiresConfirmation()
                        ->action(
                            function (Poll $record) {
                                try {
                                    $result = app(GovernanceService::class)->completePoll($record);
                                    Notification::make()
                                        ->title('Poll Completed')
                                        ->body("Winner: {$result->winningOption} with {$result->totalVotes} votes")
                                        ->success()
                                        ->send();
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Failed to Complete Poll')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        ),

                    // Tables\Actions\Action::make('results')
                    //     ->icon('heroicon-o-chart-pie')
                    //     ->color('gray')
                    //     ->url(fn (Poll $record) => route('filament.admin.resources.polls.results', $record))
                    //     ->openUrlInNewTab(),

                    // Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkActionGroup::make(
                        [
                            Tables\Actions\BulkAction::make('activate')
                                ->icon('heroicon-o-play')
                                ->color('success')
                                ->requiresConfirmation()
                                ->action(
                                    function ($records) {
                                        $governanceService = app(GovernanceService::class);
                                        $success = 0;
                                        $errors = 0;

                                        foreach ($records as $record) {
                                            if ($record->status === PollStatus::DRAFT) {
                                                try {
                                                    $governanceService->activatePoll($record);
                                                    $success++;
                                                } catch (Exception $e) {
                                                    $errors++;
                                                }
                                            }
                                        }

                                        Notification::make()
                                            ->title("Activated {$success} polls" . ($errors > 0 ? " ({$errors} failed)" : ''))
                                            ->success()
                                            ->send();
                                    }
                                ),

                            Tables\Actions\DeleteBulkAction::make(),
                        ]
                    ),
                ]
            )
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\VotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPolls::route('/'),
            'create' => Pages\CreatePoll::route('/create'),
            'edit'   => Pages\EditPoll::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['creator']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description', 'creator.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Creator' => $record->creator?->name,
            'Status'  => $record->status->value,
            'Type'    => $record->type->value,
        ];
    }
}
