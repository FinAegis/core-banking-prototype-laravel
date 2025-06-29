<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_fingerprints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fingerprint_hash')->unique()->index(); // SHA-256 of device data
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreign('user_id', 'device_fingerprints_user_foreign')->references('id')->on('users');
            
            // Device Information
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->string('operating_system')->nullable();
            $table->string('os_version')->nullable();
            $table->string('browser')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('user_agent');
            
            // Hardware Information
            $table->string('screen_resolution')->nullable();
            $table->integer('screen_color_depth')->nullable();
            $table->string('timezone')->nullable();
            $table->string('language')->nullable();
            $table->json('installed_plugins')->nullable();
            $table->json('installed_fonts')->nullable();
            
            // Canvas Fingerprint
            $table->string('canvas_fingerprint')->nullable();
            $table->string('webgl_fingerprint')->nullable();
            $table->string('audio_fingerprint')->nullable();
            
            // Network Information
            $table->string('ip_address')->index();
            $table->string('ip_country')->nullable();
            $table->string('ip_region')->nullable();
            $table->string('ip_city')->nullable();
            $table->string('isp')->nullable();
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_tor')->default(false);
            
            // Behavioral Biometrics
            $table->json('typing_patterns')->nullable(); // Keystroke dynamics
            $table->json('mouse_patterns')->nullable(); // Mouse movement patterns
            $table->json('touch_patterns')->nullable(); // Touch gesture patterns
            
            // Trust Scoring
            $table->integer('trust_score')->default(50); // 0-100
            $table->integer('usage_count')->default(1);
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->string('block_reason')->nullable();
            
            // Activity Tracking
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->integer('successful_logins')->default(0);
            $table->integer('failed_logins')->default(0);
            $table->integer('suspicious_activities')->default(0);
            
            // Associations
            $table->json('associated_users')->nullable(); // Other users using this device
            $table->json('associated_accounts')->nullable(); // Accounts accessed from this device
            
            $table->timestamps();
            
            $table->index(['is_trusted', 'is_blocked']);
            $table->index('trust_score');
            $table->index(['ip_country', 'ip_region']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_fingerprints');
    }
};