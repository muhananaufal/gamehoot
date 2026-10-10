<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\People\NameCsv;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

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
            ->assertSeeInOrder(['Budi Santoso', 'Playing', '/j/k7Qx9mP2k7Qx9mP2', 'Yusuf Hidayat', 'Not joined'])
            ->assertDontSee('Playing on a phone');
    });

    it('is closed to hosts of other events', function (): void {
        $event = eventWithOwner();

        actingAs(User::factory()->create())->get("/host/{$event->id}/people")->assertForbidden();
    });

    it('adds a name with tidy spacing and a random personal link', function (): void {
        $event = eventWithOwner();

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['names' => '  Budi   Santoso '])
            ->assertRedirect("/host/{$event->id}/people")
            ->assertSessionHas('status', '1 name added.');

        $person = Person::query()->firstOrFail();
        expect($person->name)->toBe('Budi Santoso')
            ->and($person->name_normalized)->toBe('budi santoso')
            ->and($person->join_token)->toMatch('/^[A-Za-z0-9]{16}$/');
    });

    it('adds several names at once, one per line, skipping empty lines (B-4)', function (): void {
        $event = eventWithOwner();

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['names' => implode(PHP_EOL, ['Rita Wulandari', '', '  Budi Santoso', 'Ana Putri  ', ' '])])
            ->assertRedirect("/host/{$event->id}/people")
            ->assertSessionHas('status', '3 names added.');

        expect($event->people()->orderBy('name')->pluck('name')->all())->toBe(['Ana Putri', 'Budi Santoso', 'Rita Wulandari']);
    });

    it('rejects a duplicate name, ignoring case and spaces, and saves none of the lines', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['names' => implode(PHP_EOL, ['Rita Wulandari', 'BUDI  santoso'])])
            ->assertSessionHasErrors(['names' => 'Line 2: Same as “Budi Santoso” already in this event (upper/lower case and accents are ignored).']);
        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['names' => implode(PHP_EOL, ['Ana Putri', 'ana  PUTRI'])])
            ->assertSessionHasErrors(['names' => 'Line 2: Same as line 1. Add something to tell them apart, e.g. “ana PUTRI (IT)”.']);

        expect(Person::query()->count())->toBe(1);
    });

    it('names the lines of the text area, empty lines counted, like the page does', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['names' => implode(PHP_EOL, ['Ana Putri', '', 'ana  PUTRI', '', 'budi santoso'])])
            ->assertSessionHasErrors(['names' => 'Line 3: Same as line 1. Add something to tell them apart, e.g. “ana PUTRI (IT)”.'])
            ->assertSessionHasErrors(['names' => 'Line 5: Same as “Budi Santoso” already in this event (upper/lower case and accents are ignored).']);
    });

    it('rejects an empty list and a name longer than 100 characters', function (): void {
        $event = eventWithOwner();

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['names' => implode(PHP_EOL, [' ', ''])])
            ->assertSessionHasErrors('names');
        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['names' => implode(PHP_EOL, ['Rita Wulandari', str_repeat('A', 101)])])
            ->assertSessionHasErrors('names');

        expect(Person::query()->count())->toBe(0);
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

        actingAs(hostOf($event))->post("/host/{$event->id}/people", ['names' => 'One Too Many'])
            ->assertSessionHasErrors('names');
    });

    it('refuses to change the list once it is locked (B-3)', function (): void {
        $event = eventWithOwner();
        $person = Person::factory()->for($event)->create();
        $event->forceFill(['names_locked_at' => now()])->save();
        $host = hostOf($event);

        actingAs($host)->post("/host/{$event->id}/people", ['names' => 'Late Guest'])->assertSessionHasErrors('names');
        actingAs($host)->put("/host/{$event->id}/people/{$person->id}", ['name' => 'Renamed'])->assertSessionHasErrors('names');
        actingAs($host)->delete("/host/{$event->id}/people/{$person->id}")->assertSessionHasErrors('names');
        actingAs($host)->post("/host/{$event->id}/people/import", ['file' => csvUpload("Late\n")])->assertSessionHasErrors('names');

        expect(Person::query()->count())->toBe(1);
        actingAs($host)->get("/host/{$event->id}/people")->assertSee('Name list is locked');
    });

    it('tells whether new claims are still open, and locks or unlocks them right there', function (): void {
        $event = eventWithOwner();
        $event->forceFill(['names_locked_at' => now()])->save();
        $host = hostOf($event);
        $lockForm = 'action="'.route('host.events.join-lock', $event).'"';

        actingAs($host)->get("/host/{$event->id}/people")
            ->assertSee('People who have not joined can still pick their name.')
            ->assertSee($lockForm, false)
            ->assertSee('Lock claims');

        $event->forceFill(['join_locked_at' => now()])->save();
        actingAs($host)->get("/host/{$event->id}/people")
            ->assertSee('New claims are locked too: nobody new can pick a name.')
            ->assertDontSee('can still pick their name')
            ->assertSee('Unlock claims');
    });
});

