<?php

declare(strict_types=1);

namespace App\Actions\Packs;

use App\Models\QuestionPack;
use Illuminate\Support\Facades\DB;

/**
 * D-9: a game takes the pack title when it is copied, so a new title is a change a reload
 * would copy. Saving the same title again changes nothing.
 */
final readonly class RenamePack
{
    public function handle(QuestionPack $pack, string $title): void
    {
        if ($pack->title === $title) {
            return;
        }

        DB::transaction(function () use ($pack, $title): void {
            $pack->contentChanged();
            $pack->update(['title' => $title]);
        });
    }
}
