<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Packs\SavePackQuestion;
use App\Http\Requests\Host\PackQuestionRequest;
use App\Http\Views\PackPage;
use App\Models\PackQuestion;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * D-9: the question form of a pack. The same page shows the pack and the form beside it.
 */
final class PackQuestionController
{
    public function __construct(private readonly PackPage $page) {}

    public function create(QuestionPack $pack, #[CurrentUser] User $user): View
    {
        return view('host.packs.show', $this->page->data($user, $pack, creating: true));
    }

    public function store(PackQuestionRequest $request, QuestionPack $pack, SavePackQuestion $save): RedirectResponse
    {
        $save->handle($pack, null, $request->validated());

        return redirect()->route('host.packs.show', $pack)->with('status', __('packs.question_added'));
    }

    public function edit(QuestionPack $pack, PackQuestion $question, #[CurrentUser] User $user): View
    {
        return view('host.packs.show', $this->page->data($user, $pack, $question));
    }

    public function update(PackQuestionRequest $request, QuestionPack $pack, PackQuestion $question, SavePackQuestion $save): RedirectResponse
    {
        $save->handle($pack, $question, $request->validated());

        return redirect()->route('host.packs.show', $pack)->with('status', __('packs.question_saved'));
    }

    public function destroy(QuestionPack $pack, PackQuestion $question): RedirectResponse
    {
        // D-9: copies already made for games keep their content; only their source link is cleared.
        $question->delete();

        return redirect()->route('host.packs.show', $pack)->with('status', __('packs.question_deleted'));
    }
}
