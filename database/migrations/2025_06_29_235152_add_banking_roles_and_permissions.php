<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create banking-specific roles
        $roles = [
            'super_admin' => 'Super Administrator - full system access',
            'bank_admin' => 'Bank Administrator - manage bank operations',
            'compliance_officer' => 'Compliance Officer - regulatory and AML/KYC',
            'risk_manager' => 'Risk Manager - fraud detection and risk analysis',
            'operations_manager' => 'Operations Manager - daily operations',
            'customer_service' => 'Customer Service - assist customers',
            'accountant' => 'Accountant - financial reporting',
            'auditor' => 'Auditor - audit trail access',
            'developer' => 'Developer - API and technical access',
            'customer_business' => 'Business Customer',
            'customer_private' => 'Private Customer',
        ];

        foreach ($roles as $name => $description) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Create permissions for different features
        $permissions = [
            // Dashboard & Analytics
            'view_analytics_dashboard' => 'View analytics and metrics',
            'view_financial_reports' => 'View financial reports',
            
            // Customer Management
            'view_all_customers' => 'View all customer accounts',
            'edit_customer_accounts' => 'Edit customer account details',
            'freeze_accounts' => 'Freeze/unfreeze accounts',
            'close_accounts' => 'Close customer accounts',
            
            // Transaction Management
            'view_all_transactions' => 'View all system transactions',
            'reverse_transactions' => 'Reverse transactions',
            'approve_large_transactions' => 'Approve large value transactions',
            
            // Compliance & Regulatory
            'manage_kyc' => 'Manage KYC verifications',
            'generate_regulatory_reports' => 'Generate CTR/SAR reports',
            'view_compliance_dashboard' => 'View compliance metrics',
            'manage_aml_rules' => 'Manage AML monitoring rules',
            
            // Risk & Fraud
            'view_fraud_alerts' => 'View fraud detection alerts',
            'manage_fraud_cases' => 'Manage fraud investigation cases',
            'configure_risk_rules' => 'Configure risk assessment rules',
            'view_risk_dashboard' => 'View risk analytics dashboard',
            
            // Banking Operations
            'manage_bank_integrations' => 'Manage bank connections',
            'view_reconciliation_reports' => 'View daily reconciliation',
            'manage_bank_allocations' => 'Manage bank fund allocations',
            'view_bank_health' => 'View bank health monitoring',
            
            // Financial Operations
            'manage_exchange_rates' => 'Manage exchange rate settings',
            'manage_fees' => 'Configure platform fees',
            'manage_limits' => 'Set transaction limits',
            'process_withdrawals' => 'Process withdrawal requests',
            
            // System Administration
            'manage_users' => 'Create and manage user accounts',
            'manage_roles' => 'Manage roles and permissions',
            'view_audit_logs' => 'View system audit logs',
            'manage_system_settings' => 'Configure system settings',
            
            // Developer Access
            'manage_api_keys' => 'Manage API keys',
            'view_api_logs' => 'View API access logs',
            'manage_webhooks' => 'Configure webhooks',
            
            // Customer Permissions
            'view_own_account' => 'View own account details',
            'manage_own_transactions' => 'Create own transactions',
            'view_own_statements' => 'View own account statements',
            'manage_own_settings' => 'Manage own account settings',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $this->assignPermissions();
    }

    /**
     * Assign permissions to roles
     */
    private function assignPermissions(): void
    {
        // Super Admin gets all permissions
        $superAdmin = Role::findByName('super_admin');
        $superAdmin->givePermissionTo(Permission::all());

        // Bank Admin
        $bankAdmin = Role::findByName('bank_admin');
        $bankAdmin->givePermissionTo([
            'view_analytics_dashboard',
            'view_financial_reports',
            'view_all_customers',
            'edit_customer_accounts',
            'freeze_accounts',
            'view_all_transactions',
            'approve_large_transactions',
            'manage_bank_integrations',
            'view_reconciliation_reports',
            'manage_bank_allocations',
            'view_bank_health',
            'manage_exchange_rates',
            'manage_fees',
            'manage_limits',
            'process_withdrawals',
            'manage_users',
            'view_audit_logs',
        ]);

        // Compliance Officer
        $compliance = Role::findByName('compliance_officer');
        $compliance->givePermissionTo([
            'view_all_customers',
            'view_all_transactions',
            'manage_kyc',
            'generate_regulatory_reports',
            'view_compliance_dashboard',
            'manage_aml_rules',
            'view_fraud_alerts',
            'view_audit_logs',
        ]);

        // Risk Manager
        $riskManager = Role::findByName('risk_manager');
        $riskManager->givePermissionTo([
            'view_all_customers',
            'view_all_transactions',
            'view_fraud_alerts',
            'manage_fraud_cases',
            'configure_risk_rules',
            'view_risk_dashboard',
            'view_compliance_dashboard',
        ]);

        // Operations Manager
        $operations = Role::findByName('operations_manager');
        $operations->givePermissionTo([
            'view_analytics_dashboard',
            'view_all_customers',
            'edit_customer_accounts',
            'view_all_transactions',
            'reverse_transactions',
            'view_reconciliation_reports',
            'process_withdrawals',
            'view_bank_health',
        ]);

        // Customer Service
        $customerService = Role::findByName('customer_service');
        $customerService->givePermissionTo([
            'view_all_customers',
            'edit_customer_accounts',
            'view_all_transactions',
        ]);

        // Accountant
        $accountant = Role::findByName('accountant');
        $accountant->givePermissionTo([
            'view_financial_reports',
            'view_all_transactions',
            'view_reconciliation_reports',
        ]);

        // Auditor
        $auditor = Role::findByName('auditor');
        $auditor->givePermissionTo([
            'view_all_customers',
            'view_all_transactions',
            'view_audit_logs',
            'view_compliance_dashboard',
            'view_financial_reports',
        ]);

        // Developer
        $developer = Role::findByName('developer');
        $developer->givePermissionTo([
            'manage_api_keys',
            'view_api_logs',
            'manage_webhooks',
        ]);

        // Business Customer
        $businessCustomer = Role::findByName('customer_business');
        $businessCustomer->givePermissionTo([
            'view_own_account',
            'manage_own_transactions',
            'view_own_statements',
            'manage_own_settings',
            'manage_api_keys', // Business customers can create API keys
        ]);

        // Private Customer
        $privateCustomer = Role::findByName('customer_private');
        $privateCustomer->givePermissionTo([
            'view_own_account',
            'manage_own_transactions',
            'view_own_statements',
            'manage_own_settings',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove permissions
        $permissions = [
            'view_analytics_dashboard', 'view_financial_reports',
            'view_all_customers', 'edit_customer_accounts', 'freeze_accounts', 'close_accounts',
            'view_all_transactions', 'reverse_transactions', 'approve_large_transactions',
            'manage_kyc', 'generate_regulatory_reports', 'view_compliance_dashboard', 'manage_aml_rules',
            'view_fraud_alerts', 'manage_fraud_cases', 'configure_risk_rules', 'view_risk_dashboard',
            'manage_bank_integrations', 'view_reconciliation_reports', 'manage_bank_allocations', 'view_bank_health',
            'manage_exchange_rates', 'manage_fees', 'manage_limits', 'process_withdrawals',
            'manage_users', 'manage_roles', 'view_audit_logs', 'manage_system_settings',
            'manage_api_keys', 'view_api_logs', 'manage_webhooks',
            'view_own_account', 'manage_own_transactions', 'view_own_statements', 'manage_own_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }

        // Remove new roles (keep original admin, business, private)
        $rolesToRemove = [
            'super_admin', 'bank_admin', 'compliance_officer', 'risk_manager',
            'operations_manager', 'customer_service', 'accountant', 'auditor',
            'developer', 'customer_business', 'customer_private'
        ];

        foreach ($rolesToRemove as $role) {
            Role::where('name', $role)->delete();
        }
    }
};