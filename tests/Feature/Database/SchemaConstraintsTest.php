<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use App\Models\Question;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('G6 id types', function (): void {
    it('gives public tables UUIDv7 keys', function (): void {
        $event = Event::factory()->create();

        expect(Str::isUuid($event->id))->toBeTrue()
            ->and($event->id[14])->toBe('7')
            ->and(Str::isUuid($event->owner()->firstOrFail()->id))->toBeTrue()
            ->and($event->owner()->firstOrFail()->id[14])->toBe('7');
    });

    it('gives votes an auto-increment integer key', function (): void {
        $vote = Vote::factory()->create();

        expect($vote->id)->toBeInt()->toBeGreaterThan(0);
    });
});

describe('G2 unique constraints', function (): void {
    it('rejects the same normalized name twice in one event', function (): void {
        $event = Event::factory()->create();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);

        Person::factory()->for($event)->create(['name' => 'budi santoso', 'name_normalized' => 'budi santoso']);
    })->throws(QueryException::class, 'Duplicate entry');

    it('allows the same name in different events', function (): void {
        Person::factory()->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);
        Person::factory()->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);

        expect(Person::query()->where('name_normalized', 'budi santoso')->count())->toBe(2);
    });

    it('rejects a second vote from the same person on one question', function (): void {
        $vote = Vote::factory()->create();

        Vote::factory()->create([
            'question_id' => $vote->question_id,
            'voter_person_id' => $vote->voter_person_id,
        ]);
    })->throws(QueryException::class, 'Duplicate entry');

    it('rejects a duplicate event slug', function (): void {
        Event::factory()->create(['slug' => 'gathering-2026']);

        Event::factory()->create(['slug' => 'gathering-2026']);
    })->throws(QueryException::class, 'Duplicate entry');
});

describe('G13 delete rules', function (): void {
    it('refuses to delete a person who cast a vote', function (): void {
        $vote = Vote::factory()->create();

        $vote->voter()->firstOrFail()->delete();
    })->throws(QueryException::class, 'Cannot delete or update a parent row');

    it('refuses to delete a game whose questions have votes (T8)', function (): void {
        $vote = Vote::factory()->create();

        $vote->question()->firstOrFail()->game()->firstOrFail()->delete();
    })->throws(QueryException::class, 'Cannot delete or update a parent row');

    it('refuses to delete a person who won a question', function (): void {
        $question = Question::factory()->create();
        $winner = Person::factory()->create(['event_id' => $question->game()->firstOrFail()->event_id]);
        $question->forceFill(['winner_person_id' => $winner->id])->save();

        $winner->delete();
    })->throws(QueryException::class, 'Cannot delete or update a parent row');

    it('deletes the questions of a game that was never played', function (): void {
        $game = Game::factory()->has(Question::factory()->count(3))->create();

        $game->delete();

        expect(Question::query()->count())->toBe(0);
    });
});

describe('G4 soft delete', function (): void {
    it('keeps a deleted event in the database and records who deleted it', function (): void {
        $event = Event::factory()->create();
        $admin = User::factory()->create();

        $event->forceFill(['deleted_by' => $admin->id])->save();
        $event->delete();

        expect(Event::query()->find($event->id))->toBeNull()
            ->and(Event::withTrashed()->find($event->id)?->deleted_by)->toBe($admin->id);
    });
});