describe('CSV import with preview (B-4, T4)', function (): void {
    it('marks a name that is too long in the review, and says so when saving anyway', function (): void {
        $event = eventWithOwner();
        $long = str_repeat('x', 101);

        actingAs(hostOf($event))->post("/host/{$event->id}/people/import", ['file' => csvUpload("Budi\n{$long}\n")])
            ->assertOk()
            ->assertSee('Longer than 100 characters. Shorten it to save.');

        actingAs(hostOf($event))->post("/host/{$event->id}/people/import/confirm", ['names' => ['Budi', $long]])
            ->assertSessionHasErrors(['names.1' => 'Longer than 100 characters. Shorten it to save.']);

        // The review that comes back names the real problem, not duplicates.
        actingAs(hostOf($event))->get("/host/{$event->id}/people/import")
            ->assertSee('Longer than 100 characters. Shorten it to save.')
            ->assertDontSee('Fix the duplicates');
    });

    it('says in the review how many more names fit in the event (B-3)', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->count(499)->create();

        actingAs(hostOf($event))->post("/host/{$event->id}/people/import", ['file' => csvUpload("Budi Santoso\nRita Wulandari\n")])
            ->assertOk()
            ->assertSee('Only 1 more name fits in this event (at most 500). Remove rows to save.');
    });

    it('shows a preview that flags duplicates in the file and with existing names, and saves nothing', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Rita Wulandari', 'name_normalized' => 'rita wulandari']);

        actingAs(hostOf($event))
            ->post("/host/{$event->id}/people/import", ['file' => csvUpload("Budi Santoso;IT\nbudi santoso;HR\nRITA wulandari;HR\nNew Person;X\n")])
            ->assertOk()
            ->assertSee('Separator ";" detected')
            ->assertSee('Same as row 1.')
            ->assertSee('Same as “Rita Wulandari” already in this event', false);

        // Directives inside component attributes would reach Alpine as raw text.
        actingAs(hostOf($event))
            ->post("/host/{$event->id}/people/import", ['file' => csvUpload('Budi
')])
            ->assertDontSee('@js(', false);

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
            // The example to tell them apart uses the clashing name, not a fixed one.
            ->assertSessionHasErrors(['names.1' => 'Same as row 1. Add something to tell them apart, e.g. “budi (IT)”.', 'names.2']);

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
            ->toStartWith("\u{FEFF}Name,Link")
            ->toContain('"Budi Santoso",'.url('/gathering-2026/j/k7Qx9mP2k7Qx9mP2'));
    });

    it('neutralises names that Excel would run as formulas', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => '=HYPERLINK("x")', 'name_normalized' => '=hyperlink("x")']);

        $csv = actingAs(hostOf($event))->get("/host/{$event->id}/people/links.csv")->streamedContent();

        expect($csv)->toContain("'=HYPERLINK");
        expect($csv)->not->toContain("\n\"=HYPERLINK");
    });

    it('also neutralises the full-width signs that Excel reads as formulas (audit SEC-5)', function (): void {
        $event = eventWithOwner();

        foreach (['＝SUM(A1)', '＋1', '－1', '＠cell'] as $name) {
            Person::factory()->for($event)->create(['name' => $name, 'name_normalized' => mb_strtolower($name)]);
        }

        $csv = actingAs(hostOf($event))->get("/host/{$event->id}/people/links.csv")->streamedContent();

        expect($csv)->toContain("'＝SUM(A1)")->toContain("'＋1")->toContain("'－1")->toContain("'＠cell");
    });

    it('does not reach a person through another event', function (): void {
        $event = eventWithOwner();
        $other = Event::factory()->for(hostOf($event), 'owner')->create();
        $person = Person::factory()->for($other)->create();

        actingAs(hostOf($event))->post("/host/{$event->id}/people/{$person->id}/release-claim")->assertNotFound();
    });
});

