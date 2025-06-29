@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Back link -->
        <div class="mb-6">
            <a href="{{ route('gcu.voting.index') }}" class="text-indigo-600 hover:text-indigo-700 font-medium">
                ← Back to Voting
            </a>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Main content -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Proposal header -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-start justify-between mb-4">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $proposal->title }}</h1>
                        @if($proposal->status === 'active')
                            @if($proposal->isVotingActive())
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                                    Active
                                </span>
                            @else
                                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-semibold">
                                    Upcoming
                                </span>
                            @endif
                        @elseif($proposal->status === 'implemented')
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                                Implemented
                            </span>
                        @else
                            <span class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-sm font-semibold">
                                {{ ucfirst($proposal->status) }}
                            </span>
                        @endif
                    </div>
                    
                    <div class="prose max-w-none text-gray-600">
                        <p>{{ $proposal->description }}</p>
                    </div>
                    
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-900 mb-2">Rationale</h3>
                        <div class="prose max-w-none text-gray-600">
                            <p>{{ $proposal->rationale }}</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex items-center text-sm text-gray-500">
                        <span>Proposed by {{ $proposal->creator->name ?? 'System' }}</span>
                        <span class="mx-2">•</span>
                        <span>{{ $proposal->created_at->format('M d, Y') }}</span>
                    </div>
                </div>

                <!-- Composition comparison -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Proposed Composition Changes</h2>
                    
                    <div class="space-y-4">
                        @php
                            $currencies = array_unique(array_merge(
                                array_keys($proposal->current_composition),
                                array_keys($proposal->proposed_composition)
                            ));
                            $flags = ['USD' => '🇺🇸', 'EUR' => '🇪🇺', 'GBP' => '🇬🇧', 'CHF' => '🇨🇭', 'JPY' => '🇯🇵', 'XAU' => '🏆'];
                            $names = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'CHF' => 'Swiss Franc', 'JPY' => 'Japanese Yen', 'XAU' => 'Gold'];
                        @endphp
                        
                        @foreach($currencies as $currency)
                        @php
                            $current = $proposal->current_composition[$currency] ?? 0;
                            $proposed = $proposal->proposed_composition[$currency] ?? 0;
                            $change = $proposed - $current;
                        @endphp
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <span class="text-2xl">{{ $flags[$currency] ?? '💱' }}</span>
                                <div>
                                    <div class="font-medium text-gray-900">{{ $names[$currency] ?? $currency }}</div>
                                    <div class="text-sm text-gray-500">{{ $currency }}</div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-6">
                                <div class="text-right">
                                    <div class="text-sm text-gray-500">Current</div>
                                    <div class="font-semibold">{{ $current }}%</div>
                                </div>
                                
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                                
                                <div class="text-right">
                                    <div class="text-sm text-gray-500">Proposed</div>
                                    <div class="font-semibold">{{ $proposed }}%</div>
                                </div>
                                
                                <div class="text-right min-w-[80px]">
                                    @if($change > 0)
                                        <span class="text-green-600 font-semibold">+{{ $change }}%</span>
                                    @elseif($change < 0)
                                        <span class="text-red-600 font-semibold">{{ $change }}%</span>
                                    @else
                                        <span class="text-gray-500">No change</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Voting card -->
                @if($proposal->isVotingActive())
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Cast Your Vote</h3>
                    
                    @auth
                        @if($gcuBalance > 0)
                            @if($userVote)
                                <div class="bg-indigo-50 rounded-lg p-4 mb-4">
                                    <p class="text-sm text-indigo-700">
                                        You voted <strong>{{ ucfirst($userVote->vote) }}</strong> with {{ number_format($userVote->voting_power, 2) }} Ǥ
                                    </p>
                                </div>
                            @endif
                            
                            <form action="{{ route('gcu.voting.vote', $proposal) }}" method="POST" class="space-y-3">
                                @csrf
                                <div class="space-y-2">
                                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="vote" value="for" class="mr-3" {{ $userVote && $userVote->vote === 'for' ? 'checked' : '' }}>
                                        <span class="font-medium text-green-700">For</span>
                                    </label>
                                    
                                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="vote" value="against" class="mr-3" {{ $userVote && $userVote->vote === 'against' ? 'checked' : '' }}>
                                        <span class="font-medium text-red-700">Against</span>
                                    </label>
                                    
                                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="vote" value="abstain" class="mr-3" {{ $userVote && $userVote->vote === 'abstain' ? 'checked' : '' }}>
                                        <span class="font-medium text-gray-700">Abstain</span>
                                    </label>
                                </div>
                                
                                <div class="pt-4">
                                    <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                                        {{ $userVote ? 'Update Vote' : 'Submit Vote' }}
                                    </button>
                                </div>
                                
                                <p class="text-xs text-gray-500 text-center">
                                    Your voting power: {{ number_format($gcuBalance, 2) }} Ǥ
                                </p>
                            </form>
                        @else
                            <div class="bg-yellow-50 rounded-lg p-4">
                                <p class="text-sm text-yellow-800">You need GCU holdings to vote.</p>
                                <a href="{{ route('dashboard') }}" class="text-yellow-700 underline text-sm">Get GCU →</a>
                            </div>
                        @endif
                    @else
                        <div class="text-center">
                            <p class="text-gray-600 mb-4">Login to participate in voting</p>
                            <a href="{{ route('login') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                                Login
                            </a>
                        </div>
                    @endauth
                </div>
                @endif

                <!-- Voting stats -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Voting Statistics</h3>
                    
                    <div class="space-y-4">
                        <!-- Time remaining -->
                        @if($proposal->isVotingActive())
                        <div>
                            <div class="text-sm text-gray-500">Time Remaining</div>
                            <div class="font-semibold text-gray-900">{{ $proposal->time_remaining }}</div>
                        </div>
                        @endif
                        
                        <!-- Participation -->
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-500">Participation</span>
                                <span class="font-medium">{{ number_format($proposal->participation_rate, 1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($proposal->participation_rate, 100) }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Minimum: {{ $proposal->minimum_participation }}%</p>
                        </div>
                        
                        <!-- Vote distribution -->
                        <div>
                            <div class="text-sm text-gray-500 mb-2">Vote Distribution</div>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-green-700">For</span>
                                    <span class="font-medium">{{ number_format($voteDistribution['for'], 2) }} Ǥ</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-red-700">Against</span>
                                    <span class="font-medium">{{ number_format($voteDistribution['against'], 2) }} Ǥ</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-700">Abstain</span>
                                    <span class="font-medium">{{ number_format($voteDistribution['abstain'], 2) }} Ǥ</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Approval rate -->
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-500">Approval Rate</span>
                                <span class="font-medium">{{ number_format($proposal->approval_rate, 1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: {{ min($proposal->approval_rate, 100) }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Minimum: {{ $proposal->minimum_approval }}%</p>
                        </div>
                        
                        <!-- Total votes -->
                        <div>
                            <div class="text-sm text-gray-500">Total Votes Cast</div>
                            <div class="font-semibold text-gray-900">{{ number_format($proposal->total_votes_cast, 2) }} Ǥ</div>
                            <p class="text-xs text-gray-500">out of {{ number_format($proposal->total_gcu_supply, 2) }} Ǥ total supply</p>
                        </div>
                    </div>
                </div>

                <!-- Voting period -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Voting Period</h3>
                    <div class="space-y-2 text-sm">
                        <div>
                            <span class="text-gray-500">Starts:</span>
                            <span class="ml-2 font-medium">{{ $proposal->voting_starts_at->format('M d, Y g:i A') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Ends:</span>
                            <span class="ml-2 font-medium">{{ $proposal->voting_ends_at->format('M d, Y g:i A') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection