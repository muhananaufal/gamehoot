<?php

declare(strict_types=1);

use App\Games\Pentahoot\VoteWindow;
use Carbon\CarbonImmutable;

describe('E3 vote window', function (): void {
    $endsAt = CarbonImmutable::parse('2026-10-15 10:00:00.000');

    it('accepts votes until one second after the countdown ends', function () use ($endsAt): void {
        expect(VoteWindow::accepts($endsAt, $endsAt->subSecond()))->toBeTrue()
            ->and(VoteWindow::accepts($endsAt, $endsAt))->toBeTrue()
            ->and(VoteWindow::accepts($endsAt, $endsAt->addMilliseconds(1_000)))->toBeTrue()
            ->and(VoteWindow::accepts($endsAt, $endsAt->addMilliseconds(1_001)))->toBeFalse();
    });

    it('counts the question as closed only after the tolerance, so reveal waits for late votes', function () use ($endsAt): void {
        expect(VoteWindow::isClosed($endsAt, $endsAt->addMilliseconds(1_000)))->toBeFalse()
            ->and(VoteWindow::isClosed($endsAt, $endsAt->addMilliseconds(1_001)))->toBeTrue()
            ->and(VoteWindow::isClosed(null, $endsAt))->toBeFalse();
    });
});
