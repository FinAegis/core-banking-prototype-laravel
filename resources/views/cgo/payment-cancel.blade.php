@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 lg:p-8">
                <div class="text-center py-12">
                    <svg class="mx-auto h-24 w-24 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    
                    <h2 class="mt-6 text-3xl font-bold text-gray-900">
                        Payment Cancelled
                    </h2>
                    
                    <p class="mt-4 text-lg text-gray-600">
                        Your payment has been cancelled and your investment was not processed.
                    </p>
                    
                    @if(isset($investment))
                    <div class="mt-6 bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-500">Investment Reference:</p>
                        <p class="text-lg font-mono">{{ $investment->uuid }}</p>
                    </div>
                    @endif
                    
                    <p class="mt-4 text-gray-500">
                        No charges have been made to your account. You can try again at any time.
                    </p>
                    
                    <div class="mt-8 space-x-4">
                        <a href="{{ route('cgo.invest') }}" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Try Again
                        </a>
                        
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection