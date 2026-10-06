<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-9: content_version moves on every change a reload would copy; pack_version records the
 * version a game copied (empty for games copied before, read as 0). B-6: roster_version moves
 * when the host changes the name list, so claimed phones fetch their own state again.
 * W14: only new columns with defaults, safe while old containers still run during a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_packs', function (Blueprint $table): void {
            $table->unsignedInteger('content_version')->default(0)->after('game_type');
        });

        Schema::table('games', function (Blueprint $table): void {
            $table->unsignedInteger('pack_version')->nullable()->after('source_pack_id');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedInteger('roster_version')->default(0)->after('state_version');
        });
    }

    public function down(): void
    {
        Schema::table('question_packs', fn (Blueprint $table) => $table->dropColumn('content_version'));
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('pack_version'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('roster_version'));
    }
};
