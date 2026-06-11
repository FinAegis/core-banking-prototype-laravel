<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The AgentTransaction model (AML flagging: is_flagged/flag_reason/
     * reviewed_*) was written against columns the original
     * agent_transactions migration never created — the compliance queries
     * only "worked" on SQLite test schemas and broke on MySQL. Additive
     * columns so both the original payment shape (from/to_agent_id, type)
     * and the compliance shape coexist.
     */
    public function up(): void
    {
        Schema::table('agent_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_transactions', 'agent_id')) {
                $table->string('agent_id')->nullable()->index();
            }
            if (! Schema::hasColumn('agent_transactions', 'counterparty_agent_id')) {
                $table->string('counterparty_agent_id')->nullable();
            }
            if (! Schema::hasColumn('agent_transactions', 'transaction_type')) {
                $table->string('transaction_type')->nullable();
            }
            if (! Schema::hasColumn('agent_transactions', 'description')) {
                $table->string('description')->nullable();
            }
            if (! Schema::hasColumn('agent_transactions', 'is_flagged')) {
                $table->boolean('is_flagged')->default(false)->index();
            }
            if (! Schema::hasColumn('agent_transactions', 'flag_reason')) {
                $table->string('flag_reason')->nullable();
            }
            if (! Schema::hasColumn('agent_transactions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('agent_transactions', 'reviewed_by')) {
                $table->string('reviewed_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'agent_id',
                'counterparty_agent_id',
                'transaction_type',
                'description',
                'is_flagged',
                'flag_reason',
                'reviewed_at',
                'reviewed_by',
            ]);
        });
    }
};
