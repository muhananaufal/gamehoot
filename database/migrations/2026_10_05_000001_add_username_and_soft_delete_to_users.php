<?php

declare(strict_types=1);

use App\Accounts\Username;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-6: a unique username for signing in; C-7: deleting an account keeps it (soft delete).
 * W12: the column is nullable while old containers still create accounts without one during a
 * deploy; NOT NULL follows in a later release. Existing accounts get a username from their name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique()->after('name');
            $table->softDeletes();
        });

        $taken = [];

        foreach (DB::table('users')->orderBy('created_at')->orderBy('id')->get(['id', 'name']) as $user) {
            $username = Username::fromName((string) $user->name, fn (string $candidate): bool => isset($taken[$candidate]));
            $taken[$username] = true;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'deleted_at']);
        });
    }
};
