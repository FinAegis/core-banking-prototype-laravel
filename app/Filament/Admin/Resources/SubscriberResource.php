<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SubscriberResource\Pages;
use App\Filament\Admin\Resources\SubscriberResource\RelationManagers;
use App\Models\Subscriber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;

class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    
    protected static ?string $navigationGroup = 'Marketing';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subscriber Information')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('source')
                            ->required()
                            ->options([
                                Subscriber::SOURCE_BLOG => 'Blog',
                                Subscriber::SOURCE_CGO => 'CGO Early Access',
                                Subscriber::SOURCE_INVESTMENT => 'Investment',
                                Subscriber::SOURCE_FOOTER => 'Footer',
                                Subscriber::SOURCE_CONTACT => 'Contact Form',
                                Subscriber::SOURCE_PARTNER => 'Partner Application',
                            ]),
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                Subscriber::STATUS_ACTIVE => 'Active',
                                Subscriber::STATUS_UNSUBSCRIBED => 'Unsubscribed',
                                Subscriber::STATUS_BOUNCED => 'Bounced',
                            ])
                            ->default(Subscriber::STATUS_ACTIVE),
                        TagsInput::make('tags')
                            ->suggestions([
                                'newsletter',
                                'product_updates',
                                'marketing',
                                'investor',
                                'partner',
                                'early_adopter',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Preferences')
                    ->schema([
                        Forms\Components\KeyValue::make('preferences')
                            ->keyLabel('Preference')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
                    
                Forms\Components\Section::make('Tracking Information')
                    ->schema([
                        Forms\Components\TextInput::make('ip_address')
                            ->maxLength(255)
                            ->disabled(),
                        Forms\Components\Textarea::make('user_agent')
                            ->rows(2)
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('confirmed_at')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('unsubscribed_at')
                            ->disabled(),
                        Forms\Components\TextInput::make('unsubscribe_reason')
                            ->maxLength(255)
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->weight(FontWeight::Bold),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Subscriber::SOURCE_BLOG => 'Blog',
                        Subscriber::SOURCE_CGO => 'CGO Early Access',
                        Subscriber::SOURCE_INVESTMENT => 'Investment',
                        Subscriber::SOURCE_FOOTER => 'Footer',
                        Subscriber::SOURCE_CONTACT => 'Contact Form',
                        Subscriber::SOURCE_PARTNER => 'Partner Application',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Subscriber::STATUS_ACTIVE => 'success',
                        Subscriber::STATUS_UNSUBSCRIBED => 'warning',
                        Subscriber::STATUS_BOUNCED => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TagsColumn::make('tags')
                    ->separator(',')
                    ->limitList(3),
                Tables\Columns\TextColumn::make('confirmed_at')
                    ->label('Confirmed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Subscribed')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Subscriber::STATUS_ACTIVE => 'Active',
                        Subscriber::STATUS_UNSUBSCRIBED => 'Unsubscribed',
                        Subscriber::STATUS_BOUNCED => 'Bounced',
                    ])
                    ->default(Subscriber::STATUS_ACTIVE),
                SelectFilter::make('source')
                    ->multiple()
                    ->options([
                        Subscriber::SOURCE_BLOG => 'Blog',
                        Subscriber::SOURCE_CGO => 'CGO Early Access',
                        Subscriber::SOURCE_INVESTMENT => 'Investment',
                        Subscriber::SOURCE_FOOTER => 'Footer',
                        Subscriber::SOURCE_CONTACT => 'Contact Form',
                        Subscriber::SOURCE_PARTNER => 'Partner Application',
                    ]),
                Tables\Filters\Filter::make('confirmed')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('confirmed_at')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('unsubscribe')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Unsubscribe Subscriber')
                    ->modalDescription('Are you sure you want to unsubscribe this subscriber?')
                    ->modalSubmitActionLabel('Yes, unsubscribe')
                    ->visible(fn (Subscriber $record): bool => $record->isActive())
                    ->action(function (Subscriber $record): void {
                        $record->unsubscribe('Manual unsubscribe by admin');
                        Notification::make()
                            ->title('Subscriber unsubscribed')
                            ->success()
                            ->send();
                    }),
                Action::make('resubscribe')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Subscriber $record): bool => !$record->isActive())
                    ->action(function (Subscriber $record): void {
                        $record->update([
                            'status' => Subscriber::STATUS_ACTIVE,
                            'unsubscribed_at' => null,
                            'unsubscribe_reason' => null,
                        ]);
                        Notification::make()
                            ->title('Subscriber reactivated')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('bulk_unsubscribe')
                        ->label('Unsubscribe')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                if ($record->isActive()) {
                                    $record->unsubscribe('Bulk unsubscribe by admin');
                                }
                            }
                            Notification::make()
                                ->title('Subscribers unsubscribed')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('add_tags')
                        ->label('Add Tags')
                        ->icon('heroicon-o-tag')
                        ->form([
                            TagsInput::make('tags')
                                ->required()
                                ->suggestions([
                                    'newsletter',
                                    'product_updates',
                                    'marketing',
                                    'investor',
                                    'partner',
                                    'early_adopter',
                                ]),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $record) {
                                $record->addTags($data['tags']);
                            }
                            Notification::make()
                                ->title('Tags added to subscribers')
                                ->success()
                                ->send();
                        }),
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
            'index' => Pages\ListSubscribers::route('/'),
            'create' => Pages\CreateSubscriber::route('/create'),
            'edit' => Pages\EditSubscriber::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', Subscriber::STATUS_ACTIVE)->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
