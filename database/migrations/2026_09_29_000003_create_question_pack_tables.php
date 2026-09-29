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
        // D-9, G9: content owned by a host, copied into games when a game is created.
        Schema::create('question_packs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('game_type', 16);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'game_type']);
        });

        Schema::create('pack_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pack_id')->constrained('question_packs')->cascadeOnDelete();
            $table->unsignedInteger('position');
            // E16: weight of the question for Tebak games.
            $table->unsignedSmallInteger('points')->default(1);
            $table->timestamps();

            // G8
            $table->index(['pack_id', 'position']);
        });

        Schema::create('pack_question_pentahoot', function (Blueprint $table) {
            $table->foreignUuid('pack_question_id')->primary()->constrained('pack_questions')->cascadeOnDelete();
            $table->text('prompt');
            $table->unsignedSmallInteger('duration_seconds');
            $table->timestamps();
        });

        Schema::create('pack_question_kata', function (Blueprint $table) {
            $table->foreignUuid('pack_question_id')->primary()->constrained('pack_questions')->cascadeOnDelete();
            $table->text('prompt');
            $table->string('answer_text');
            $table->json('initial_open_indexes');
            $table->timestamps();
        });

        Schema::create('pack_question_gambar', function (Blueprint $table) {
            $table->foreignUuid('pack_question_id')->primary()->constrained('pack_questions')->cascadeOnDelete();
            $table->string('title');
            $table->string('answer_text');
            $table->foreignUuid('question_image_id')->constrained('media_files')->restrictOnDelete();
            $table->foreignUuid('answer_image_id')->constrained('media_files')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pack_question_gambar');
        Schema::dropIfExists('pack_question_kata');
        Schema::dropIfExists('pack_question_pentahoot');
        Schema::dropIfExists('pack_questions');
        Schema::dropIfExists('question_packs');
    }
};
