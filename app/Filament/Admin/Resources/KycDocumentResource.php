<?php

/**
 * KycDocumentResource — read-only list of uploaded KYC documents.
 *
 * Support can see which documents a user uploaded and where each one stands
 * (pending, verified, rejected, expired). Documents are reviewed through the
 * KYC flow, not here, so the list has no create, edit or delete actions, and
 * it never shows the stored file path.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Compliance\Models\KycDocument;
use App\Filament\Admin\Resources\KycDocumentResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class KycDocumentResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = KycDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'TrustCert';

    protected static ?string $navigationLabel = 'KYC Documents';

    protected static ?string $modelLabel = 'KYC Document';

    protected static ?string $pluralModelLabel = 'KYC Documents';

    private const DOCUMENT_TYPES = [
        'passport'         => 'Passport',
        'national_id'      => 'National ID',
        'drivers_license'  => 'Driver\'s license',
        'residence_permit' => 'Residence permit',
        'utility_bill'     => 'Utility bill',
        'bank_statement'   => 'Bank statement',
        'selfie'           => 'Selfie',
        'proof_of_income'  => 'Proof of income',
        'other'            => 'Other',
    ];

    private const STATUSES = [
        'pending'  => 'Pending',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'expired'  => 'Expired',
    ];

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
                    ->label('User')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Document')
                    ->formatStateUsing(fn (string $state): string => self::DOCUMENT_TYPES[$state] ?? ucfirst(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUSES[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        'pending'  => 'warning',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('uploaded_at')
                    ->label('Uploaded')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('verified_at')
                    ->label('Verified')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('verified_by')
                    ->label('Reviewed by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rejection_reason')
                    ->label('Rejection reason')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('uploaded_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
                SelectFilter::make('document_type')
                    ->label('Document')
                    ->options(self::DOCUMENT_TYPES),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No KYC documents yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKycDocuments::route('/'),
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
