<?php

declare(strict_types=1);

use App\Support\JoinQrCode;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

describe('E18 join QR code', function (): void {
    it('draws an SVG whose dark modules follow the text color, so the tokens decide the colors (F21)', function (): void {
        $svg = JoinQrCode::svg('https://play.example.test/year-end-party');

        expect($svg)->toStartWith('<svg')->toContain('currentColor');
        expect($svg)->not->toMatch('/#[0-9a-fA-F]{6}/');
    });

    it('uses the highest error correction, so the logo in the middle does not break it', function (): void {
        $link = 'https://play.example.test/year-end-party';
        $moduleCount = fn (string $svg): int => substr_count($svg, 'M');
        $withLevel = fn (ErrorCorrectionLevel $level): string => (new Writer(new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd)))
            ->writeString($link, Encoder::DEFAULT_BYTE_MODE_ENCODING, $level);

        expect($moduleCount(JoinQrCode::svg($link)))->toBe($moduleCount($withLevel(ErrorCorrectionLevel::H())));
        expect($moduleCount($withLevel(ErrorCorrectionLevel::H())))->not->toBe($moduleCount($withLevel(ErrorCorrectionLevel::L())));
    });

    it('refuses an empty link', function (): void {
        JoinQrCode::svg('');
    })->throws(InvalidArgumentException::class);
});
