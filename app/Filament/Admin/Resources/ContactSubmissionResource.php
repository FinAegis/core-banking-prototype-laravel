<?php

/**
 * ContactSubmissionResource — the support inbox.
 *
 * Re-homed from the orphaned legacy `App\Filament\Resources` tree (which no
 * panel discovered) into the active admin panel. Submissions are created by
 * the public contact form only, so the create page was dropped; operators
 * view, triage (status/priority filters), respond, and keep internal notes.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Contact\Models\ContactSubmission;
use App\Filament\Admin\Resources\ContactSubmissionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactSubmissionResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = ContactSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Contact Submissions';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() > 0 ? 'warning' : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->disabled(),
                        Forms\Components\Select::make('subject')
                            ->options([
                                'account'    => 'Account Issues',
                                'technical'  => 'Technical Support',
                                'billing'    => 'Billing & Payments',
                                'gcu'        => 'GCU Questions',
                                'api'        => 'API & Integration',
                                'compliance' => 'Compliance & Security',
                                'other'      => 'Other',
                            ])
                            ->required()
                            ->disabled(),
                        Forms\Components\Select::make('priority')
                            ->options([
                                'low'    => 'Low',
                                'medium' => 'Medium',
                                'high'   => 'High',
                                'urgent' => 'Urgent',
                            ])
                            ->required()
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Message')
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->disabled()
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Response')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending'   => 'Pending',
                                'responded' => 'Responded',
                                'closed'    => 'Closed',
                            ])
                            ->required(),
                        Forms\Components\DateTimePicker::make('responded_at')
                            ->label('Response Date'),
                        Forms\Components\Textarea::make('response_notes')
                            ->label('Internal Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(),
                        Forms\Components\Textarea::make('user_agent')
                            ->label('User Agent')
                            ->disabled()
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('attachment_path')
                            ->label('Attachment')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'account'    => 'Account Issues',
                        'technical'  => 'Technical Support',
                        'billing'    => 'Billing & Payments',
                        'gcu'        => 'GCU Questions',
                        'api'        => 'API & Integration',
                        'compliance' => 'Compliance & Security',
                        'other'      => 'Other',
                        default      => $state,
                    }),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high', 'urgent' => 'danger',
                        'medium'         => 'warning',
                        default          => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'responded' => 'success',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Attachment')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon(''),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'responded' => 'Responded',
                        'closed'    => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low'    => 'Low',
                        'medium' => 'Medium',
                        'high'   => 'High',
                        'urgent' => 'Urgent',
                    ]),
                Tables\Filters\SelectFilter::make('subject')
                    ->options([
                        'account'    => 'Account Issues',
                        'technical'  => 'Technical Support',
                        'billing'    => 'Billing & Payments',
                        'gcu'        => 'GCU Questions',
                        'api'        => 'API & Integration',
                        'compliance' => 'Compliance & Security',
                        'other'      => 'Other',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('respond')
                    ->label('Mark as Responded')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (ContactSubmission $record) => $record->markAsResponded())
                    ->visible(fn (ContactSubmission $record): bool => $record->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactSubmissions::route('/'),
            'view'  => Pages\ViewContactSubmission::route('/{record}'),
            'edit'  => Pages\EditContactSubmission::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<ContactSubmission>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes();
    }

    public static function canCreate(): bool
    {
        // Submissions only enter via the public contact form.
        return false;
    }
}