describe('the Names page (U2, U4, U5, U6)', function (): void {
    it('puts Add name in the top bar and lets the list be searched and filtered in the browser', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'claimed_at' => now(), 'claim_token_hash' => hash('sha256', 'x')]);
        Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);

        $html = (string) actingAs(hostOf($event))->get("/host/{$event->id}/people")->assertOk()->getContent();

        expect(Str::between($html, '<header', '</header>'))->not->toContain('Add name')
            ->and(Str::after($html, '<main'))->toContain('Add name')
            ->and($html)->toContain('x-data="tableView(')
            ->and($html)->toMatch('/<tr data-row [^>]*data-search="budi santoso[^"]*" data-filter="joined"/')
            ->and($html)->toMatch('/<tr data-row [^>]*data-search="rita wulandari[^"]*" data-filter="waiting"/')
            ->and($html)->toContain('aria-label="Search name or link"');
    });

    it('says when each name joined', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'claimed_at' => CarbonImmutable::parse('2026-10-15 03:05:00'), 'claim_token_hash' => hash('sha256', 'x')]);

        actingAs(hostOf($event))->get("/host/{$event->id}/people")->assertSee('Joined 15 Oct, 10:05');
    });

    it('asks before deleting a name, and keeps one confirmation dialog for the whole page', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->count(30)->create();

        $html = (string) actingAs(hostOf($event))->get("/host/{$event->id}/people")->getContent();

        // The header's Export links and Add name dialogs, plus the one dialog every row shares.
        expect(substr_count($html, '<dialog'))->toBeLessThanOrEqual(3)
            ->and(substr_count($html, 'data-delete="1"'))->toBe(30)
            ->and($html)->not->toContain('variant="link-destructive"');
    });

    it('stays light for a list of 480 names', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->count(480)->sequence(fn ($s) => ['name' => "Participant Number {$s->index}"])->create();

        $size = strlen((string) actingAs(hostOf($event))->get("/host/{$event->id}/people")->getContent());
        expect($size)->toBeLessThan(700_000);
    });
});

it('marks Add names as a create button with the plus icon (U2)', function (): void {
    $event = eventWithOwner();

    expect((string) actingAs(hostOf($event))->get("/host/{$event->id}/people")->getContent())
        ->toMatch('/<svg[^>]*>[\s\S]{0,200}<\/svg>\s*Add names\s*<\/button>/');
});

describe('Names page actions (U4, U5, U21)', function (): void {
    it('keeps Copy beside the link and puts the other actions in the row menu of an Actions column (U5)', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'claimed_at' => now()]);
        Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);

        $html = (string) actingAs(hostOf($event))->get("/host/{$event->id}/people")->getContent();
        $budi = Str::match('/<tr data-row[^>]*data-name="Budi Santoso"[\s\S]*?<\/tr>/', $html);
        $rita = Str::match('/<tr data-row[^>]*data-name="Rita Wulandari"[\s\S]*?<\/tr>/', $html);

        expect($html)->toContain('>Actions</')
            ->and($html)->not->toContain('?edit=')
            ->and($html)->toContain('placeholder="Search name or link"')
            ->and($html)->toContain('data-sort-link=')
            // The personal link cell holds the link and Copy only.
            ->and($budi)->toMatch('/<code>\/j\/[^<]+<\/code><button[^>]*data-copy=/')
            ->and($budi)->not->toContain('data-ask=')
            ->and($budi)->toMatch('/data-menu[^>]*data-new-link="1"[^>]*data-release="1"[^>]*data-rename="1"[^>]*data-delete="1"/')
            // Release only for a name that joined.
            ->and($rita)->not->toContain('data-release');
    });

    it('puts Export links in the page header instead of its own section (U21)', function (): void {
        $event = eventWithOwner();
        Person::factory()->for($event)->create();

        $html = (string) actingAs(hostOf($event))->get("/host/{$event->id}/people")->getContent();
        // The content header comes before the list; the old section came after it.
        expect((int) strpos($html, 'Export links (CSV)'))->toBeLessThan((int) strpos($html, 'id="names-live"'))
            ->and($html)->not->toContain('id="links-heading"');
    });

    it('reopens the rename dialog with what was typed and the error after a refused rename', function (): void {
        $event = eventWithOwner();
        $budi = Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'name_normalized' => 'budi santoso']);
        Person::factory()->for($event)->create(['name' => 'Rita', 'name_normalized' => 'rita']);

        actingAs(hostOf($event))->from("/host/{$event->id}/people")
            ->put("/host/{$event->id}/people/{$budi->id}", ['name' => 'rita', 'form' => "rename-{$budi->id}"])
            ->assertRedirect("/host/{$event->id}/people");

        $html = (string) actingAs(hostOf($event))->get("/host/{$event->id}/people")->getContent();

        expect($html)->toContain('askRow(')->toContain('"value":"rita"')->toContain('"error":');
    });
});

