<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * F26: the key in the name of an event's host channel (event.{uuid}.host.{key}), replaced when
 * someone loses access. W14: nullable, so a container of the previous release that still runs
 * during the deploy can create events; the model fills a missing key when it is first needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('host_channel_key', 32)->nullable()->after('roster_version');
        });

        foreach (DB::table('events')->whereNull('host_channel_key')->pluck('id') as $id) {
            DB::table('events')->where('id', $id)->update(['host_channel_key' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('host_channel_key');
        });
    }
};
