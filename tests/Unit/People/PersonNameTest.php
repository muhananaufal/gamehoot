<?php

declare(strict_types=1);

use App\People\PersonName;

describe('B-4 person names', function (): void {
    it('tidies spacing for display', function (): void {
        expect(PersonName::display("  Budi   Santoso\t"))->toBe('Budi Santoso');
    });

    it('compares names without case or extra spaces', function (): void {
        expect(PersonName::normalize('  BUDI  santoso '))->toBe('budi santoso')
            ->and(PersonName::normalize('Ägnes Öztürk'))->toBe('ägnes öztürk');
    });

    it('treats composed and decomposed accents as the same name', function (): void {
        expect(PersonName::normalize("Rene\u{0301}"))->toBe(PersonName::normalize("Ren\u{00E9}"));
    });
});
