<?php

declare(strict_types=1);

use App\Enums\MediaVariant;
use App\Enums\MediaVisibility;
use App\Media\MediaStore;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('media');
});

it('stores the two sizes made in the browser as one parent row and its w720 variant (G11, E8, E14)', function (): void {
    $user = User::factory()->create();
    $large = UploadedFile::fake()->image('Eiffel Tower.jpg', 1920, 1080);
    $small = UploadedFile::fake()->image('Eiffel Tower.jpg', 720, 405);

    $parent = app(MediaStore::class)->store($large, $small, MediaVisibility::Private, $user);

    expect($parent->parent_id)->toBeNull();
    expect($parent->variant)->toBe(MediaVariant::W1920);
    expect($parent->visibility)->toBe(MediaVisibility::Private);
    expect($parent->disk)->toBe('media');
    expect($parent->mime)->toBe('image/jpeg');
    expect([$parent->width, $parent->height])->toBe([1920, 1080]);
    // The server encodes the image again (metadata stripped), so size and checksum are of the stored file.
    $stored = Storage::disk('media')->get($parent->path);
    expect($parent->size_bytes)->toBe(strlen((string) $stored));
    expect($parent->sha256)->toBe(hash('sha256', (string) $stored));
    expect($parent->original_name)->toBe('Eiffel Tower.jpg');
    expect($parent->created_by)->toBe($user->id);

    $variant = $parent->variants()->sole();
    expect($variant->variant)->toBe(MediaVariant::W720);
    expect($variant->visibility)->toBe(MediaVisibility::Private);
    expect([$variant->width, $variant->height])->toBe([720, 405]);
    expect($variant->sha256)->toBe(hash('sha256', (string) Storage::disk('media')->get($variant->path)));

    Storage::disk('media')->assertExists([$parent->path, $variant->path]);
});

it('names answer images after the original and question images without it (E9)', function (): void {
    $user = User::factory()->create();
    $store = app(MediaStore::class);

    $answer = $store->store(
        // A fake file is only a real PNG when its extension is written in lower case.
        UploadedFile::fake()->image('Menara Eiffel (2).png', 800, 600),
        UploadedFile::fake()->image('Menara Eiffel (2).png', 720, 540),
        MediaVisibility::Private,
        $user,
    );
    $question = $store->store(
        UploadedFile::fake()->image('Menara Eiffel.jpg', 800, 600),
        UploadedFile::fake()->image('Menara Eiffel.jpg', 720, 540),
        MediaVisibility::Public,
        $user,
    );

    $date = now()->format('Y-m-d');
    expect($answer->path)->toMatch("#^private/menara-eiffel-2-{$date}-[a-z0-9]{16}\\.png$#");
    expect($answer->variants()->sole()->path)->toMatch("#^private/menara-eiffel-2-{$date}-[a-z0-9]{16}-w720\\.png$#");

    // The question image URL reaches phones before Reveal, so its name says nothing.
    expect($question->path)->toMatch("#^public/{$date}-[a-z0-9]{16}\\.jpg$#");
    expect($question->variants()->sole()->path)->toMatch("#^public/{$date}-[a-z0-9]{16}-w720\\.jpg$#");
    expect($question->original_name)->toBe('Menara Eiffel.jpg');
});

it('never overwrites a file: the same upload twice makes two files (D-9)', function (): void {
    $user = User::factory()->create();
    $large = UploadedFile::fake()->image('a.jpg', 100, 100);
    $small = UploadedFile::fake()->image('a.jpg', 100, 100);

    $first = app(MediaStore::class)->store($large, $small, MediaVisibility::Public, $user);
    $second = app(MediaStore::class)->store($large, $small, MediaVisibility::Public, $user);

    expect($second->path)->not->toBe($first->path);
    expect($second->sha256)->toBe($first->sha256);
    expect(MediaFile::query()->count())->toBe(4);
});

