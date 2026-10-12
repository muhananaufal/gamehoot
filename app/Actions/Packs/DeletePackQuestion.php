<?php

declare(strict_types=1);

namespace App\Actions\Packs;

use App\Models\PackQuestion;
use App\Models\QuestionPack;
use Illuminate\Support\Facades\DB;

/**
 * D-9: copies already made for games keep their content; only their source link is cleared.
 * The pack version moves, so games copied before show the pack changed.
 */
final readonly class DeletePackQuestion
{
    public function handle(QuestionPack $pack, PackQuestion $question): void
    {
        DB::transaction(function () use ($pack, $question): void {
            $pack->contentChanged();
            $question->delete();
        });
    }
}
