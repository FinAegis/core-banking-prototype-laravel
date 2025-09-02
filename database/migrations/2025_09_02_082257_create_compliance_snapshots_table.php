<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('compliance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid');
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamp('created_at');

            $table->index(['aggregate_uuid', 'aggregate_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_snapshots');
    }
};
