<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gcu_voting_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->json('proposed_composition'); // {"USD": 30, "EUR": 25, ...}
            $table->json('current_composition'); // Snapshot of current composition at proposal time
            $table->text('rationale');
            $table->enum('status', ['draft', 'active', 'closed', 'implemented', 'rejected']);
            $table->dateTime('voting_starts_at');
            $table->dateTime('voting_ends_at');
            $table->decimal('minimum_participation', 5, 2)->default(10.00); // % of total GCU supply
            $table->decimal('minimum_approval', 5, 2)->default(50.00); // % of votes to pass
            $table->decimal('total_gcu_supply', 20, 4)->nullable(); // Snapshot at voting start
            $table->decimal('total_votes_cast', 20, 4)->default(0);
            $table->decimal('votes_for', 20, 4)->default(0);
            $table->decimal('votes_against', 20, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->dateTime('implemented_at')->nullable();
            $table->json('implementation_details')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'voting_starts_at']);
            $table->index(['status', 'voting_ends_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('gcu_voting_proposals');
    }
};