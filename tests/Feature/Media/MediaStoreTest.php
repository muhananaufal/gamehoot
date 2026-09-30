<?php

declare(strict_types=1);

use App\Enums\MediaVariant;
use App\Enums\MediaVisibility;
use App\Media\MediaStore;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
    expect($parent->size_bytes)->toBe((int) $large->getSize());
    expect($parent->sha256)->toBe(hash_file('sha256', $large->getRealPath()));
    expect($parent->original_name)->toBe('Eiffel Tower.jpg');
    expect($parent->created_by)->toBe($user->id);

    $variant = $parent->variants()->sole();
    expect($variant->variant)->toBe(MediaVariant::W720);
    expect($variant->visibility)->toBe(MediaVisibility::Private);
    expect([$variant->width, $variant->height])->toBe([720, 405]);
    expect($variant->sha256)->toBe(hash_file('sha256', $small->getRealPath()));

    Storage::disk('media')->assertExists([$parent->path, $variant->path]);
    expect(Storage::disk('media')->get($parent->path))->toBe(file_get_contents($large->getRealPath()));
});

it('names answer images after the original and question images without it (E9)', function (): void {
    $user = User::factory()->create();
    $store = app(MediaStore::class);

    $answer = $store->store(
        UploadedFile::fake()->image('Menara Eiffel (2).PNG', 800, 600),
        UploadedFile::fake()->image('Menara Eiffel (2).PNG', 720, 540),
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
