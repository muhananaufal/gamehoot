<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\GameType;
use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function newEventForm(array $extra = []): array
{
    return [
        'form' => 'create-event',
        'name' => 'Year-End Party',
        'slug' => 'year-end-party',
        'screen_theme' => 'dark',
        'show_on_devices' => '0',
        ...$extra,
    ];
}

describe('New event in four steps, saved at the last one (U26)', function (): void {
    it('creates the event, its names and its games in one go, then goes to Live control', function (): void {
        $host = User::factory()->create();
        $trivia = Pentahoot::pack($host, ['Q1?'], 'Team Trivia');
        $awards = Pentahoot::pack($host, ['Q1?', 'Q2?'], 'Office Awards');

        $response = actingAs($host)->post('/host/events', newEventForm([
            'names' => implode(PHP_EOL, ['Rita Wulandari', '', 'Budi Santoso']),
            'pack_ids' => [$awards->id, $trivia->id],
        ]));

        $event = Event::query()->where('slug', 'year-end-party')->sole();
        $response->assertRedirect(route('host.events.live', $event));
        expect($event->status)->toBe(EventStatus::Draft)
            ->and($event->people()->orderBy('name')->pluck('name')->all())->toBe(['Budi Santoso', 'Rita Wulandari'])
            ->and($event->games()->orderBy('position')->pluck('title')->all())->toBe(['Office Awards', 'Team Trivia'])
            ->and(ActionLog::query()->where('action', LoggedAction::NamesAdded)->value('payload'))->toBe(['count' => 2]);
    });

    it('still creates an event with names and games skipped', function (): void {
        $host = User::factory()->create();

        actingAs($host)->post('/host/events', newEventForm(['names' => '', 'pack_ids' => []]))->assertRedirect();

        $event = Event::query()->sole();
        expect($event->people()->count())->toBe(0)
            ->and($event->games()->count())->toBe(0)
            ->and(ActionLog::query()->where('action', LoggedAction::NamesAdded)->exists())->toBeFalse();
    });

    it('saves nothing when the names have a problem, and says which line', function (): void {
        $host = User::factory()->create();

        actingAs($host)->post('/host/events', newEventForm(['names' => implode(PHP_EOL, ['Ana Putri', 'ana  PUTRI'])]))
            ->assertSessionHasErrors(['names' => 'Line 2: Same as line 1. Add something to tell them apart, e.g. “ana PUTRI (IT)”.']);

        expect(Event::query()->count())->toBe(0);
    });

    it('refuses packs of another host or of a type that cannot be played, and saves nothing', function (): void {
        $host = User::factory()->create();
        $foreign = Pentahoot::pack(User::factory()->create());
        $notPlayable = new QuestionPack(['title' => 'Old', 'game_type' => GameType::Pentahoot]);
        $notPlayable->owner()->associate($host)->save();
        $notPlayable->delete();

        actingAs($host)->post('/host/events', newEventForm(['pack_ids' => [$foreign->id]]))->assertSessionHasErrors('pack_ids.0');
        actingAs($host)->post('/host/events', newEventForm(['pack_ids' => [$notPlayable->id]]))->assertSessionHasErrors('pack_ids.0');

        expect(Event::query()->count())->toBe(0);
    });

    it('keeps the rule of 500 names per event', function (): void {
        $host = User::factory()->create();
        $names = implode(PHP_EOL, array_map(fn (int $i): string => "Guest {$i}", range(1, 501)));

        actingAs($host)->post('/host/events', newEventForm(['names' => $names]))
            ->assertSessionHasErrors(['names' => 'An event can have at most 500 names.']);
    });
});

describe('reading a name file for the new event, without saving it (U26, T4)', function (): void {
    it('returns the names of the file the importer reads', function (): void {
        actingAs(User::factory()->create())
            ->postJson('/host/events/names/parse', ['file' => UploadedFile::fake()->createWithContent('staff.csv', "nama;divisi\r\nRita Wulandari;HR\r\nBudi Santoso;IT\r\n")])
            ->assertOk()
            ->assertExactJson(['names' => ['Rita Wulandari', 'Budi Santoso'], 'file' => 'staff.csv']);

        expect(Event::query()->count())->toBe(0);
    });

    it('refuses a file that is not a CSV, in plain words', function (): void {
        // A real file, so its type is read from its bytes as in production (fake files use the name).
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg==', true));
        $png = new UploadedFile($path, 'photo.csv', null, null, true);

        actingAs(User::factory()->create())->postJson('/host/events/names/parse', ['file' => $png])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'This is not a CSV file. Save the list from Excel as CSV, or use the template.');
    });
});

it('slides the steps of New event the way the host moves (U32)', function (): void {
    actingAs(User::factory()->create())->get('/host')
        ->assertOk()
        ->assertSee('--step-dir', false)
        ->assertSee('x-transition:enter-start="step-from"', false);
});
