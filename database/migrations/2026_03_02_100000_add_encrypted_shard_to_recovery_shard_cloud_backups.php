<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('recovery_shard_cloud_backups', function (Blueprint $table) {
            $table->binary('encrypted_shard')->nullable()->after('shard_version');
        });
    }

    public function down(): void
    {
        Schema::table('recovery_shard_cloud_backups', function (Blueprint $table) {
            $table->dropColumn('encrypted_shard');
        });
    }
};
