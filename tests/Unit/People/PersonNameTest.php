<?php

declare(strict_types=1);

use App\People\PersonName;

describe('B-4 person names', function (): void {
    it('tidies spacing for display', function (): void {
        expect(PersonName::display("  Budi   Santoso\t"))->toBe('Budi Santoso');
    });

    it('keeps accents and case in the name that is shown', function (): void {
        expect(PersonName::display('José Ägnes'))->toBe('José Ägnes');
    });

    it('compares names without case or extra spaces', function (): void {
        expect(PersonName::normalize('  BUDI  santoso '))->toBe('budi santoso');
    });

    it('treats composed and decomposed accents as the same name', function (): void {
        expect(PersonName::normalize("Rene\u{0301}"))->toBe(PersonName::normalize("Ren\u{00E9}"));
    });

    // B-8 (audit DATA-1): the unique index of utf8mb4_unicode_ci treats these as one name, so the
    // comparison before saving does too; otherwise the second one failed with an error 500.
    it('treats names the database sees as equal as the same name (B-8)', function (string $a, string $b): void {
        expect(PersonName::normalize($a))->toBe(PersonName::normalize($b));
    })->with([
        'accent' => ['José', 'Jose'],
        'umlaut' => ['Ägnes Öztürk', 'agnes ozturk'],
        'full-width letters' => ['ＡＮＡ', 'ana'],
        'sharp s' => ['Strauß', 'Strauss'],
        'upper case accent' => ['ÉLODIE', 'elodie'],
    ]);

    it('still tells different names apart (B-8)', function (): void {
        expect(PersonName::normalize('Ana'))->not->toBe(PersonName::normalize('Anna'))
            ->and(PersonName::normalize('Budi (IT)'))->not->toBe(PersonName::normalize('Budi'));
    });
});
