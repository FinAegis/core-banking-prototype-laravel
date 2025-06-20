<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PopulateDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:populate 
                            {--fresh : Wipe the database first}
                            {--with-admin : Also create an admin user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate the database with demo data for GCU platform demonstration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌍 GCU Platform Demo Data Population');
        $this->line('=====================================');
        
        if ($this->option('fresh')) {
            if (!$this->confirm('This will wipe your database. Are you sure?')) {
                $this->warn('Operation cancelled.');
                return Command::FAILURE;
            }
            
            $this->info('Refreshing database...');
            Artisan::call('migrate:fresh', [], $this->output);
        }
        
        // Run standard seeders first
        $this->info('Running standard seeders...');
        Artisan::call('db:seed', [], $this->output);
        
        // Run demo data seeder
        $this->info('Creating demo users and data...');
        Artisan::call('db:seed', ['--class' => 'DemoDataSeeder'], $this->output);
        
        // Create admin user if requested
        if ($this->option('with-admin')) {
            $this->createAdminUser();
        }
        
        // Display summary
        $this->displaySummary();
        
        $this->newLine();
        $this->info('✅ Demo data population completed!');
        
        return Command::SUCCESS;
    }
    
    /**
     * Create an admin user for the dashboard
     */
    private function createAdminUser(): void
    {
        $this->info('Creating admin user...');
        
        $email = 'admin@gcu.global';
        $password = 'admin123';
        
        // Check if admin already exists
        if (DB::table('users')->where('email', $email)->exists()) {
            $this->warn("Admin user already exists: $email");
            return;
        }
        
        // Use Filament command to create admin
        Artisan::call('make:filament-user', [
            '--name' => 'Admin User',
            '--email' => $email,
            '--password' => $password,
        ]);
        
        $this->info("Admin user created:");
        $this->line("  Email: $email");
        $this->line("  Password: $password");
    }
    
    /**
     * Display summary of created data
     */
    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('📊 Demo Data Summary');
        $this->line('====================');
        
        // Users
        $userCount = DB::table('users')->where('email', 'like', 'demo.%')->count();
        $this->line("Demo Users: $userCount");
        
        // Accounts
        $accountCount = DB::table('accounts')->count();
        $this->line("Accounts: $accountCount");
        
        // Assets
        $assetCount = DB::table('assets')->where('is_active', true)->count();
        $this->line("Active Assets: $assetCount");
        
        // Polls
        $activePollCount = DB::table('polls')->where('status', 'active')->count();
        $this->line("Active Polls: $activePollCount");
        
        // Bank preferences
        $bankPrefCount = DB::table('user_bank_preferences')->count();
        $this->line("Bank Preferences: $bankPrefCount");
        
        $this->newLine();
        $this->info('🔐 Demo User Credentials');
        $this->line('========================');
        $this->table(
            ['Email', 'Password', 'Persona'],
            [
                ['demo.argentina@gcu.global', 'demo123', 'High-inflation country user'],
                ['demo.nomad@gcu.global', 'demo123', 'Digital nomad'],
                ['demo.business@gcu.global', 'demo123', 'Business user'],
                ['demo.investor@gcu.global', 'demo123', 'Investor'],
                ['demo.user@gcu.global', 'demo123', 'Regular user'],
            ]
        );
        
        $this->newLine();
        $this->info('🔗 Access Points');
        $this->line('================');
        $this->line('Admin Dashboard: /admin');
        $this->line('API Documentation: /api/documentation');
        $this->line('GCU Voting: /api/voting/polls (API)');
    }
}