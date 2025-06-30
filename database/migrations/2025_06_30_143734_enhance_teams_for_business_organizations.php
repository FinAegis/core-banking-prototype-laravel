<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Business organization fields
            $table->boolean('is_business_organization')->default(false)->after('personal_team');
            $table->string('organization_type')->nullable()->after('is_business_organization');
            $table->string('business_registration_number')->nullable()->after('organization_type');
            $table->string('tax_id')->nullable()->after('business_registration_number');
            $table->json('business_details')->nullable()->after('tax_id');
            
            // Limits and settings
            $table->integer('max_users')->default(5)->after('business_details');
            $table->json('allowed_roles')->nullable()->after('max_users');
            
            // Indexes
            $table->index('is_business_organization');
            $table->index('business_registration_number');
        });
        
        // Create team_user_roles table for team-specific roles
        Schema::create('team_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->json('permissions')->nullable();
            $table->timestamps();
            
            $table->unique(['team_id', 'user_id', 'role']);
            $table->index(['team_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_user_roles');
        
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['is_business_organization']);
            $table->dropIndex(['business_registration_number']);
            
            $table->dropColumn([
                'is_business_organization',
                'organization_type',
                'business_registration_number',
                'tax_id',
                'business_details',
                'max_users',
                'allowed_roles'
            ]);
        });
    }
};