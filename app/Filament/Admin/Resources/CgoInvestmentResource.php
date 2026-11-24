<?php

/**
 * CgoInvestmentResource — read-only list of CGO investments.
 *
 * Shows who invested, how much, in which tier and where each investment
 * stands. Payments, refunds and certificates are handled by the CGO flow,
 * so the list has no create, edit or delete actions.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Cgo\Models\CgoInvestment;
use App\Filament\Admin\Resources\CgoInvestmentResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CgoInvestmentResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = CgoInvestment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'CGO Investments';

    protected static ?string $modelLabel = 'CGO Investment';

    protected static ?string $pluralModelLabel = 'CGO Investments';

    public static function form(Form $form): Form
    {
        // Read-only resource — no form schema.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Investor')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    // Stored as a decimal string; shown as stored, no float conversion.
                    ->formatStateUsing(fn ($state, CgoInvestment $record): string => "{$state} {$record->currency}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('tier')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed', 'completed'                                       => 'success',
                        'pending', 'processing', 'kyc_required', 'aml_review'          => 'warning',
                        'cancelled', 'refunded', 'payment_failed', 'failed', 'expired' => 'danger',
                        default                                                        => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('shares_purchased')
                    ->label('Shares'),
                Tables\Columns\TextColumn::make('certificate_number')
                    ->label('Certificate')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'pending'        => 'Pending',
                    'confirmed'      => 'Confirmed',
                    'kyc_required'   => 'KYC required',
                    'aml_review'     => 'AML review',
                    'payment_failed' => 'Payment failed',
                    'cancelled'      => 'Cancelled',
                    'refunded'       => 'Refunded',
                    'expired'        => 'Expired',
                ]),
                SelectFilter::make('tier')->options([
                    'bronze' => 'Bronze',
                    'silver' => 'Silver',
                    'gold'   => 'Gold',
                ]),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No CGO investments yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCgoInvestments::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
