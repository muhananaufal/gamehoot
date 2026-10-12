<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Http\Requests\Host\ImportPeopleRequest;
use App\People\NameCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * U26, T4: the New event names step reads a CSV file with the importer's rules and gets its
 * names back. Nothing is saved; the names join the list the host can still edit.
 */
final class NameFileController
{
    public function __invoke(ImportPeopleRequest $request): JsonResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        return response()->json([
            'names' => NameCsv::parse((string) $file->get())->names,
            'file' => $file->getClientOriginalName(),
        ]);
    }
}
