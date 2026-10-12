<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-9: when the copied questions of a game were last edited in the event (D-2), so the Games page
 * can mark it and Reload can warn that the edits go. W14: a new nullable column only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('pack_version');
        });
    }

    public function down(): void
    {
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('edited_at'));
    }
};
