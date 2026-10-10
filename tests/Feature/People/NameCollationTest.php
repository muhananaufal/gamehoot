<?php

declare(strict_types=1);

use App\Actions\People\EditNameList;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * B-8 (audit DATA-1): "José" and "Jose" are one name for the unique index (utf8mb4_unicode_ci).
 * Adding both used to pass every check and end in an error 500 when the second row hit the index.
 */
describe('names the database sees as equal (B-8)', function (): void {
    it('refuses them in the New event wizard with a message, never an error 500', function (): void {
        $host = User::factory()->create();

        actingAs($host)->from('/host/events/create')->post('/host/events', [
            'name' => 'Year-End Party',
            'slug' => 'year-end-party',
            'screen_theme' => 'dark',
            'names' => "José A\nJose A\nRita",
        ])->assertRedirect('/host/events/create')->assertSessionHasErrors('names');

        expect(Event::query()->count())->toBe(0)
            ->and(Person::query()->count())->toBe(0);
    });

    it('marks the clash in an import before anything is saved', function (): void {
        $event = Event::factory()->create();
        Person::factory()->for($event)->create(['name' => 'Élodie', 'name_normalized' => 'élodie']);

        actingAs($event->owner()->firstOrFail())->post("/host/{$event->id}/people/import/confirm", ['names' => ['ELODIE', 'Strauß', 'Strauss']])
            ->assertSessionHasErrors(['names.0', 'names.2']);

        expect($event->people()->count())->toBe(1);
    });

    it('turns a clash only the database finds into a message for its line, and saves nothing', function (): void {
        $event = Event::factory()->create();

        // The checks before saving are bypassed here, as two hosts saving at once would.
        $errors = [];

        try {
            app(EditNameList::class)->import($event, ['Rita', 'José', 'JOSÉ'], $event->owner()->firstOrFail());
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
        }

        expect($errors)->toHaveKey('names.2')
            ->and($errors)->toHaveKey('names')
            ->and($event->people()->count())->toBe(0);
    });
});
