<?php

declare(strict_types=1);

use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function eventWithOwner(): Event
{
    return Event::factory()->for(User::factory(), 'owner')->create(['slug' => 'gathering-2026']);
}

function hostOf(Event $event): User
{
    return $event->owner()->firstOrFail();
}

/**
 * A real upload: its MIME type is detected from the bytes, like in production. The fake files
 * of the testing helpers report a type from the file name instead.
 */
function realUpload(string $contents, string $name = 'names.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents((string) $path, $contents);

    return new UploadedFile((string) $path, $name, null, null, true);
}

function csvUpload(string $contents): UploadedFile
{
    return realUpload($contents);
}

describe('name list (B-3, B-4)', function (): void {
    it('lists names with their status and personal link', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'join_token' => 'k7Qx9mP2k7Qx9mP2', 'claimed_at' => now()]);
        Person::factory()->for($event)->create(['name' => 'Yusuf Hidayat']);

        actingAs(hostOf($event))->get("/host/{$event->id}/people")
            ->assertOk()
            ->assertSeeInOrder(['Budi Santoso', 'Playing on a phone', '/gathering-2026/j/k7Qx9mP2k7Qx9mP2', 'Yusuf Hidayat', 'Not joined']);
    });

    it('is closed to hosts of other events', function (): void {
        $event = eventWithOwner();

        actingAs(User::factory()->create())->get("/host/{$event->id}/people")->assertForbidden();
    });

    it('adds a name with tidy spacing and a random personal link', function (): void {
        $event = eventWithOwner();

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['name' => '  Budi   Santoso '])
            ->assertRedirect("/host/{$event->id}/people");

        $person = Person::query()->firstOrFail();
        expect($person->name)->toBe('Budi Santoso')
            ->and($person->name_normalized)->toBe('budi santoso')
            ->and($person->join_token)->toMatch('/^[A-Za-z0-9]{16}$/');
    });

    it('rejects a duplicate name, ignoring case and spaces', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['name' => 'BUDI  santoso'])
            ->assertSessionHasErrors('name');

        expect(Person::query()->count())->toBe(1);
    });

    it('renames a name, still rejecting duplicates but not itself', function (): void {
        $event = eventWithOwner();
        $budi = Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);
        Person::factory()->for($event)->create(['name' => 'Rita', 'name_normalized' => 'rita']);

        actingAs(hostOf($event))->put("/host/{$event->id}/people/{$budi->id}", ['name' => 'rita'])->assertSessionHasErrors('name');
        actingAs(hostOf($event))->put("/host/{$event->id}/people/{$budi->id}", ['name' => 'budi santoso'])->assertSessionHasNoErrors();

        expect($budi->refresh()->name)->toBe('budi santoso');
    });

    it('deletes a name', function (): void {
        $event = eventWithOwner();
        $person = Person::factory()->for($event)->create();

        actingAs(hostOf($event))->delete("/host/{$event->id}/people/{$person->id}")->assertRedirect("/host/{$event->id}/people");

        expect(Person::query()->count())->toBe(0);
    });

    it('stops at 500 names per event (T9)', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->count(500)->create();

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['name' => 'One Too Many'])
            ->assertSessionHasErrors('name');
    });

    it('refuses to change the list once it is locked (B-3)', function (): void {
        $event = eventWithOwner();
        $person = Person::factory()->for($event)->create();
        $event->forceFill(['names_locked_at' => now()])->save();
        $host = hostOf($event);

        actingAs($host)->post("/host/{$event->id}/people", ['name' => 'Late Guest'])->assertSessionHasErrors('names');
        actingAs($host)->put("/host/{$event->id}/people/{$person->id}", ['name' => 'Renamed'])->assertSessionHasErrors('names');
        actingAs($host)->delete("/host/{$event->id}/people/{$person->id}")->assertSessionHasErrors('names');
        actingAs($host)->post("/host/{$event->id}/people/import", ['file' => csvUpload("Late\n")])->assertSessionHasErrors('names');

        expect(Person::query()->count())->toBe(1);
        actingAs($host)->get("/host/{$event->id}/people")->assertSee('Name list is locked');
    });
});

