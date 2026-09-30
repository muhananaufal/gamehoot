<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use App\Support\CsvCell;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T5: every personal link as CSV. The page warns that the file lets anyone join as someone else.
 */
final class PersonLinksController
{
    public function __invoke(Event $event): StreamedResponse
    {
        $people = $event->people()->orderBy('name')->get(['name', 'join_token']);

        return response()->streamDownload(function () use ($event, $people): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Name', 'Link'], ',', '"', '');

            foreach ($people as $person) {
                fputcsv($out, [CsvCell::safe($person->name), url("/{$event->slug}/j/{$person->join_token}")], ',', '"', '');
            }

            fclose($out);
        }, "{$event->slug}-personal-links.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
