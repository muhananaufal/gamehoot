<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Media\MediaStore;
use App\Models\MediaFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * G11: removes files on the media disk that no media_files row points to, for example after
 * a PHP worker died between writing a file and saving its row. Files younger than an hour are
 * left alone, so an upload in progress is never touched. Run by hand (F4: no scheduler).
 */
#[Signature('pentahoot:prune-media')]
#[Description('Delete media files that no database row refers to (older than one hour)')]
final class PruneMediaCommand extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk(MediaStore::DISK);
        $tracked = MediaFile::query()->pluck('path')->flip()->all();
        $cutoff = now()->subHour()->getTimestamp();
        $removed = 0;

        foreach (['public', 'private'] as $folder) {
            foreach ($disk->allFiles($folder) as $path) {
                if (isset($tracked[$path]) || $disk->lastModified($path) > $cutoff) {
                    continue;
                }

                $disk->delete($path);
                $removed++;
            }
        }

        $this->info(trans_choice('{1} Removed :count stray file.|[0,*] Removed :count stray files.', $removed));

        return self::SUCCESS;
    }
}
