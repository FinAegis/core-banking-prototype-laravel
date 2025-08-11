@extends('layouts.public')

@section('title', 'Thank You - CGO Early Access')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50">
    <!-- Success Message Section -->
    <section class="pt-20 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Success Header -->
                <div class="bg-gradient-to-r from-green-500 to-emerald-600 px-8 py-12 text-center">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 backdrop-blur rounded-full mb-6">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                        You're on the List!
                    </h1>
                    <p class="text-xl text-green-50 max-w-2xl mx-auto">
                        Welcome to the FinAegis Continuous Growth Offering early access program
                    </p>
                </div>

                <!-- Content Section -->
                <div class="p-8 lg:p-12">
                    <!-- Confirmation Message -->
                    <div class="text-center mb-10">
                        <p class="text-lg text-gray-700 mb-4">
                            Thank you for your interest in investing in the future of democratic banking. 
                            We've successfully added your email to our exclusive early access list.
                        </p>
                        <p class="text-gray-600">
                            A confirmation email has been sent to your inbox with more details about the CGO program.
                        </p>
                    </div>

                    <!-- What to Expect -->
                    <div class="bg-indigo-50 rounded-xl p-8 mb-10">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                            <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            What Happens Next?
                        </h2>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-indigo-600 text-white">
                                        1
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Email Confirmation</h3>
                                    <p class="text-gray-600">Check your inbox for our welcome email with detailed information about the CGO program and investment tiers.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-indigo-600 text-white">
                                        2
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Launch Notification</h3>
                                    <p class="text-gray-600">You'll receive an exclusive early access notification 24 hours before the public launch.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-indigo-600 text-white">
                                        3
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Investment Opportunities</h3>
                                    <p class="text-gray-600">Get first access to limited investment tiers with special early investor benefits.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-indigo-600 text-white">
                                        4
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Ownership Certificate</h3>
                                    <p class="text-gray-600">Receive your official FinAegis ownership certificate after successful investment.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Early Access Benefits -->
                    <div class="bg-gradient-to-r from-purple-100 to-indigo-100 rounded-xl p-8 mb-10">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Early Access Benefits</h2>
                        <ul class="space-y-3">
                            <li class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-purple-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Priority access to limited investment tiers
                            </li>
                            <li class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-purple-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Exclusive early investor pricing
                            </li>
                            <li class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-purple-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Regular updates on platform development
                            </li>
                            <li class="flex items-center text-gray-700">
                                <svg class="w-5 h-5 text-purple-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Direct communication with the founding team
                            </li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('cgo') }}" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition duration-150 ease-in-out">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to CGO Page
                        </a>
                        
                        @guest
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition duration-150 ease-in-out">
                                Create Account
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition duration-150 ease-in-out">
                                Go to Dashboard
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                </svg>
                            </a>
                        @endguest
                    </div>

                    <!-- Additional Info -->
                    <div class="mt-10 pt-10 border-t border-gray-200 text-center">
                        <p class="text-sm text-gray-600">
                            Questions? Contact our investment team at 
                            <a href="mailto:info@finaegis.org" class="text-indigo-600 hover:text-indigo-700 font-medium">info@finaegis.org</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection