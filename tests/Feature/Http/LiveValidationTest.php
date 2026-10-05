<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * U19: what Unpoly sends when a field with up-validate changes.
 *
 * @return array<string, string>
 */
function validating(Event $event, string $field, ?string $return = null): array
{
    return ['X-Up-Validate' => $field, 'X-Pentahoot-Return' => $return ?? url("/host/{$event->id}/people")];
}

describe('live validation (U19)', function (): void {
    it('checks a field without saving anything, and returns to the page of the form', function (): void {
        $event = Event::factory()->create();

        actingAs($event->owner()->firstOrFail())
            ->withHeaders(validating($event, 'name'))
            ->post("/host/{$event->id}/people", ['name' => 'Budi Santoso', 'form' => 'add-person'])
            ->assertRedirect("/host/{$event->id}/people")
            ->assertSessionHasNoErrors()
            ->assertSessionHasInput('name', 'Budi Santoso');

        expect(Person::query()->count())->toBe(0);
    });

    it('returns the error of the field, including rules that need the database', function (): void {
        $event = Event::factory()->create();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);

        actingAs($event->owner()->firstOrFail())
            ->withHeaders(validating($event, 'name'))
            ->post("/host/{$event->id}/people", ['name' => 'budi  SANTOSO', 'form' => 'add-person'])
            ->assertRedirect("/host/{$event->id}/people")
            ->assertSessionHasErrors('name');

        expect(Person::query()->count())->toBe(1);
    });

    it('never sends the browser to another site named in the header', function (): void {
        $event = Event::factory()->create();

        $response = actingAs($event->owner()->firstOrFail())
            ->from("/host/{$event->id}/people")
            ->withHeaders(validating($event, 'name', 'https://evil.example/steal'))
            ->post("/host/{$event->id}/people", ['name' => 'Budi Santoso']);

        $location = (string) $response->headers->get('Location');

        expect($location)->not->toContain('evil.example')
            ->and(str_starts_with($location, url('/')))->toBeTrue();
    });

    it('still saves a normal submit', function (): void {
        $event = Event::factory()->create();

        actingAs($event->owner()->firstOrFail())->post("/host/{$event->id}/people", ['name' => 'Budi Santoso']);

        expect(Person::query()->count())->toBe(1);
    });
});

it('lets the host pages check fields as they change, but not the sign in (U19)', function (): void {
    actingAs(User::factory()->create());

    expect((string) get('/host/events/create')->getContent())->toContain('up-validate')
        ->toContain('up-form-group');
    auth()->logout();
    expect((string) get('/login')->getContent())->not->toContain('up-validate');
});
