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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            // D-3: renamed to {slug}--deleted-{id} on delete so the slug can be reused.
            $table->string('slug')->unique();
            $table->string('status', 16)->default('draft');
            $table->timestamp('names_locked_at')->nullable();
            $table->timestamp('join_locked_at')->nullable();
            $table->boolean('show_on_devices')->default(false);
            $table->string('screen_theme', 8)->default('dark');
            // G7: the only marker of the active game. Its foreign key is added once games exists.
            $table->uuid('active_game_id')->nullable();
            $table->unsignedBigInteger('state_version')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreignUuid('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('owner_id');
        });

        // C-2: co-hosts. G13: composite primary key, no id of its own.
        Schema::create('event_hosts', function (Blueprint $table) {
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['event_id', 'user_id']);
        });

        Schema::create('people', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('name_normalized', 100);
            // B-1: random token of the personal link.
            $table->string('join_token', 64)->unique();
            // G3: only the hash of the device claim token is stored.
            $table->char('claim_token_hash', 64)->nullable()->unique();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            // G2, B-4: duplicate names never reach the database.
            $table->unique(['event_id', 'name_normalized']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
        Schema::dropIfExists('event_hosts');
        Schema::dropIfExists('events');
    }
};
