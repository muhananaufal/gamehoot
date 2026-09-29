<?php

declare(strict_types=1);

use App\Exceptions\UnsupportedKataAnswer;
use App\Games\TebakKata\AnswerBoxes;
use App\Games\TebakKata\AnswerCell;

/**
 * @return list<list<string>>
 */
function renderWords(AnswerBoxes $boxes): array
{
    return array_map(
        fn (array $word): array => array_map(
            fn (AnswerCell $cell): string => $cell->box === null ? "({$cell->char})" : "{$cell->char}{$cell->box}",
            $word,
        ),
        $boxes->words,
    );
}

describe('E5 answer boxes', function (): void {
    it('gives every letter one box, in capitals', function (): void {
        $boxes = AnswerBoxes::fromAnswer('paris');

        expect($boxes->count)->toBe(5)
            ->and(renderWords($boxes))->toBe([['P0', 'A1', 'R2', 'I3', 'S4']]);
    });

    it('splits words on spaces and numbers boxes across words', function (): void {
        $boxes = AnswerBoxes::fromAnswer('new  york ');

        expect($boxes->count)->toBe(7)
            ->and(renderWords($boxes))->toBe([['N0', 'E1', 'W2'], ['Y3', 'O4', 'R5', 'K6']]);
    });

    it('gives digits a box', function (): void {
        expect(AnswerBoxes::fromAnswer('3 Idiots')->count)->toBe(7);
    });

    it('shows punctuation without a box', function (): void {
        $boxes = AnswerBoxes::fromAnswer("R&B-Jl./O'k");

        expect($boxes->count)->toBe(6)
            ->and(renderWords($boxes))->toBe([['R0', '(&)', 'B1', '(-)', 'J2', 'L3', '(.)', '(/)', 'O4', "(')", 'K5']]);
    });

    it('treats a typographic apostrophe as a plain one', function (): void {
        expect(renderWords(AnswerBoxes::fromAnswer('O’k')))->toBe([['O0', "(')", 'K1']]);
    });

    it('keeps accented letters as one box, composed or decomposed', function (): void {
        $composed = AnswerBoxes::fromAnswer("caf\u{00E9}");
        $decomposed = AnswerBoxes::fromAnswer("cafe\u{0301}");

        expect($composed->count)->toBe(4)
            ->and(renderWords($decomposed))->toBe(renderWords($composed));
    });

    it('rejects characters that are neither letters, digits nor allowed punctuation', function (string $answer): void {
        expect(AnswerBoxes::supports($answer))->toBeFalse();

        AnswerBoxes::fromAnswer($answer);
    })->with(['hello!', 'a+b', 'emoji 😀', "tab\tchar"])->throws(UnsupportedKataAnswer::class);

    it('rejects an answer without any box', function (string $answer): void {
        expect(AnswerBoxes::supports($answer))->toBeFalse();

        AnswerBoxes::fromAnswer($answer);
    })->with(['', '   ', '- / .'])->throws(UnsupportedKataAnswer::class);
});
