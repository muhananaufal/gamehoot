<?php

declare(strict_types=1);

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
        // G6: internal tables use bigint ids. G13: foreign keys used by results restrict deletes.
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('question_id')->constrained('questions')->restrictOnDelete();
            $table->foreignUuid('voter_person_id')->constrained('people')->restrictOnDelete();
            $table->foreignUuid('target_person_id')->constrained('people')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->timestamps();

            // G2: one vote per person per question. Reset (T1) deletes the votes first.
            $table->unique(['question_id', 'voter_person_id']);
            // G8
            $table->index(['question_id', 'attempt', 'target_person_id']);
        });

        // G10: top 5 frozen at Reveal, per attempt.
        Schema::create('question_results', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->foreignUuid('person_id')->constrained('people')->restrictOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->unsignedInteger('votes');
            $table->timestamp('frozen_at');
            $table->timestamps();

            $table->unique(['question_id', 'attempt', 'person_id']);
        });

        // G10: final leaderboard frozen when the game finishes.
        Schema::create('game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->restrictOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->unsignedInteger('points');
            // E10: tie-break, the earlier to reach the score ranks higher.
            $table->timestamp('reached_at', 3);
            $table->timestamp('frozen_at');
            $table->timestamps();

            $table->unique(['game_id', 'person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_results');
        Schema::dropIfExists('question_results');
        Schema::dropIfExists('votes');
    }
};
