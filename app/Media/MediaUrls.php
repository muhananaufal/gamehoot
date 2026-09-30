<?php

declare(strict_types=1);

namespace App\Media;

use App\Enums\MediaVariant;
use App\Enums\MediaVisibility;
use App\Models\MediaFile;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * F16, E9: URLs of stored images. Public images are static files under /media; private images
 * only get a temporary signed URL of the /media/{mediaFile} route.
 */
final readonly class MediaUrls
{
    public function __construct(private UrlGenerator $urls, private Repository $config) {}

    /**
     * E14: both sizes of an image, the 1920 px file for the projector and the 720 px one for phones.
     *
     * @return array{large: string, small: string}
     */
    public function sizes(MediaFile $image): array
    {
        $small = $image->variants->first(fn (MediaFile $variant): bool => $variant->variant === MediaVariant::W720);

        return ['large' => $this->url($image), 'small' => $this->url($small ?? $image)];
    }

    public function url(MediaFile $file): string
    {
        if ($file->visibility === MediaVisibility::Public) {
            return $this->urls->asset('media/'.substr($file->path, strlen('public/')));
        }

        $minutes = $this->config->get('media.signed_minutes');

        return $this->urls->temporarySignedRoute(
            'media.show',
            CarbonImmutable::now()->addMinutes(is_int($minutes) ? $minutes : 720),
            ['mediaFile' => $file->id],
        );
    }
}
