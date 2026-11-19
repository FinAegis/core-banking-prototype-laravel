<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Agent spending limits are owned by a team, or by a single user when
        // the user has no team (previously every user without a team shared
        // the team_id NULL rows). MPP agent ids become unique per owner, like
        // x402, instead of across the platform.
        Schema::table('mpp_spending_limits', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('team_id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['agent_id']);
            $table->unique(['agent_id', 'team_id']);
            $table->unique(['agent_id', 'user_id']);
        });

        Schema::table('x402_spending_limits', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('team_id')->constrained()->cascadeOnDelete();
            $table->unique(['agent_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('x402_spending_limits', function (Blueprint $table): void {
            $table->dropUnique(['agent_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('mpp_spending_limits', function (Blueprint $table): void {
            $table->dropUnique(['agent_id', 'user_id']);
            $table->dropUnique(['agent_id', 'team_id']);
            $table->unique(['agent_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
