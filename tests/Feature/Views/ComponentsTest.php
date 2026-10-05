<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\Finder\SplFileInfo;

it('makes the whole switch of a toggle clickable, not only its text (E14)', function (): void {
    $html = Blade::render('<x-toggle name="show_on_devices" label="Show questions" />');

    // The drawn switch is a label for the hidden checkbox, so a tap on it toggles.
    expect($html)->toMatch('/<label for="toggle-show_on_devices" class="[^"]*cursor-pointer[^"]*">\s*<input id="toggle-show_on_devices"/');
});

it('writes the score under each podium name with its unit (E17)', function (): void {
    $html = Blade::render('<x-podium stand="stand" :score="$score" />', ['score' => __('tebak.points')]);

    expect($html)->toContain('$store.realtime.count(')
        ->toContain('__N__ points')
        ->toContain('$store.realtime.initials(row.name)');
});

it('keeps link-style buttons 44 px tall and in the link color (O11, U11)', function (string $variant): void {
    $html = Blade::render('<x-button :variant="$variant">Copy</x-button>', ['variant' => $variant]);

    expect($html)->toContain('min-h-11')
        ->and($html)->not->toMatch('/\btext-accent\b/');
})->with(['link', 'link-destructive']);

it('hands the saved message of the last request to the toast area, read out politely (U3)', function (): void {
    session()->flash('status', 'Pack saved.');

    $html = Blade::render('<x-toasts />');

    expect($html)->toContain('aria-live="polite"')
        ->toContain('$store.toasts.push(')
        ->toContain('Pack saved.');
});

it('has no toast message when nothing was saved', function (): void {
    $html = Blade::render('<x-toasts />');

    expect($html)->toContain('aria-live="polite"')
        ->and($html)->not->toContain('$store.toasts.push(');
});

it('never lets Cancel submit a dialog form, so Enter in a field sends the form (U2, U5)', function (): void {
    // The first submit button of a form answers Enter. A Cancel with formmethod="dialog" placed
    // first closed the Add name dialog on Enter without adding the name.
    $offending = array_values(array_filter(
        File::allFiles(resource_path('views')),
        fn (SplFileInfo $file): bool => str_contains($file->getContents(), 'formmethod="dialog"'),
    ));

    expect(array_map(fn (SplFileInfo $file): string => $file->getRelativePathname(), $offending))->toBe([]);
});

it('shows where the theme switch goes: sun while dark, moon while light (U17)', function (): void {
    $html = Blade::render('<x-theme-toggle />');

    expect($html)->toContain('x-data="themeToggle"')
        ->and($html)->toMatch('/<span x-show="dark"[^>]*>\s*<svg/')
        ->and($html)->toMatch('/<span x-show="! dark"[^>]*>\s*<svg/')
        ->and($html)->toContain('aria-label="Switch theme"');
});

it('lets every password field be shown and hidden (U17)', function (): void {
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render('<x-field name="password" type="password" label="Password" />');

    expect($html)->toContain(':type="shown ? \'text\' : \'password\'"')
        ->and($html)->toContain(':aria-pressed="shown.toString()"')
        ->and($html)->toContain('aria-label="Show password"');
});

it('gives every button variant the ink colour of its busy spinner (U16)', function (string $variant, string $ink): void {
    expect(Blade::render("<x-button variant=\"{$variant}\">Save</x-button>"))->toContain('[--busy-ink:var(--color-'.$ink.')]');
})->with([
    ['primary', 'on-accent'],
    ['secondary', 'ink'],
    ['destructive', 'on-destructive'],
    ['link', 'link'],
    ['link-destructive', 'on-danger'],
]);

it('puts the plus icon on dialog triggers that make something new (U2)', function (): void {
    $html = Blade::render('<x-confirm-dialog title="New pack" trigger="New pack" trigger-icon="plus"><p>Form</p></x-confirm-dialog>');

    expect($html)->toMatch('/<button[^>]*>\s*<svg[^>]*>[\s\S]*?<\/svg>\s*New pack\s*<\/button>/');
});

