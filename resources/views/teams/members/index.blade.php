<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Team Members') }} - {{ $team->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Team Members ({{ $team->users()->count() }}/{{ $team->max_users }})
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Manage your team members and their roles
                            </p>
                        </div>
                        
                        @if (!$team->hasReachedUserLimit())
                            <a href="{{ route('teams.members.create', $team) }}" 
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                Add Team Member
                            </a>
                        @else
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                User limit reached
                            </span>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Email
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Role
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Joined
                                    </th>
                                    <th class="px-6 py-3 bg-gray-50 dark:bg-gray-700 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($members as $member)
                                    @php
                                        $teamRole = $teamRoles->where('user_id', $member->id)->first();
                                        $isOwner = $team->user_id === $member->id;
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $member->name }}
                                                        @if ($isOwner)
                                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                Owner
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                                {{ $teamRole ? ucwords(str_replace('_', ' ', $teamRole->role)) : 'Member' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $member->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            @if (!$isOwner)
                                                <a href="{{ route('teams.members.edit', [$team, $member]) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3">
                                                    Edit
                                                </a>
                                                <form action="{{ route('teams.members.destroy', [$team, $member]) }}" 
                                                      method="POST" 
                                                      class="inline"
                                                      onsubmit="return confirm('Are you sure you want to remove this team member?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        Remove
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Role Permissions Info -->
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Available Roles & Permissions
                    </h3>
                    
                    <div class="space-y-4">
                        @php
                            $roleDescriptions = [
                                'compliance_officer' => [
                                    'title' => 'Compliance Officer',
                                    'description' => 'Manage KYC verifications, generate regulatory reports, view compliance metrics',
                                    'permissions' => ['View all customers', 'Manage KYC', 'Generate reports', 'View fraud alerts']
                                ],
                                'risk_manager' => [
                                    'title' => 'Risk Manager',
                                    'description' => 'Monitor fraud alerts, manage risk rules, view risk analytics',
                                    'permissions' => ['View fraud alerts', 'Manage fraud cases', 'Configure risk rules', 'View risk dashboard']
                                ],
                                'accountant' => [
                                    'title' => 'Accountant',
                                    'description' => 'View financial reports, reconciliation, and transaction history',
                                    'permissions' => ['View financial reports', 'View all transactions', 'View reconciliation reports']
                                ],
                                'operations_manager' => [
                                    'title' => 'Operations Manager',
                                    'description' => 'Manage daily operations, process withdrawals, reverse transactions',
                                    'permissions' => ['View analytics', 'Edit customer accounts', 'Reverse transactions', 'Process withdrawals']
                                ],
                                'customer_service' => [
                                    'title' => 'Customer Service',
                                    'description' => 'Assist customers, view and edit customer information',
                                    'permissions' => ['View all customers', 'Edit customer accounts', 'View transactions']
                                ],
                            ];
                        @endphp
                        
                        @foreach ($availableRoles as $role)
                            @if (isset($roleDescriptions[$role]))
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $roleDescriptions[$role]['title'] }}
                                    </h4>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $roleDescriptions[$role]['description'] }}
                                    </p>
                                    <div class="mt-2">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Permissions:</span>
                                        <ul class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                            @foreach ($roleDescriptions[$role]['permissions'] as $permission)
                                                <li class="inline-block mr-2">• {{ $permission }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>