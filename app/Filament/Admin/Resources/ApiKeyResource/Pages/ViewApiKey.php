<?php

namespace App\Filament\Admin\Resources\ApiKeyResource\Pages;

use App\Filament\Admin\Resources\ApiKeyResource;
use App\Models\ApiKeyLog;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewApiKey extends ViewRecord
{
    protected static string $resource = ApiKeyResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Key Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('description'),
                        Infolists\Components\TextEntry::make('key_prefix')
                            ->label('Key Preview')
                            ->formatStateUsing(fn (string $state): string => $state . '...'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Permissions & Security')
                    ->schema([
                        Infolists\Components\TextEntry::make('permissions')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'read' => 'success',
                                'write' => 'warning',
                                'delete' => 'danger',
                                '*' => 'primary',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('allowed_ips')
                            ->label('IP Whitelist')
                            ->listWithLineBreaks()
                            ->default('All IPs allowed'),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expiration')
                            ->dateTime()
                            ->default('Never expires'),
                    ]),
                    
                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Owner'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('last_used_at')
                            ->label('Last Used')
                            ->dateTime()
                            ->default('Never'),
                        Infolists\Components\TextEntry::make('last_used_ip')
                            ->label('Last IP')
                            ->default('N/A'),
                        Infolists\Components\TextEntry::make('request_count')
                            ->label('Total Requests')
                            ->numeric(),
                        Infolists\Components\TextEntry::make('logs_count')
                            ->label('Requests Today')
                            ->state(function ($record): int {
                                return $record->logs()
                                    ->where('created_at', '>=', now()->startOfDay())
                                    ->count();
                            }),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Recent Activity')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('recentLogs')
                            ->label('')
                            ->state(function ($record) {
                                return $record->logs()
                                    ->orderBy('created_at', 'desc')
                                    ->limit(10)
                                    ->get();
                            })
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Time')
                                    ->dateTime('M d, g:i A'),
                                Infolists\Components\TextEntry::make('method')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'GET' => 'info',
                                        'POST' => 'success',
                                        'PUT', 'PATCH' => 'warning',
                                        'DELETE' => 'danger',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('path'),
                                Infolists\Components\TextEntry::make('response_code')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (?int $state): string => match (true) {
                                        $state >= 200 && $state < 300 => 'success',
                                        $state >= 400 => 'danger',
                                        default => 'warning',
                                    }),
                                Infolists\Components\TextEntry::make('response_time')
                                    ->label('Response Time')
                                    ->formatStateUsing(fn (?int $state): string => $state ? $state . 'ms' : 'N/A'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}