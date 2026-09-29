<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\People\EditNameList;
use App\Exceptions\NameListLocked;
use App\Http\Requests\Host\ConfirmImportRequest;
use App\Http\Requests\Host\ImportPeopleRequest;
use App\Models\Event;
use App\People\DuplicateNames;
use App\People\NameCsv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/**
 * B-4: import never saves directly. The file becomes an editable preview; Save sends the
 * reviewed list, which is validated again (T4 for the file format).
 */
final class PersonImportController
{
    public function create(Request $request, Event $event): View|RedirectResponse
    {
        if ($event->names_locked_at !== null) {
            return redirect()->route('host.events.people.index', $event)->withErrors(['names' => __('people.locked')]);
        }

        // A reviewed list that failed validation on Save comes back here: show it again.
        $old = $request->old('names');

        if (is_array($old) && $old !== []) {
            return $this->review($event, array_values(array_filter($old, is_string(...))), null, null);
        }

        return view('host.people.import', ['event' => $event, 'rows' => null, 'separator' => null, 'fileName' => null]);
    }

    public function store(ImportPeopleRequest $request, Event $event): View|RedirectResponse
    {
        if ($event->names_locked_at !== null) {
            return back()->withErrors(['names' => __('people.locked')]);
        }

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $csv = NameCsv::parse((string) $file->get());

        return $this->review($event, $csv->names, $csv->separator, $file->getClientOriginalName());
    }

    public function update(ConfirmImportRequest $request, Event $event, EditNameList $names): RedirectResponse
    {
        try {
            $names->import($event, $request->names());
        } catch (NameListLocked) {
            return redirect()->route('host.events.people.index', $event)->withErrors(['names' => __('people.locked')]);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['names' => __('people.duplicate_race')]);
        }

        return redirect()->route('host.events.people.index', $event)
            ->with('status', trans_choice('people.imported', count($request->names())));
    }

    /**
     * @param  list<string>  $names
     */
    private function review(Event $event, array $names, ?string $separator, ?string $fileName): View
    {
        $problems = DuplicateNames::in($names, $event);

        return view('host.people.import', [
            'event' => $event,
            'separator' => $separator,
            'fileName' => $fileName,
            'rows' => array_map(fn (int $index, string $name): array => [
                'name' => $name,
                'problem' => $problems[$index] ?? null,
            ], array_keys($names), $names),
        ]);
    }
}
