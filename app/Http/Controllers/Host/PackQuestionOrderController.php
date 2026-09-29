<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Packs\ReorderPackQuestions;
use App\Http\Requests\Host\ReorderPackQuestionsRequest;
use App\Models\QuestionPack;
use Illuminate\Http\RedirectResponse;

final class PackQuestionOrderController
{
    public function store(ReorderPackQuestionsRequest $request, QuestionPack $pack, ReorderPackQuestions $reorder): RedirectResponse
    {
        $reorder->handle($pack, $request->order());

        return redirect()->route('host.packs.show', $pack);
    }
}
