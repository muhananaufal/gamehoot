<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use App\Results\EventResults;
use App\Support\CsvCell;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * D-3, G10: the results as CSV, one row per ranked name.
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

            fputcsv($out, ['Game', 'Question', 'Prompt', 'Rank', 'Name', 'Votes'], ',', '"', '');

            foreach ($results->games as $game) {
                foreach ($game['questions'] as $question) {
                    foreach ($question['rows'] as $row) {
                        fputcsv($out, [
                            CsvCell::safe($game['title']),
                            $question['number'],
                            CsvCell::safe($question['prompt']),
                            $row['rank'],
                            CsvCell::safe($row['name']),
                            $row['votes'],
                        ], ',', '"', '');
                    }
                }
            }

            fclose($out);
        }, "{$event->slug}-results.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