describe('one table look (U4)', function (): void {
    it('makes a column header sortable only when it names a sort key', function (): void {
        $sortable = Blade::render('<x-table.th sort="created">Created</x-table.th>');
        $plain = Blade::render('<x-table.th>Actions</x-table.th>');

        expect($sortable)->toContain(":aria-sort=\"ariaSort('created')\"")->toContain("@click=\"sortBy('created')\"")
            ->and($plain)->not->toContain('sortBy');
    });

    it('puts rows per page first and the search box in the far right corner, after the date', function (): void {
        $html = Blade::render('<x-table.toolbar search="Search name or link" today="2026-10-05" date-label="Created">FILTERS</x-table.toolbar>');

        // U22: rows per page is the themed dropdown with 10, 25, 50 and 100.
        expect($html)->toContain('x-model="size"')
            ->toContain('placeholder="Search name or link"')
            ->and($html)->not->toContain('<select')
            ->and((int) strpos($html, 'x-model="size"'))->toBeLessThan((int) strpos($html, 'FILTERS'))
            ->and((int) strpos($html, 'dateRangePicker'))->toBeLessThan((int) strpos($html, 'placeholder="Search name or link"'))
            ->and(substr_count(str_replace(chr(92).'u0022', '"', $html), '"value":'))->toBe(4);
    });

    it('numbers the pages under the table, marking the current one', function (): void {
        $html = Blade::render('<x-table-pager />');

        expect($html)->toContain('x-for="(item, index) in pageNumbers"')
            ->toContain(":aria-current=\"item === page ? 'page' : null\"")
            ->and($html)->not->toContain('x-model="size"');
    });
});

it('lets the page dialog carry one field, with the error the server gave (U5)', function (): void {
    $html = Blade::render('<x-confirm-host />');

    expect($html)->toContain('x-if="$store.confirm.field"')
        ->toContain('x-model="$store.confirm.field.value"')
        ->toContain('x-text="$store.confirm.field.error"');
});

it('shows a read-only field as switched off, so the host sees it cannot change now (U20)', function (): void {
    view()->share('errors', new ViewErrorBag);

    expect(Blade::render('<x-field name="slug" label="Link" readonly />'))
        ->toContain('read-only:cursor-not-allowed')
        ->toContain('has-[input:read-only]:bg-subtle');
});

it('checks a field on the server when it changes, except passwords and when switched off (U19)', function (): void {
    view()->share('errors', new ViewErrorBag);

    expect(Blade::render('<x-field name="name" label="Name" />'))->toContain('up-form-group')->toContain('up-validate ')->toContain('X-Pentahoot-Return')
        ->and(Blade::render('<x-field name="password" type="password" label="Password" />'))->not->toContain('up-validate')
        ->and(Blade::render('<x-field name="email" label="Email" :live="false" />'))->not->toContain('up-validate');
});

describe('third manual round: table details (U4, U13)', function (): void {
    it('rounds the top corners of the header and shows which way a column sorts', function (): void {
        $html = Blade::render('<x-table.th sort="name">Name</x-table.th>');

        expect($html)->toContain('first:rounded-tl-card')->toContain('last:rounded-tr-card')
            ->toContain(":class=\"sort === 'name' && dir === 'asc' ? 'opacity-100' : 'opacity-30'\"")
            ->toContain(":class=\"sort === 'name' && dir === 'desc' ? 'opacity-100' : 'opacity-30'\"");
    });

    it('centres the action column', function (): void {
        expect(Blade::render('<x-table.th actions>Actions</x-table.th>'))->toContain('text-center');
    });

    it('keeps dialogs left aligned wherever their button sits', function (): void {
        expect(Blade::render('<x-confirm-dialog title="T" trigger="Go">Body</x-confirm-dialog>'))->toMatch('/<dialog[^>]*text-left/');
    });
});

describe('custom dropdown (U22)', function (): void {
    it('renders a themed combobox instead of a browser select, bound with x-model or sent with a form', function (): void {
        $bound = Blade::render('<x-select :options="[[\'value\' => \'all\', \'label\' => \'All\', \'count\' => 3]]" label="Status" x-model="filter" />');
        $form = Blade::render('<x-select name="game_type" :options="[[\'value\' => \'pentahoot\', \'label\' => \'Pentahoot\']]" value="pentahoot" label="Game type" />');

        expect($bound)->toContain('x-modelable="value"')->toContain('x-model="filter"')
            ->toContain('role="combobox"')->toContain('role="listbox"')->toContain('aria-label="Status"')
            ->and($bound)->not->toContain('<select')
            ->and($form)->toContain('<input type="hidden" name="game_type"');
    });

    it('leaves no browser select in the host panel views', function (): void {
        $selects = collect(File::allFiles(resource_path('views')))
            ->filter(fn (SplFileInfo $file): bool => str_contains($file->getContents(), '<select'))
            ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
            ->values()
            ->all();

        expect($selects)->toBe([]);
    });
});
