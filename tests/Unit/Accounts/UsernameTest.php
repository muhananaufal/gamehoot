<?php

declare(strict_types=1);

use App\Accounts\Username;

describe('C-6 usernames', function (): void {
    it('makes a username from a name: lower case, without spaces or other signs', function (string $name, string $username): void {
        expect(Username::fromName($name, fn (string $candidate): bool => false))->toBe($username);
    })->with([
        ['muhananaufal', 'muhananaufal'],
        ['Arif Rahman', 'arifrahman'],
        ['Dina (HR)', 'dinahr'],
        ['José Ömer', 'joseomer'],
        ['  Tomi   Pratama ', 'tomipratama'],
    ]);

    it('adds a number when the username is taken', function (): void {
        $taken = ['budi', 'budi2'];

        expect(Username::fromName('Budi', fn (string $candidate): bool => in_array($candidate, $taken, true)))->toBe('budi3');
    });

    it('falls back to user and a number when too little of the name is left', function (): void {
        expect(Username::fromName('Al', fn (string $candidate): bool => false))->toBe('user1')
            ->and(Username::fromName('!!!', fn (string $candidate): bool => $candidate === 'user1'))->toBe('user2');
    });

    it('keeps every made username within the rules', function (): void {
        $long = Username::fromName(str_repeat('Panjang ', 10), fn (string $candidate): bool => false);

        expect($long)->toMatch(Username::PATTERN)->and(strlen($long))->toBeLessThanOrEqual(30);
    });

    it('reads typed usernames in lower case', function (): void {
        expect(Username::normalize('  Muha.Naufal '))->toBe('muha.naufal');
    });
});
