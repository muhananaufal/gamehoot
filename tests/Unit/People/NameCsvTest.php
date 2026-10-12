<?php

declare(strict_types=1);

use App\People\NameCsv;

describe('T4 name CSV', function (): void {
    it('reads one name per row from the first column', function (): void {
        $csv = NameCsv::parse("Budi Santoso,IT\nRita Wulandari,HR\n");

        expect($csv->names)->toBe(['Budi Santoso', 'Rita Wulandari'])
            ->and($csv->separator)->toBe(',');
    });

    it('detects a semicolon separator and drops the UTF-8 BOM from Excel', function (): void {
        $csv = NameCsv::parse("\u{FEFF}Budi Santoso;IT\r\nRita Wulandari;HR\r\n");

        expect($csv->names)->toBe(['Budi Santoso', 'Rita Wulandari'])
            ->and($csv->separator)->toBe(';');
    });

    it('keeps quoted names with the separator inside', function (): void {
        expect(NameCsv::parse("\"Santoso, Budi\";IT\n")->names)->toBe(['Santoso, Budi']);
    });

    it('skips blank rows and a name header row', function (): void {
        expect(NameCsv::parse("Name\n\nBudi Santoso\n   \nRita\n")->names)->toBe(['Budi Santoso', 'Rita'])
            ->and(NameCsv::parse("nama;divisi\nBudi;IT\n")->names)->toBe(['Budi']);
    });

    it('tidies spacing inside names', function (): void {
        expect(NameCsv::parse("  Budi   Santoso  \n")->names)->toBe(['Budi Santoso']);
    });

    it('reads a single column file without any separator', function (): void {
        $csv = NameCsv::parse("Budi\nRita\n");

        expect($csv->names)->toBe(['Budi', 'Rita'])
            ->and($csv->separator)->toBe(',');
    });

    it('reads a file saved by Excel as Windows-1252 instead of dropping accented names', function (): void {
        $windows1252 = "Jos\xE9 Rizal\nRen\xE9 \xD1u\xF1ez\nBudi\n";

        expect(NameCsv::parse($windows1252)->names)->toBe(['José Rizal', 'René Ñuñez', 'Budi']);
    });
});