describe('the empty list follows the state of the event (U25)', function (): void {
    it('offers to add or import names while the list can still change', function (): void {
        $event = eventWithOwner();
        $event->forceFill(['status' => EventStatus::Draft])->save();

        actingAs(hostOf($event))->get("/host/{$event->id}/people")
            ->assertSee('Add the people who can play with Add names')
            ->assertSee(route('host.events.people.import.create', $event), false);
    });

    it('says that players cannot join yet once an empty event is open', function (): void {
        $event = eventWithOwner();
        $event->forceFill(['status' => EventStatus::Open])->save();

        actingAs(hostOf($event))->get("/host/{$event->id}/people")
            ->assertSee('The event is open, but nobody can join until there are names.')
            ->assertSee(route('host.events.people.import.create', $event), false);
    });

    it('offers nothing to add once the list is locked', function (): void {
        $event = eventWithOwner();
        $event->forceFill(['status' => EventStatus::Open, 'names_locked_at' => now()])->save();

        actingAs(hostOf($event))->get("/host/{$event->id}/people")
            ->assertSee('The list locked when the first game started, so names can no longer be added.')
            ->assertDontSee('Add the people who can play with Add names')
            ->assertDontSee(route('host.events.people.import.create', $event), false)
            ->assertDontSee('Add name');
    });
});

describe('Import names in a modal (B-4)', function (): void {
    it('opens Import CSV as a modal over the Names page, which takes the saved list back', function (): void {
        $event = eventWithOwner();
        $html = (string) actingAs(hostOf($event))->get("/host/{$event->id}/people")->getContent();

        expect($html)->toMatch('#<a[^>]*href="'.preg_quote(route('host.events.people.import.create', $event), '#').'"[^>]*up-layer="new modal"#')
            ->and($html)->toContain('up-accept-location="'.route('host.events.people.index', $event, false).'"');
    });

    it('shows two steps, the supported format and a template to download', function (): void {
        $event = eventWithOwner();

        actingAs(hostOf($event))->get("/host/{$event->id}/people/import")
            ->assertSee('up-main="modal"', false)
            ->assertSeeInOrder(['Upload', 'Review'])
            ->assertDontSee('Saves all names at once')
            ->assertSee('Only the first column is read.')
            ->assertSee(route('host.events.people.import.template', $event), false);
    });

    it('gives a template every importer reads: a header and one name per row', function (): void {
        $event = eventWithOwner();

        $response = actingAs(hostOf($event))->get("/host/{$event->id}/people/import/template.csv")->assertOk();

        expect((string) $response->headers->get('content-disposition'))->toContain('pentahoot-names-template.csv')
            ->and(NameCsv::parse((string) $response->streamedContent())->names)->toBe(['Rita Wulandari', 'Budi Santoso', 'Ana Putri']);
    });

    it('goes back from Review to Upload, naming the last file', function (): void {
        $event = eventWithOwner();

        actingAs(hostOf($event))->post("/host/{$event->id}/people/import", ['file' => realUpload("name\nRita\n", 'staff.csv')])
            ->assertSee(route('host.events.people.import.create', ['event' => $event, 'last' => 'staff.csv']), false);

        actingAs(hostOf($event))->get("/host/{$event->id}/people/import?last=staff.csv")
            ->assertSee('Last file: staff.csv');
    });

    it('says so when a file has no names, instead of an empty table', function (): void {
        $event = eventWithOwner();

        actingAs(hostOf($event))->post("/host/{$event->id}/people/import", ['file' => realUpload("name\n", 'empty.csv')])
            ->assertSee('No names found in this file.')
            ->assertDontSee('<table', false);
    });
});

describe('a refused upload stays in the Import modal (B-4)', function (): void {
    it('returns to the Upload step with the error under the file field, wherever the host came from', function (): void {
        $event = eventWithOwner();
        $upload = route('host.events.people.import.create', $event);
        $png = realUpload((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg==', true), 'photo.csv');

        // The Names page is where the modal closes, so a refusal must never go back there.
        actingAs(hostOf($event))->from(route('host.events.people.index', $event))
            ->post("/host/{$event->id}/people/import", ['file' => $png])
            ->assertRedirect($upload)
            ->assertSessionHasErrors('file');

        actingAs(hostOf($event))->from(route('host.events.people.index', $event))
            ->post("/host/{$event->id}/people/import", [])
            ->assertRedirect($upload)
            ->assertSessionHasErrors('file');
    });
});

it('explains a refused file in plain words (B-4)', function (): void {
    $event = eventWithOwner();
    $png = realUpload((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg==', true), 'photo.csv');

    actingAs(hostOf($event))->post("/host/{$event->id}/people/import", [])
        ->assertSessionHasErrors(['file' => 'Pick a CSV file first.']);
    actingAs(hostOf($event))->post("/host/{$event->id}/people/import", ['file' => $png])
        ->assertSessionHasErrors(['file' => 'This is not a CSV file. Save the list from Excel as CSV, or use the template.']);
    actingAs(hostOf($event))->post("/host/{$event->id}/people/import", ['file' => UploadedFile::fake()->create('big.csv', 1025, 'text/csv')])
        ->assertSessionHasErrors(['file' => 'The file is larger than 1 MB. Split the list into smaller files.']);
});
