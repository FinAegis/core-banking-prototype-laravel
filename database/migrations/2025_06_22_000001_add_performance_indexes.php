<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite index for account balance lookups
        Schema::table('account_balances', function (Blueprint $table) {
            if (!$this->indexExists('account_balances', 'idx_account_asset_balance')) {
                $table->index(['account_uuid', 'asset_code'], 'idx_account_asset_balance');
            }
        });
        
        // Add index for account lookups including frozen status
        Schema::table('accounts', function (Blueprint $table) {
            if (!$this->indexExists('accounts', 'idx_account_frozen_status')) {
                $table->index(['uuid', 'frozen'], 'idx_account_frozen_status');
            }
        });
        
        // Add indexes for asset transfer lookups
        if (Schema::hasTable('asset_transfers')) {
            Schema::table('asset_transfers', function (Blueprint $table) {
                if (!$this->indexExists('asset_transfers', 'idx_transfer_from_status')) {
                    $table->index(['from_account_uuid', 'status'], 'idx_transfer_from_status');
                }
                if (!$this->indexExists('asset_transfers', 'idx_transfer_to_status')) {
                    $table->index(['to_account_uuid', 'status'], 'idx_transfer_to_status');
                }
                if (!$this->indexExists('asset_transfers', 'idx_transfer_created_status')) {
                    $table->index(['created_at', 'status'], 'idx_transfer_created_status');
                }
            });
        }
        
        // Add indexes for transaction performance
        if (Schema::hasTable('asset_transactions')) {
            Schema::table('asset_transactions', function (Blueprint $table) {
                if (!$this->indexExists('asset_transactions', 'idx_transaction_account_asset_date')) {
                    $table->index(['account_uuid', 'asset_code', 'created_at'], 'idx_transaction_account_asset_date');
                }
            });
        }
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        // Skip check for SQLite as it's primarily used for testing
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }
        
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
        return count($indexes) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_balances', function (Blueprint $table) {
            $table->dropIndex('idx_account_asset_balance');
        });
        
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex('idx_account_frozen_status');
        });
        
        if (Schema::hasTable('asset_transfers')) {
            Schema::table('asset_transfers', function (Blueprint $table) {
                $table->dropIndex('idx_transfer_from_status');
                $table->dropIndex('idx_transfer_to_status');
                $table->dropIndex('idx_transfer_created_status');
            });
        }
        
        if (Schema::hasTable('asset_transactions')) {
            Schema::table('asset_transactions', function (Blueprint $table) {
                $table->dropIndex('idx_transaction_account_asset_date');
            });
        }
    }
};