<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E7, E9: when the host revealed the answer image of a Tebak Gambar question. Skip is off from
 * then on. Nullable, so running containers of the previous release keep working during a deploy (W14).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('question_gambar', function (Blueprint $table) {
            $table->timestamp('revealed_at')->nullable()->after('answer_image_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_gambar', function (Blueprint $table) {
            $table->dropColumn('revealed_at');
        });
    }
};
