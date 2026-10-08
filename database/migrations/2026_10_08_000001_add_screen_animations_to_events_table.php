<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * U31: whether the Public View plays Penta's idle bursts and confetti. On by default; a host
 * turns it off for a formal event or a weak projector laptop. W14: a new column with a default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('screen_animations')->default(true)->after('show_on_devices');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('screen_animations');
        });
    }
};
