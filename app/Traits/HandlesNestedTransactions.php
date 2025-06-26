<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait HandlesNestedTransactions
{
    /**
     * Execute a callback within a database transaction, handling nested transactions for tests.
     *
     * @param callable $callback
     * @return mixed
     */
    protected function executeInTransaction(callable $callback)
    {
        // Check if we're in a test environment with an existing transaction
        if ($this->isInTestTransaction()) {
            // Execute directly without starting a new transaction
            return $callback();
        }
        
        // Normal production behavior - use a transaction
        return DB::transaction($callback);
    }
    
    /**
     * Check if we're in a test environment with an active transaction.
     *
     * @return bool
     */
    protected function isInTestTransaction(): bool
    {
        return app()->environment('testing') && 
               DB::getDefaultConnection() === 'sqlite' && 
               DB::transactionLevel() > 0;
    }
}