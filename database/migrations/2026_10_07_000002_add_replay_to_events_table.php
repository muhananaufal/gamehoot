<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-11: the past moment the Public View replays, if any: its kind, its game and, for a question
 * moment, its question. W14: new nullable columns only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('replay_kind', 16)->nullable()->after('active_game_id');
            $table->foreignUuid('replay_game_id')->nullable()->after('replay_kind')->constrained('games')->nullOnDelete();
            $table->foreignUuid('replay_question_id')->nullable()->after('replay_game_id')->constrained('questions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replay_question_id');
            $table->dropConstrainedForeignId('replay_game_id');
            $table->dropColumn('replay_kind');
        });
    }
};
