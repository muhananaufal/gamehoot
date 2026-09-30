<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use App\Results\EventResults;
use App\Support\CsvCell;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * D-3, G10: the results as CSV. Pentahoot: one row per ranked name with its votes. Tebak:
 * one row per won question with its points, then the final board ("Final").
 */
final class ResultsExportController
{
    public function __invoke(Event $event): StreamedResponse
    {
        $results = EventResults::for($event);

        return response()->streamDownload(function () use ($results): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Game', 'Question', 'Prompt', 'Rank', 'Name', 'Score'], ',', '"', '');

            foreach ($results->games as $game) {
                $title = CsvCell::safe($game['title']);

                foreach ($game['questions'] as $question) {
                    foreach ($question['rows'] as $row) {
                        fputcsv($out, [$title, $question['number'], CsvCell::safe($question['prompt']), $row['rank'], CsvCell::safe($row['name']), $row['votes']], ',', '"', '');
                    }

                    if ($question['winner'] !== null) {
                        fputcsv($out, [$title, $question['number'], CsvCell::safe($question['prompt']), '', CsvCell::safe($question['winner']), $question['points']], ',', '"', '');
                    }
                }

                foreach ($game['final'] ?? [] as $row) {
                    fputcsv($out, [$title, 'Final', '', $row['rank'], CsvCell::safe($row['name']), $row['points']], ',', '"', '');
                }
            }

            fclose($out);
        }, "{$event->slug}-results.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
