<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\GameType;
use App\Models\Event;
use App\Models\MediaFile;
use App\Models\QuestionPack;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LoadTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds only base data that is safe to run again, and never an account (O10)', function (): void {
    expect(Artisan::call('db:seed', ['--force' => true]))->toBe(0)
        ->and(Artisan::call('db:seed', ['--force' => true]))->toBe(0);

    // Accounts come from pentahoot:create-super-admin, never from a seeder with a known password.
    expect(User::query()->count())->toBe(0);
});

describe('demo and load-test data (O10)', function (): void {
    beforeEach(function (): void {
        Storage::fake('media');
    });

    it('builds a demo event with about 50 names, one pack per game type and a game from each', function (): void {
        $admin = User::factory()->create(['is_super_admin' => true]);

        expect(Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]))->toBe(0);

        $event = Event::query()->where('slug', 'demo-gathering')->sole();
        expect($event->owner_id)->toBe($admin->id)
            ->and($event->people()->count())->toBe(50)
            ->and(QuestionPack::query()->whereBelongsTo($admin, 'owner')->pluck('game_type')->map->value->sort()->values()->all())
            ->toBe(['pentahoot', 'tebak_gambar', 'tebak_kata'])
            ->and($event->games()->count())->toBe(3)
            ->and(MediaFile::query()->count())->toBeGreaterThan(0);

        // Safe to run again: the demo event is not made twice.
        expect(Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]))->toBe(0)
            ->and(Event::query()->where('slug', 'demo-gathering')->count())->toBe(1);
    });

    it('builds a load-test event with 500 names and an open Pentahoot game', function (): void {
        User::factory()->create(['is_super_admin' => true]);

        expect(Artisan::call('db:seed', ['--class' => LoadTestSeeder::class, '--force' => true]))->toBe(0);

        $event = Event::query()->where('slug', 'load-test')->sole();
        expect($event->people()->count())->toBe(500)
            ->and($event->people()->distinct()->count('name_normalized'))->toBe(500)
            ->and($event->status)->toBe(EventStatus::Open)
            ->and($event->games()->sole()->type)->toBe(GameType::Pentahoot);
    });

    it('refuses to run in production', function (string $seeder): void {
        User::factory()->create(['is_super_admin' => true]);
        app()->detectEnvironment(fn (): string => 'production');

        expect(fn () => Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]))->toThrow(RuntimeException::class, 'production');
        expect(Event::query()->count())->toBe(0);
    })->with([DemoSeeder::class, LoadTestSeeder::class]);

    it('asks for a super-admin first, because demo data needs an owner', function (): void {
        expect(fn () => Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]))
            ->toThrow(RuntimeException::class, 'pentahoot:create-super-admin');
    });
});
