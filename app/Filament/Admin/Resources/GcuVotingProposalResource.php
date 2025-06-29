<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GcuVotingProposalResource\Pages;
use App\Models\GcuVotingProposal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GcuVotingProposalResource extends Resource
{
    protected static ?string $model = GcuVotingProposal::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    
    protected static ?string $navigationGroup = 'GCU Management';
    
    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Voting Proposals';
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Proposal Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('rationale')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                    
                Forms\Components\Section::make('Voting Configuration')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'closed' => 'Closed',
                                'implemented' => 'Implemented',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                        Forms\Components\DateTimePicker::make('voting_starts_at')
                            ->required(),
                        Forms\Components\DateTimePicker::make('voting_ends_at')
                            ->required()
                            ->after('voting_starts_at'),
                        Forms\Components\TextInput::make('minimum_participation')
                            ->numeric()
                            ->suffix('%')
                            ->default(10)
                            ->minValue(1)
                            ->maxValue(100),
                        Forms\Components\TextInput::make('minimum_approval')
                            ->numeric()
                            ->suffix('%')
                            ->default(50)
                            ->minValue(1)
                            ->maxValue(100),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Composition')
                    ->schema([
                        Forms\Components\KeyValue::make('proposed_composition')
                            ->keyLabel('Currency')
                            ->valueLabel('Percentage')
                            ->addButtonLabel('Add Currency')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'active',
                        'danger' => 'rejected',
                        'warning' => 'closed',
                        'primary' => 'implemented',
                    ]),
                Tables\Columns\TextColumn::make('participation_rate')
                    ->label('Participation')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('approval_rate')
                    ->label('Approval')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('voting_starts_at')
                    ->label('Start')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('voting_ends_at')
                    ->label('End')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'closed' => 'Closed',
                        'implemented' => 'Implemented',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('close')
                    ->action(fn (GcuVotingProposal $record) => $record->update(['status' => 'closed']))
                    ->requiresConfirmation()
                    ->visible(fn (GcuVotingProposal $record) => $record->status === 'active'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListGcuVotingProposals::route('/'),
            'create' => Pages\CreateGcuVotingProposal::route('/create'),
            'edit' => Pages\EditGcuVotingProposal::route('/{record}/edit'),
        ];
    }
}