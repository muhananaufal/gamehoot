<?php

declare(strict_types=1);

namespace App\Actions\Packs;

use App\Models\PackQuestion;
use App\Models\QuestionPack;
use Illuminate\Support\Facades\DB;

final class ReorderPackQuestions
{
    /**
     * @param  list<string>  $order  every question id of the pack, in the new order
     */
    public function handle(QuestionPack $pack, array $order): void
    {
        DB::transaction(function () use ($pack, $order): void {
            QuestionPack::query()->lockForUpdate()->findOrFail($pack->id);

            foreach ($order as $index => $id) {
                PackQuestion::query()->where('pack_id', $pack->id)->whereKey($id)->update(['position' => $index + 1]);
            }
        });
    }
}
