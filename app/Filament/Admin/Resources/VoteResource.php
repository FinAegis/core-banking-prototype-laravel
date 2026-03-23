<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Filament\Admin\Resources\VoteResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VoteResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = Vote::class;

    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';

    protected static ?string $navigationGroup = 'Governance';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Vote Details')
                        ->schema(
                            [
                                Forms\Components\Select::make('poll_id')
                                    ->label('Poll')
                                    ->options(fn () => Poll::pluck('title', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(
                                        function ($state, callable $set) {
                                            $poll = Poll::find($state);
                                            if ($poll) {
                                                $set('available_options', collect($poll->options)->pluck('label', 'id')->toArray());
                                            }
                                        }
                                    ),

                                Forms\Components\Select::make('user_uuid')
                                    ->label('User')
                                    ->options(fn () => User::pluck('name', 'uuid'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\CheckboxList::make('selected_options')
                                    ->label('Selected Options')
                                    ->options(
                                        function (callable $get) {
                                            $pollId = $get('poll_id');
                                            if (! $pollId) {
                                                return [];
                                            }

                                            $poll = Poll::find($pollId);
                                            if (! $poll) {
                                                return [];
                                            }

                                            return collect($poll->options)->pluck('label', 'id')->toArray();
                                        }
                                    )
                                    ->required()
                                    ->columns(2),

                                Forms\Components\TextInput::make('voting_power')
                                    ->label('Voting Power')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1),
                            ]
                        )
                        ->columns(2),

                    Forms\Components\Section::make('Vote Metadata')
                        ->schema(
                            [
                                Forms\Components\DateTimePicker::make('voted_at')
                                    ->label('Vote Cast At')
                                    ->default(now())
                                    ->required(),

                                Forms\Components\TextInput::make('signature')
                                    ->label('Cryptographic Signature')
                                    ->disabled()
                                    ->helperText('Automatically generated when vote is saved'),

                                Forms\Components\KeyValue::make('metadata')
                                    ->label('Additional Metadata')
                                    ->keyLabel('Key')
                                    ->valueLabel('Value')
                                    ->addActionLabel('Add metadata')
                                    ->columnSpanFull(),
                            ]
                        )
                        ->columns(2)
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
                    Tables\Columns\TextColumn::make('poll.title')
                        ->label('Poll')
                        ->searchable()
                        ->sortable()
                        ->limit(30),

                    Tables\Columns\TextColumn::make('user.name')
                        ->label('Voter')
                        ->searchable()
                        ->sortable(),

                    Tables\Columns\TextColumn::make('selected_options_string')
                        ->label('Vote')
                        ->getStateUsing(fn (Vote $record) => $record->getSelectedOptionsAsString())
                        ->badge()
                        ->separator(','),

                    Tables\Columns\TextColumn::make('voting_power')
                        ->label('Power')
                        ->sortable()
                        ->alignCenter(),

                    Tables\Columns\TextColumn::make('voting_power_weight')
                        ->label('Weight')
                        ->getStateUsing(fn (Vote $record) => number_format($record->getVotingPowerWeight(), 1) . '%')
                        ->sortable(false)
                        ->alignCenter(),

                    Tables\Columns\IconColumn::make('is_valid')
                        ->label('Valid')
                        ->getStateUsing(fn (Vote $record) => $record->isValid())
                        ->boolean()
                        ->sortable(false),

                    Tables\Columns\TextColumn::make('voted_at')
                        ->label('Cast At')
                        ->dateTime('M j, Y H:i')
                        ->sortable(),

                    Tables\Columns\TextColumn::make('poll.status')
                        ->label('Poll Status')
                        ->badge()
                        ->colors(
                            [
                                'gray'    => 'draft',
                                'success' => 'active',
                                'danger'  => 'closed',
                                'warning' => 'cancelled',
                            ]
                        )
                        ->sortable(),
                ]
            )
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('poll_id')
                        ->label('Poll')
                        ->options(fn () => Poll::pluck('title', 'id'))
                        ->searchable()
                        ->preload(),

                    Tables\Filters\SelectFilter::make('user_uuid')
                        ->label('User')
                        ->options(fn () => User::pluck('name', 'uuid'))
                        ->searchable()
                        ->preload(),

                    Tables\Filters\Filter::make('high_voting_power')
                        ->query(fn (Builder $query) => $query->where('voting_power', '>', 10))
                        ->label('High Voting Power (>10)'),

                    Tables\Filters\Filter::make('recent_votes')
                        ->query(fn (Builder $query) => $query->where('voted_at', '>=', now()->subDays(7)))
                        ->label('Recent Votes (7 days)'),

                    Tables\Filters\Filter::make('invalid_votes')
                        ->query(fn (Builder $query) => $query->whereRaw('voting_power <= 0 OR selected_options = "[]"'))
                        ->label('Invalid Votes'),
                ]
            )
            ->actions(
                [
                    Tables\Actions\Action::make('verify_signature')
                        ->icon('heroicon-o-shield-check')
                        ->color('primary')
                        ->action(
                            function (Vote $record) {
                                $isValid = $record->verifySignature();

                                \Filament\Notifications\Notification::make()
                                    ->title($isValid ? 'Signature Valid' : 'Signature Invalid')
                                    ->body($isValid ? 'Vote signature is cryptographically valid' : 'Vote signature verification failed')
                                    ->color($isValid ? 'success' : 'danger')
                                    ->send();
                            }
                        ),

                    // Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkActionGroup::make(
                        [
                            Tables\Actions\BulkAction::make('verify_signatures')
                                ->icon('heroicon-o-shield-check')
                                ->color('primary')
                                ->action(
                                    function ($records) {
                                        $valid = 0;
                                        $invalid = 0;

                                        foreach ($records as $record) {
                                            if ($record->verifySignature()) {
                                                $valid++;
                                            } else {
                                                $invalid++;
                                            }
                                        }

                                        \Filament\Notifications\Notification::make()
                                            ->title('Signature Verification Complete')
                                            ->body("Valid: {$valid}, Invalid: {$invalid}")
                                            ->color($invalid > 0 ? 'warning' : 'success')
                                            ->send();
                                    }
                                ),

                            Tables\Actions\DeleteBulkAction::make(),
                        ]
                    ),
                ]
            )
            ->defaultSort('voted_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVotes::route('/'),
            'create' => Pages\CreateVote::route('/create'),
            'edit'   => Pages\EditVote::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['poll', 'user']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['poll.title', 'user.name', 'selected_options'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Poll' => $record->poll?->title,
            'User' => $record->user?->name,
            'Vote' => $record->getSelectedOptionsAsString(),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Votes should be created through the API, not admin panel
    }
}
