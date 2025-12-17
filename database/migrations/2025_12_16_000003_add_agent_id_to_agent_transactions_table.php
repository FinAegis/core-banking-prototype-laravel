<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_transactions', 'agent_id')) {
                $table->string('agent_id')->nullable()->after('transaction_id');
                $table->index('agent_id');
            }

            if (! Schema::hasColumn('agent_transactions', 'counterparty_agent_id')) {
                $table->string('counterparty_agent_id')->nullable()->after('agent_id');
                $table->index('counterparty_agent_id');
            }

            if (! Schema::hasColumn('agent_transactions', 'transaction_type')) {
                $table->string('transaction_type')->nullable()->after('currency');
            }

            if (! Schema::hasColumn('agent_transactions', 'description')) {
                $table->text('description')->nullable()->after('status');
            }

            if (! Schema::hasColumn('agent_transactions', 'is_flagged')) {
                $table->boolean('is_flagged')->default(false)->after('metadata');
            }

            if (! Schema::hasColumn('agent_transactions', 'flag_reason')) {
                $table->string('flag_reason')->nullable()->after('is_flagged');
            }

            if (! Schema::hasColumn('agent_transactions', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('flag_reason');
            }

            if (! Schema::hasColumn('agent_transactions', 'reviewed_by')) {
                $table->string('reviewed_by')->nullable()->after('reviewed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_transactions', function (Blueprint $table) {
            $columns = [
                'agent_id',
                'counterparty_agent_id',
                'transaction_type',
                'description',
                'is_flagged',
                'flag_reason',
                'reviewed_at',
                'reviewed_by',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('agent_transactions', $column)) {
                    if (in_array($column, ['agent_id', 'counterparty_agent_id'])) {
                        $table->dropIndex([$column]);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
