<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\VirtualsAgent\Enums\AgentStatus;
use App\Domain\VirtualsAgent\Models\VirtualsAgentProfile;
use App\Domain\VirtualsAgent\Services\AgentOnboardingService;
use App\Filament\Admin\Resources\VirtualsAgentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VirtualsAgentResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = VirtualsAgentProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'AI Agents';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Virtuals Agents';

    protected static ?string $modelLabel = 'Agent';

    protected static ?string $pluralModelLabel = 'Virtuals Agents';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Agent Identity')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('virtuals_agent_id')
                                    ->label('Virtuals Agent ID')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('agent_name')
                                    ->label('Agent Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('agent_description')
                                    ->label('Description')
                                    ->rows(2),
                                Forms\Components\Select::make('employer_user_id')
                                    ->label('Employer')
                                    ->relationship('employer', 'email')
                                    ->searchable()
                                    ->required(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Configuration')
                        ->schema(
                            [
                                Forms\Components\Select::make('status')
                                    ->options(
                                        collect(AgentStatus::cases())
                                            ->mapWithKeys(fn (AgentStatus $s) => [$s->value => ucfirst($s->value)])
                                            ->all()
                                    )
                                    ->required(),
                                Forms\Components\Select::make('chain')
                                    ->options([
                                        'base'     => 'Base',
                                        'polygon'  => 'Polygon',
                                        'arbitrum' => 'Arbitrum',
                                        'ethereum' => 'Ethereum',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('trustcert_subject_id')
                                    ->label('TrustCert Subject')
                                    ->disabled()
                                    ->placeholder('Auto-generated on activation'),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Spending Limits')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('x402_spending_limit_id')
                                    ->label('X402 Spending Limit ID')
                                    ->disabled()
                                    ->placeholder('Linked after onboarding'),
                                Forms\Components\TextInput::make('card_id')
                                    ->label('Card ID')
                                    ->disabled()
                                    ->placeholder('Provisioned separately'),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Timestamps')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('created_at')
                                    ->label('Registered')
                                    ->disabled(),
                                Forms\Components\TextInput::make('updated_at')
                                    ->label('Last Updated')
                                    ->disabled(),
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
                    Tables\Columns\TextColumn::make('agent_name')
                        ->label('Agent')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('virtuals_agent_id')
                        ->label('Virtuals ID')
                        ->searchable()
                        ->limit(16)
                        ->tooltip(fn ($record): string => $record->virtuals_agent_id),
                    Tables\Columns\TextColumn::make('employer.email')
                        ->label('Employer')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->color(
                            fn (string $state): string => match ($state) {
                                'active'      => 'success',
                                'registered'  => 'info',
                                'suspended'   => 'warning',
                                'deactivated' => 'danger',
                                default       => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('chain')
                        ->badge()
                        ->color('gray'),
                    Tables\Columns\TextColumn::make('x402_spending_limit_id')
                        ->label('Spending Limit')
                        ->formatStateUsing(fn ($state): string => $state ? 'Linked' : 'None')
                        ->color(fn ($state): string => $state ? 'success' : 'gray')
                        ->badge(),
                    Tables\Columns\TextColumn::make('card_id')
                        ->label('Card')
                        ->formatStateUsing(fn ($state): string => $state ? 'Provisioned' : 'None')
                        ->color(fn ($state): string => $state ? 'success' : 'gray')
                        ->badge(),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Registered')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(),
                ]
            )
            ->defaultSort('created_at', 'desc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            collect(AgentStatus::cases())
                                ->mapWithKeys(fn (AgentStatus $s) => [$s->value => ucfirst($s->value)])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('chain')
                        ->options([
                            'base'     => 'Base',
                            'polygon'  => 'Polygon',
                            'arbitrum' => 'Arbitrum',
                            'ethereum' => 'Ethereum',
                        ]),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('This will prevent the agent from executing any payments.')
                        ->visible(fn ($record): bool => in_array(
                            $record->status instanceof AgentStatus ? $record->status->value : $record->status,
                            [AgentStatus::ACTIVE->value],
                            true,
                        ))
                        ->action(function ($record): void {
                            app(AgentOnboardingService::class)->suspendAgent($record->id, 'Suspended via admin dashboard');
                        }),
                    Tables\Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-play-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => in_array(
                            $record->status instanceof AgentStatus ? $record->status->value : $record->status,
                            [AgentStatus::SUSPENDED->value, AgentStatus::REGISTERED->value],
                            true,
                        ))
                        ->action(function ($record): void {
                            $record->update(['status' => AgentStatus::ACTIVE]);
                            event(new \App\Domain\VirtualsAgent\Events\VirtualsAgentActivated(
                                agentProfileId: $record->id,
                                virtualsAgentId: $record->virtuals_agent_id,
                            ));
                        }),
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkAction::make('suspendSelected')
                        ->label('Suspend Selected')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('This will suspend all selected agents and prevent them from executing payments.')
                        ->action(function ($records): void {
                            $service = app(AgentOnboardingService::class);
                            $records->each(fn ($r) => $service->suspendAgent($r->id, 'Bulk suspended via admin dashboard'));
                        }),
                ]
            );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVirtualsAgents::route('/'),
            'create' => Pages\CreateVirtualsAgent::route('/create'),
            'view'   => Pages\ViewVirtualsAgent::route('/{record}'),
            'edit'   => Pages\EditVirtualsAgent::route('/{record}/edit'),
        ];
    }
}
