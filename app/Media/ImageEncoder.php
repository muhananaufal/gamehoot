<?php

declare(strict_types=1);

namespace App\Media;

use GdImage;
use LogicException;

/**
 * E8, O7: every stored image is decoded and encoded again on the server. The browser already
 * made it small, but a request can skip the browser; encoding again drops camera metadata
 * (GPS position, device, original time) and anything else hidden in the file.
 */
final class ImageEncoder
{
    private const int JPEG_QUALITY = 85;

    public static function encode(string $path, string $mime): string
    {
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            default => throw new LogicException("Unexpected image type {$mime}."),
        };

        if (! $image instanceof GdImage) {
            throw new LogicException('The image could not be decoded.');
        }

        ob_start();

        if ($mime === 'image/png') {
            // Transparent PNGs stay transparent.
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $written = imagepng($image, null, 9);
        } else {
            $written = imagejpeg($image, null, self::JPEG_QUALITY);
        }

        $bytes = (string) ob_get_clean();

        if (! $written || $bytes === '') {
            throw new LogicException('The image could not be encoded.');
        }

        return $bytes;
    }
}
