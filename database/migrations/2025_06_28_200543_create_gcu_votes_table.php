<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gcu_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('gcu_voting_proposals')->onDelete('cascade');
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid');
            $table->enum('vote', ['for', 'against', 'abstain']);
            $table->decimal('voting_power', 20, 4); // GCU balance at time of vote
            $table->string('signature')->nullable(); // Cryptographic signature for verification
            $table->json('metadata')->nullable(); // Additional vote metadata
            $table->timestamps();
            
            // Ensure one vote per user per proposal
            $table->unique(['proposal_id', 'user_uuid']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gcu_votes');
    }
};