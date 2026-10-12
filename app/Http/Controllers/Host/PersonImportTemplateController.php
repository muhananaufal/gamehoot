<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * B-4: a file every importer reads: a header and one name per row, UTF-8 with a BOM so Excel
 * keeps the accents. The event is bound so the policy of the route group can check it.
 */
final class PersonImportTemplateController
{
    /** The example names of the template. */
    private const array NAMES = ['Rita Wulandari', 'Budi Santoso', 'Ana Putri'];

    public function __invoke(Event $event): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "\u{FEFF}", implode("\r\n", ['name', ...self::NAMES]), "\r\n";
        }, 'pentahoot-names-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
