@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">GCU Composition Voting</h1>
            <p class="mt-2 text-gray-600">Vote on monthly proposals to optimize the Global Currency Unit basket</p>
        </div>

        <!-- Voting Power Display -->
        @auth
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg p-6 text-white mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Your Voting Power</h3>
                    <p class="text-indigo-100">1 GCU = 1 Vote</p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold">{{ number_format($gcuBalance, 2) }} Ǥ</div>
                    <p class="text-indigo-100">Available votes</p>
                </div>
            </div>
        </div>
        @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-8">
            <p class="text-yellow-800">
                <a href="{{ route('login') }}" class="font-semibold underline">Login</a> to participate in GCU governance voting.
            </p>
        </div>
        @endauth

        <!-- Active Proposals -->
        @if($activeProposals->count() > 0)
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Active Proposals</h2>
            <div class="space-y-6">
                @foreach($activeProposals as $proposal)
                <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">
                                {{ $proposal->title }}
                            </h3>
                            <p class="text-gray-600 mb-4">{{ Str::limit($proposal->description, 200) }}</p>
                            
                            <!-- Progress bars -->
                            <div class="space-y-3">
                                <!-- Participation -->
                                <div>
                                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                                        <span>Participation</span>
                                        <span>{{ number_format($proposal->participation_rate, 1) }}% (Min: {{ $proposal->minimum_participation }}%)</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min($proposal->participation_rate, 100) }}%"></div>
                                    </div>
                                </div>
                                
                                <!-- Approval -->
                                <div>
                                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                                        <span>Approval</span>
                                        <span>{{ number_format($proposal->approval_rate, 1) }}% (Min: {{ $proposal->minimum_approval }}%)</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ min($proposal->approval_rate, 100) }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ml-6 text-right">
                            <div class="text-sm text-gray-500 mb-2">
                                Ends {{ $proposal->time_remaining }}
                            </div>
                            <a href="{{ route('gcu.voting.show', $proposal) }}" 
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                View & Vote
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Upcoming Proposals -->
        @if($upcomingProposals->count() > 0)
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Upcoming Proposals</h2>
            <div class="grid md:grid-cols-2 gap-6">
                @foreach($upcomingProposals as $proposal)
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $proposal->title }}</h3>
                    <p class="text-gray-600 text-sm mb-3">{{ Str::limit($proposal->description, 100) }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            Starts {{ $proposal->voting_starts_at->diffForHumans() }}
                        </span>
                        <a href="{{ route('gcu.voting.show', $proposal) }}" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                            Preview →
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Past Proposals -->
        @if($pastProposals->count() > 0)
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Past Proposals</h2>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Proposal
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Result
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Participation
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Approval
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th class="relative px-6 py-3">
                                <span class="sr-only">View</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($pastProposals as $proposal)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $proposal->title }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($proposal->status === 'implemented')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Implemented
                                    </span>
                                @elseif($proposal->status === 'rejected')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Rejected
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Closed
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ number_format($proposal->participation_rate, 1) }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ number_format($proposal->approval_rate, 1) }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $proposal->voting_ends_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('gcu.voting.show', $proposal) }}" class="text-indigo-600 hover:text-indigo-900">
                                    View
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection