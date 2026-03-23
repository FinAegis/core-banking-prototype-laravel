<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\TrustCert\Enums\CertificateStatus;
use App\Domain\TrustCert\Enums\IssuerType;
use App\Domain\TrustCert\Models\Certificate;
use App\Filament\Admin\Resources\CertificateResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class CertificateResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = Certificate::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'TrustCert';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Certificate Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('id')
                                    ->label('Certificate ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('subject')
                                    ->disabled(),
                                Forms\Components\TextInput::make('issuer_type')
                                    ->label('Issuer Type')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                                Forms\Components\TextInput::make('credential_type')
                                    ->label('Credential Type')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Dates')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('issued_at')
                                    ->label('Issued At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('expires_at')
                                    ->label('Expires At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('revoked_at')
                                    ->label('Revoked At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('revocation_reason')
                                    ->label('Revocation Reason')
                                    ->disabled(),
                            ]
                        )->columns(4),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('subject')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('issuer_type')
                        ->label('Issuer Type')
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->sortable(),
                    Tables\Columns\TextColumn::make('credential_type')
                        ->label('Credential Type')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(
                            fn ($state): string => match ($state->value) {
                                'pending'   => 'gray',
                                'active'    => 'success',
                                'suspended' => 'warning',
                                'revoked'   => 'danger',
                                'expired'   => 'gray',
                                default     => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('issued_at')
                        ->label('Issued')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('expires_at')
                        ->label('Expires')
                        ->dateTime()
                        ->sortable()
                        ->color(
                            fn ($state): string => $state instanceof Carbon && $state->isPast()
                                ? 'danger'
                                : 'success'
                        ),
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('User')
                        ->searchable()
                        ->toggleable(),
                ]
            )
            ->defaultSort('issued_at', 'desc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            collect(CertificateStatus::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('issuer_type')
                        ->label('Issuer Type')
                        ->options(
                            collect(IssuerType::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                ->all()
                        ),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                ]
            )
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificates::route('/'),
            'view'  => Pages\ViewCertificate::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
