<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\McpToolInvocationResource\Pages;
use App\Models\McpToolInvocation;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only audit view of MCP tool invocations.
 *
 * The table is append-only by design (see ToolInvocationLogger) so this
 * resource intentionally forbids create/edit/delete — the AML feed reads
 * from the same table and mutating rows here would corrupt that feed.
 */
final class McpToolInvocationResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = McpToolInvocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'MCP';

    protected static ?string $navigationLabel = 'Tool Invocations';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        // Read-only resource — no form schema.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('tool_name')
                    ->label('Tool')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('result_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success'            => 'success',
                        'idempotency_replay' => 'info',
                        'spending_limit'     => 'warning',
                        'rate_limited'       => 'warning',
                        'error'              => 'danger',
                        default              => 'gray',
                    }),

                Tables\Columns\TextColumn::make('error_code')
                    ->label('Error')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('client_id')
                    ->label('Client')
                    ->limit(20)
                    ->tooltip(fn (McpToolInvocation $r): string => $r->client_id)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user_id')
                    ->label('User')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('settlement_amount_minor')
                    ->label('Settled')
                    ->formatStateUsing(fn (?int $state, McpToolInvocation $r): string => $r->formattedSettlement() ?? '—')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state . ' ms')
                    ->alignEnd()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('idempotency_key')
                    ->label('Idem key')
                    ->limit(12)
                    ->tooltip(fn (McpToolInvocation $r): ?string => $r->idempotency_key)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request id')
                    ->limit(12)
                    ->tooltip(fn (McpToolInvocation $r): ?string => $r->request_id)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('tool_name')
                    ->label('Tool')
                    ->options(fn (): array => self::toolOptions()),

                SelectFilter::make('result_status')
                    ->label('Status')
                    ->options([
                        'success'            => 'Success',
                        'error'              => 'Error',
                        'rate_limited'       => 'Rate limited',
                        'spending_limit'     => 'Spending limit',
                        'idempotency_replay' => 'Idempotency replay',
                    ]),

                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),

                Filter::make('settled_only')
                    ->label('Settled (has $-impact) only')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('settlement_amount_minor')),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No MCP tool invocations yet')
            ->emptyStateDescription('Calls to https://mcp.zelta.app/mcp will appear here.');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMcpToolInvocations::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function toolOptions(): array
    {
        $names = array_keys((array) config('mcp.tools', []));

        return array_combine($names, $names) ?: [];
    }
}
