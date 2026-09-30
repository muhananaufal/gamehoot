<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

/**
 * E18: the QR code on the Public View lobby. It holds only the general join link, never a
 * token. Level H error correction leaves room for the logo in the middle. The SVG draws its
 * modules in currentColor with no background, so the page's tokens set both colors (F21).
 * It must be tested with real iOS and Android cameras before the rehearsal.
 */
final class JoinQrCode
{
    private const int SIZE = 320;

    /** The quiet zone scanners need around the code, in modules. */
    private const int MARGIN = 4;

    public static function svg(string $link): string
    {
        if ($link === '') {
            throw new InvalidArgumentException('The join link is empty.');
        }

        $writer = new Writer(new ImageRenderer(new RendererStyle(self::SIZE, self::MARGIN), new SvgImageBackEnd));
        $svg = $writer->writeString($link, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::H());

        $svg = (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
        $svg = (string) preg_replace('/<rect [^>]*fill="#[0-9a-fA-F]{6}"\/>/', '', $svg, 1);
        $svg = (string) preg_replace('/fill="#[0-9a-fA-F]{6}"/', 'fill="currentColor"', $svg);

        return (string) preg_replace('/^<svg /', '<svg fill="currentColor" aria-hidden="true" ', $svg, 1);
    }
}
