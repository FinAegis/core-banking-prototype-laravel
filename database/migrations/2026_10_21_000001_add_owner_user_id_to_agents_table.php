<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Record which user owns an Agent Protocol agent, so API key and session
     * management and A2A tasks can be limited to the owner.
     *
     * Existing rows are backfilled from metadata.registered_by, which only
     * the GraphQL registerAgent mutation writes (server side, from the
     * signed-in user). Agents created by the AI bridge (ai_domain_source)
     * and rows without a recorded registrant stay ownerless: only staff can
     * manage them.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('agents', 'owner_user_id')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->unsignedBigInteger('owner_user_id')->nullable()->after('organization');
                $table->index('owner_user_id');
            });
        }

        DB::table('agents')
            ->whereNull('owner_user_id')
            ->whereNotNull('metadata')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : null;

                    if (! is_array($metadata) || ! empty($metadata['ai_domain_source'])) {
                        continue;
                    }

                    $registeredBy = $metadata['registered_by'] ?? null;

                    if (! is_string($registeredBy) && ! is_int($registeredBy)) {
                        continue;
                    }

                    if (! ctype_digit((string) $registeredBy) || (int) $registeredBy <= 0) {
                        continue;
                    }

                    DB::table('agents')
                        ->where('id', $row->id)
                        ->update(['owner_user_id' => (int) $registeredBy]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('agents', 'owner_user_id')) {
            Schema::table('agents', function (Blueprint $table): void {
                $table->dropIndex(['owner_user_id']);
                $table->dropColumn('owner_user_id');
            });
        }
    }
};
