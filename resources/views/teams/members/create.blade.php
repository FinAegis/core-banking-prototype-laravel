<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Add Team Member') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('teams.members.store', $team) }}">
                        @csrf
                        
                        <div>
                            <x-label for="name" value="{{ __('Name') }}" />
                            <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                            <x-input-error for="name" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-label for="email" value="{{ __('Email') }}" />
                            <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
                            <x-input-error for="email" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-label for="password" value="{{ __('Password') }}" />
                            <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
                            <x-input-error for="password" class="mt-2" />
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                The password will be shared with the new team member securely.
                            </p>
                        </div>

                        <div class="mt-4">
                            <x-label for="role" value="{{ __('Role') }}" />
                            <select id="role" name="role" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                @php
                                    $roleNames = [
                                        'compliance_officer' => 'Compliance Officer',
                                        'risk_manager' => 'Risk Manager',
                                        'accountant' => 'Accountant',
                                        'operations_manager' => 'Operations Manager',
                                        'customer_service' => 'Customer Service',
                                    ];
                                @endphp
                                @foreach ($availableRoles as $role)
                                    <option value="{{ $role }}">{{ $roleNames[$role] ?? ucwords(str_replace('_', ' ', $role)) }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="role" class="mt-2" />
                        </div>

                        <!-- Role Description -->
                        <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <h4 class="font-medium text-sm text-gray-900 dark:text-gray-100 mb-2">Role Permissions</h4>
                            <div id="role-description" class="text-sm text-gray-600 dark:text-gray-400">
                                <!-- Dynamically updated based on selected role -->
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('teams.members.index', $team) }}" 
                               class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150 mr-3">
                                Cancel
                            </a>
                            
                            <x-button>
                                {{ __('Add Team Member') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const roleDescriptions = {
            'compliance_officer': {
                title: 'Compliance Officer',
                description: 'Can manage KYC verifications, generate regulatory reports (CTR/SAR), view compliance metrics, and monitor fraud alerts.',
                permissions: ['View all customers', 'Manage KYC', 'Generate regulatory reports', 'View compliance dashboard', 'View fraud alerts']
            },
            'risk_manager': {
                title: 'Risk Manager', 
                description: 'Can view and manage fraud cases, configure risk assessment rules, and access risk analytics.',
                permissions: ['View fraud alerts', 'Manage fraud cases', 'Configure risk rules', 'View risk dashboard']
            },
            'accountant': {
                title: 'Accountant',
                description: 'Can view financial reports, transaction history, and daily reconciliation reports.',
                permissions: ['View financial reports', 'View all transactions', 'View reconciliation reports']
            },
            'operations_manager': {
                title: 'Operations Manager',
                description: 'Can manage daily operations including processing withdrawals, reversing transactions, and viewing analytics.',
                permissions: ['View analytics dashboard', 'Edit customer accounts', 'Reverse transactions', 'Process withdrawals']
            },
            'customer_service': {
                title: 'Customer Service',
                description: 'Can assist customers by viewing and editing customer information and transaction history.',
                permissions: ['View all customers', 'Edit customer accounts', 'View all transactions']
            }
        };

        function updateRoleDescription() {
            const roleSelect = document.getElementById('role');
            const descriptionDiv = document.getElementById('role-description');
            const selectedRole = roleSelect.value;
            
            if (roleDescriptions[selectedRole]) {
                const role = roleDescriptions[selectedRole];
                let html = `<p class="mb-2">${role.description}</p>`;
                html += '<ul class="list-disc list-inside space-y-1">';
                role.permissions.forEach(permission => {
                    html += `<li>${permission}</li>`;
                });
                html += '</ul>';
                descriptionDiv.innerHTML = html;
            }
        }

        document.getElementById('role').addEventListener('change', updateRoleDescription);
        updateRoleDescription(); // Initial load
    </script>
</x-app-layout>