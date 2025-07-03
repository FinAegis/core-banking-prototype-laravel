<?php

namespace App\Filament\Resources;

use App\Domain\Cgo\Actions\RequestRefundAction;
use App\Domain\Cgo\Aggregates\RefundAggregate;
use App\Filament\Resources\CgoRefundResource\Pages;
use App\Models\CgoRefund;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CgoRefundResource extends Resource
{
    protected static ?string $model = CgoRefund::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    
    protected static ?string $navigationGroup = 'CGO Management';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Refund Information')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Refund ID')
                            ->disabled(),
                        
                        Forms\Components\Select::make('investment_id')
                            ->label('Investment')
                            ->relationship('investment', 'uuid')
                            ->searchable()
                            ->preload()
                            ->disabled(),
                        
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->prefix('$')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => number_format($state / 100, 2)),
                        
                        Forms\Components\TextInput::make('currency')
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->disabled(),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Refund Details')
                    ->schema([
                        Forms\Components\Select::make('reason')
                            ->options([
                                'customer_request' => 'Customer Request',
                                'duplicate_payment' => 'Duplicate Payment',
                                'payment_error' => 'Payment Error',
                                'system_error' => 'System Error',
                                'regulatory_requirement' => 'Regulatory Requirement',
                                'other' => 'Other',
                            ])
                            ->disabled(),
                        
                        Forms\Components\Textarea::make('reason_details')
                            ->rows(3)
                            ->disabled(),
                        
                        Forms\Components\Textarea::make('approval_notes')
                            ->rows(3)
                            ->visible(fn ($record) => $record && $record->approved_at),
                        
                        Forms\Components\Textarea::make('rejection_reason')
                            ->rows(3)
                            ->visible(fn ($record) => $record && $record->rejected_at),
                    ]),
                
                Forms\Components\Section::make('Payment Processing')
                    ->schema([
                        Forms\Components\TextInput::make('payment_processor')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('processor_refund_id')
                            ->label('Processor Refund ID')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('processor_status')
                            ->disabled(),
                        
                        Forms\Components\KeyValue::make('processor_response')
                            ->disabled(),
                    ])
                    ->visible(fn ($record) => $record && $record->processor_refund_id),
                
                Forms\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\DateTimePicker::make('requested_at')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('rejected_at')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('processed_at')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('failed_at')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('cancelled_at')
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
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('investment.uuid')
                    ->label('Investment')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD', divideBy: 100)
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'primary' => 'approved',
                        'danger' => ['rejected', 'failed'],
                        'info' => 'processing',
                        'success' => 'completed',
                        'gray' => 'cancelled',
                    ]),
                
                Tables\Columns\TextColumn::make('reason')
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucfirst($state))),
                
                Tables\Columns\TextColumn::make('payment_processor')
                    ->label('Processor')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('requested_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'customer_request' => 'Customer Request',
                        'duplicate_payment' => 'Duplicate Payment',
                        'payment_error' => 'Payment Error',
                        'system_error' => 'System Error',
                        'regulatory_requirement' => 'Regulatory Requirement',
                        'other' => 'Other',
                    ]),
                
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CgoRefund $record) => $record->canBeApproved())
                    ->form([
                        Forms\Components\Textarea::make('approval_notes')
                            ->label('Approval Notes')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (CgoRefund $record, array $data) {
                        RefundAggregate::retrieve($record->id)
                            ->approve(
                                approvedBy: auth()->id(),
                                approvalNotes: $data['approval_notes']
                            )
                            ->persist();
                        
                        Notification::make()
                            ->title('Refund approved')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CgoRefund $record) => $record->canBeRejected())
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (CgoRefund $record, array $data) {
                        RefundAggregate::retrieve($record->id)
                            ->reject(
                                rejectedBy: auth()->id(),
                                rejectionReason: $data['rejection_reason']
                            )
                            ->persist();
                        
                        Notification::make()
                            ->title('Refund rejected')
                            ->warning()
                            ->send();
                    }),
                
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-ban')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (CgoRefund $record) => $record->canBeCancelled())
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (CgoRefund $record, array $data) {
                        RefundAggregate::retrieve($record->id)
                            ->cancel(
                                cancellationReason: $data['cancellation_reason'],
                                cancelledBy: auth()->id(),
                                cancelledAt: now()->toIso8601String()
                            )
                            ->persist();
                        
                        Notification::make()
                            ->title('Refund cancelled')
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('requested_at', 'desc');
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
            'index' => Pages\ListCgoRefunds::route('/'),
            'create' => Pages\CreateCgoRefund::route('/create'),
            'view' => Pages\ViewCgoRefund::route('/{record}'),
            'edit' => Pages\EditCgoRefund::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Refunds should be created through the workflow
    }

    public static function canEdit(Model $record): bool
    {
        return false; // Refunds should not be edited directly
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Refunds should not be deleted
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() > 0 ? 'warning' : null;
    }
}