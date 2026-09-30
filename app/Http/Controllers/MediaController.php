<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MediaVisibility;
use App\Models\MediaFile;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * F16, E9: a private image behind a temporary signed URL (the signed middleware checks it).
 * Public images are static files and are never served here. Files are never overwritten, so
 * phones may keep them for as long as the URL lives.
 */
final class MediaController
{
    public function __invoke(MediaFile $mediaFile, Repository $config): Response
    {
        abort_unless($mediaFile->visibility === MediaVisibility::Private, 404);

        $minutes = $config->get('media.signed_minutes');
        $headers = [
            'Content-Type' => $mediaFile->mime,
            'Cache-Control' => 'private, max-age='.(is_int($minutes) ? $minutes * 60 : 0),
        ];

        if ($config->get('media.accel') === true) {
            $location = $config->get('media.accel_location');

            return response('', 200, $headers + ['X-Accel-Redirect' => (is_string($location) ? $location : '/_media/').$mediaFile->path]);
        }

        return Storage::disk($mediaFile->disk)->response($mediaFile->path, null, $headers);
    }
}
