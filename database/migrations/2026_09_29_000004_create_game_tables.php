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
        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            // G9: trace of the copied pack only, never used to compute results.
            $table->foreignUuid('source_pack_id')->nullable()->constrained('question_packs')->nullOnDelete();
            $table->string('type', 16);
            $table->string('title');
            $table->unsignedInteger('position');
            $table->string('status', 16)->default('draft');
            // G7: the only marker of the question on screen. Its foreign key is added below.
            $table->uuid('current_question_id')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });

        // G13: questions cascade with an unplayed game; votes and results
        // restrict the delete once the game has been played (T8).
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignUuid('source_pack_question_id')->nullable()->constrained('pack_questions')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedSmallInteger('points')->default(1);
            $table->string('status', 16);
            $table->boolean('skip_used')->default(false);
            $table->timestamp('answer_revealed_at')->nullable();
            $table->foreignUuid('winner_person_id')->nullable()->constrained('people')->restrictOnDelete();
            // G1, E10: millisecond precision for the tie-break.
            $table->timestamp('resolved_at', 3)->nullable();
            $table->timestamps();

            // G8
            $table->index(['game_id', 'status', 'position']);
        });

        Schema::create('question_pentahoot', function (Blueprint $table) {
            $table->foreignUuid('question_id')->primary()->constrained('questions')->cascadeOnDelete();
            $table->text('prompt');
            $table->unsignedSmallInteger('duration_seconds');
            $table->unsignedSmallInteger('attempt')->default(1);
            // F4, E3: votes are accepted until ends_at + 1 second.
            $table->timestamp('ends_at', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('question_kata', function (Blueprint $table) {
            $table->foreignUuid('question_id')->primary()->constrained('questions')->cascadeOnDelete();
            $table->text('prompt');
            $table->string('answer_text');
            $table->json('initial_open_indexes');
            $table->json('opened_indexes');
            $table->timestamps();
        });

        Schema::create('question_gambar', function (Blueprint $table) {
            $table->foreignUuid('question_id')->primary()->constrained('questions')->cascadeOnDelete();
            $table->string('title');
            $table->string('answer_text');
            $table->foreignUuid('question_image_id')->constrained('media_files')->restrictOnDelete();
            $table->foreignUuid('answer_image_id')->constrained('media_files')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('active_game_id')->references('id')->on('games')->nullOnDelete();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreign('current_question_id')->references('id')->on('questions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['current_question_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['active_game_id']);
        });

        Schema::dropIfExists('question_gambar');
        Schema::dropIfExists('question_kata');
        Schema::dropIfExists('question_pentahoot');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('games');
    }
};