describe('CSV import with preview (B-4, T4)', function (): void {
    it('shows a preview that flags duplicates in the file and with existing names, and saves nothing', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Rita Wulandari', 'name_normalized' => 'rita wulandari']);

        actingAs(hostOf($event))
            ->post("/host/{$event->id}/people/import", ['file' => csvUpload("Budi Santoso;IT\nbudi santoso;HR\nRITA wulandari;HR\nNew Person;X\n")])
            ->assertOk()
            ->assertSee('Separator ";" detected')
            ->assertSee('Same as row 1.')
            ->assertSee('Same as “Rita Wulandari” already in this event', false);

        expect(Person::query()->count())->toBe(1);
    });

    it('saves all names at once when none is a duplicate', function (): void {
        $event = eventWithOwner();

        actingAs(hostOf($event))
            ->post("/host/{$event->id}/people/import/confirm", ['names' => ['Budi Santoso', 'Budi Santoso (IT)', 'Rita']])
            ->assertRedirect("/host/{$event->id}/people");

        expect(Person::query()->where('event_id', $event->id)->orderBy('name')->pluck('name')->all())
            ->toBe(['Budi Santoso', 'Budi Santoso (IT)', 'Rita']);
    });

    it('validates again on save and saves nothing if a duplicate is left', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Rita', 'name_normalized' => 'rita']);

        actingAs(hostOf($event))
            ->post("/host/{$event->id}/people/import/confirm", ['names' => ['Budi', 'budi ', 'RITA']])
            ->assertRedirect("/host/{$event->id}/people/import")
            ->assertSessionHasErrors(['names.1', 'names.2']);

        expect(Person::query()->count())->toBe(1);

        // The review comes back with the same rows and problems instead of an empty upload form.
        actingAs(hostOf($event))->get("/host/{$event->id}/people/import")
            ->assertOk()
            ->assertSee('Review 3 names')
            ->assertSee('Same as row 1.');
    });

    it('rejects files that are not text, judged by content and not by name (O7)', function (): void {
        $event = eventWithOwner();
        // A real 1x1 PNG saved with a .csv name.
        $png = realUpload((string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ));

        actingAs(hostOf($event))
            ->post("/host/{$event->id}/people/import", ['file' => $png])
            ->assertSessionHasErrors('file');
    });
});

describe('claims and personal links (B-1, B-6, T5)', function (): void {
    it('releases a claimed name and logs it without the name (E13, K5)', function (): void {
        $event = eventWithOwner();
        $person = Person::factory()->for($event)->create(['claim_token_hash' => hash('sha256', 'token'), 'claimed_at' => now()]);

        actingAs(hostOf($event))->post("/host/{$event->id}/people/{$person->id}/release-claim")
            ->assertRedirect("/host/{$event->id}/people");

        $person->refresh();
        $log = ActionLog::query()->firstOrFail();
        expect($person->claim_token_hash)->toBeNull()
            ->and($person->claimed_at)->toBeNull()
            ->and($log->action)->toBe(LoggedAction::ClaimReleased)
            ->and($log->payload)->toBe(['person_id' => $person->id]);
    });

    it('releases claims even after the list is locked (B-5, B-6)', function (): void {
        $event = eventWithOwner();
        $person = Person::factory()->for($event)->create(['claim_token_hash' => hash('sha256', 'token'), 'claimed_at' => now()]);
        $event->forceFill(['names_locked_at' => now()])->save();

        actingAs(hostOf($event))->post("/host/{$event->id}/people/{$person->id}/release-claim");

        expect($person->refresh()->claimed_at)->toBeNull();
    });

    it('makes a new personal link when the old one leaked (T5)', function (): void {
        $event = eventWithOwner();
        $person = Person::factory()->for($event)->create(['join_token' => 'oldtokenoldtoken']);

        actingAs(hostOf($event))->post("/host/{$event->id}/people/{$person->id}/regenerate-link");

        expect($person->refresh()->join_token)->not->toBe('oldtokenoldtoken')->toMatch('/^[A-Za-z0-9]{16}$/');
    });

    it('exports every personal link as CSV (T5)', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'join_token' => 'k7Qx9mP2k7Qx9mP2']);

        $response = actingAs(hostOf($event))->get("/host/{$event->id}/people/links.csv");

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        expect($response->streamedContent())
            ->toContain('Name,Link')
            ->toContain('"Budi Santoso",'.url('/gathering-2026/j/k7Qx9mP2k7Qx9mP2'));
    });

    it('neutralises names that Excel would run as formulas', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => '=HYPERLINK("x")', 'name_normalized' => '=hyperlink("x")']);

        $csv = actingAs(hostOf($event))->get("/host/{$event->id}/people/links.csv")->streamedContent();

        expect($csv)->toContain("'=HYPERLINK");
        expect($csv)->not->toContain("\n\"=HYPERLINK");
    });

    it('does not reach a person through another event', function (): void {
        $event = eventWithOwner();
        $other = Event::factory()->for(hostOf($event), 'owner')->create();
        $person = Person::factory()->for($other)->create();

        actingAs(hostOf($event))->post("/host/{$event->id}/people/{$person->id}/release-claim")->assertNotFound();
    });
});
