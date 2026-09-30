<?php

declare(strict_types=1);

/**
 * F15, F22: an action that bumps state_version must also publish the new state,
 * otherwise open screens keep showing the old one until they poll.
 */
it('publishes the state wherever the version is bumped', function (string $source): void {
    expect($source)->toContain('StatePublisher')->toContain('->publish(');
})->with(function (): array {
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Actions', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        if (str_contains($source, 'bumpStateVersion()')) {
            $files[$file->getFilename()] = [$source];
        }
    }

    return $files;
});
