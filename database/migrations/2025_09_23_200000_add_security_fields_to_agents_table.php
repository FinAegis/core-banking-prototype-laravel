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
        Schema::table('agents', function (Blueprint $table) {
            $table->text('public_key')->nullable()->after('metadata');
            $table->text('private_key_encrypted')->nullable()->after('public_key');
            $table->timestamp('key_rotated_at')->nullable()->after('private_key_encrypted');
            $table->integer('key_rotation_count')->default(0)->after('key_rotated_at');
            $table->string('signature_algorithm')->default('RS256')->after('key_rotation_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'public_key',
                'private_key_encrypted',
                'key_rotated_at',
                'key_rotation_count',
                'signature_algorithm',
            ]);
        });
    }
};