/**
 * A JPEG with a camera APP1 (Exif) segment right after the start marker, as phones write it.
 */
function jpegWithExif(string $marker): UploadedFile
{
    $fake = UploadedFile::fake()->image('photo.jpg', 1920, 1080);
    $plain = (string) file_get_contents((string) $fake->getRealPath());
    $payload = "Exif\0\0".$marker;
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    $path = (string) tempnam(sys_get_temp_dir(), 'exif');
    file_put_contents($path, substr($plain, 0, 2).$segment.substr($plain, 2));

    return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
}

it('encodes images again on the server, dropping camera metadata such as GPS (E8, O7)', function (): void {
    $large = jpegWithExif('GPSLatitude-secret-home-address');
    expect((string) file_get_contents($large->getRealPath()))->toContain('GPSLatitude-secret-home-address');

    $parent = app(MediaStore::class)->store($large, UploadedFile::fake()->image('photo.jpg', 720, 405), MediaVisibility::Public, User::factory()->create());

    $stored = (string) Storage::disk('media')->get($parent->path);
    expect($stored)->not->toContain('GPSLatitude')
        ->and(getimagesizefromstring($stored))->toMatchArray([0 => 1920, 1 => 1080, 2 => IMAGETYPE_JPEG]);
});

it('keeps PNG images as PNG', function (): void {
    $parent = app(MediaStore::class)->store(
        UploadedFile::fake()->image('logo.png', 800, 600),
        UploadedFile::fake()->image('logo.png', 720, 540),
        MediaVisibility::Private,
        User::factory()->create(),
    );

    expect($parent->mime)->toBe('image/png')
        ->and(getimagesizefromstring((string) Storage::disk('media')->get($parent->path)))->toMatchArray([2 => IMAGETYPE_PNG]);
});

it('removes the files it wrote when the save around it rolls back (G11)', function (): void {
    $user = User::factory()->create();

    try {
        DB::transaction(function () use ($user): void {
            app(MediaStore::class)->store(UploadedFile::fake()->image('a.jpg', 1920, 1080), UploadedFile::fake()->image('a.jpg', 720, 405), MediaVisibility::Private, $user);

            throw new RuntimeException('The question row failed to save.');
        });
    } catch (RuntimeException) {
        // The failed save is the point of the test.
    }

    expect(Storage::disk('media')->allFiles())->toBe([])
        ->and(MediaFile::query()->count())->toBe(0);
});

it('prunes stray files that no row points to, once they are an hour old (G11)', function (): void {
    $disk = Storage::disk('media');
    $tracked = app(MediaStore::class)->store(UploadedFile::fake()->image('a.jpg', 1920, 1080), UploadedFile::fake()->image('a.jpg', 720, 405), MediaVisibility::Public, User::factory()->create());
    $disk->put('private/old-orphan.jpg', 'x');
    $disk->put('private/fresh-orphan.jpg', 'x');
    touch($disk->path('private/old-orphan.jpg'), now()->subHours(2)->getTimestamp());
    touch($disk->path($tracked->path), now()->subHours(2)->getTimestamp());

    expect(Artisan::call('pentahoot:prune-media'))->toBe(0)
        ->and(Artisan::output())->toContain('Removed 1 stray file.');

    $disk->assertMissing('private/old-orphan.jpg');
    $disk->assertExists(['private/fresh-orphan.jpg', $tracked->path]);
});

it('refuses to decode an image with more pixels than the browser ever sends (K7, audit SEC-4)', function (): void {
    $user = User::factory()->create();
    $tall = UploadedFile::fake()->image('tall.png', 1000, 6000);
    $small = UploadedFile::fake()->image('tall.png', 120, 720);

    expect(fn () => app(MediaStore::class)->store($tall, $small, MediaVisibility::Public, $user))
        ->toThrow(LogicException::class, 'Refusing to decode');

    expect(Storage::disk('media')->allFiles())->toBe([])
        ->and(MediaFile::query()->count())->toBe(0);
});
