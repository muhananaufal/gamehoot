<?php

declare(strict_types=1);

use App\Enums\MediaVisibility;
use App\Media\MediaStore;
use App\Media\MediaUrls;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;
use function Pest\Laravel\travel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('media');
});

function storedImage(MediaVisibility $visibility): MediaFile
{
    return app(MediaStore::class)->store(
        UploadedFile::fake()->image('Answer.jpg', 1920, 1080),
        UploadedFile::fake()->image('Answer.jpg', 720, 405),
        $visibility,
        User::factory()->create(),
    );
}

it('sends a private image to a signed URL, in both sizes (E9, E14)', function (): void {
    $image = storedImage(MediaVisibility::Private);
    $sizes = app(MediaUrls::class)->sizes($image->load('variants'));

    $response = get($sizes['large'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect($response->streamedContent())->toBe(Storage::disk('media')->get($image->path));

    $small = $image->variants()->sole();
    expect(get($sizes['small'])->assertOk()->streamedContent())->toBe(Storage::disk('media')->get($small->path));
});

it('refuses a private image without a valid signature', function (): void {
    $image = storedImage(MediaVisibility::Private);
    $url = app(MediaUrls::class)->url($image);

    get("/media/{$image->id}")->assertForbidden();
    get(str_replace('signature=', 'signature=0', $url))->assertForbidden();
});

it('lets a signed URL expire after 12 hours (E9)', function (): void {
    $url = app(MediaUrls::class)->url(storedImage(MediaVisibility::Private));

    travel(719)->minutes();
    get($url)->assertOk();

    travel(2)->minutes();
    get($url)->assertForbidden();
});

it('hands the file to nginx when MEDIA_ACCEL is on (F16)', function (): void {
    config(['media.accel' => true]);
    $image = storedImage(MediaVisibility::Private);

    $response = get(app(MediaUrls::class)->url($image))
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', "/_media/{$image->path}")
        ->assertHeader('Content-Type', 'image/jpeg');

    expect($response->getContent())->toBe('');
});

it('serves public images statically, never through the signed route (F16, E9)', function (): void {
    $image = storedImage(MediaVisibility::Public);

    expect(app(MediaUrls::class)->url($image))->toBe(url('media/'.basename($image->path)));

    $signed = URL::temporarySignedRoute('media.show', now()->addMinute(), ['mediaFile' => $image->id]);
    get($signed)->assertNotFound();
});
