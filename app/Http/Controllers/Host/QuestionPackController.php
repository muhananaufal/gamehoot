<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Http\Requests\Host\StorePackRequest;
use App\Http\Requests\Host\UpdatePackRequest;
use App\Http\Views\PackPage;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * D-9: question packs owned by the host.
 */
final class QuestionPackController
{
    public function __construct(private readonly PackPage $page) {}

    public function index(#[CurrentUser] User $user): View
    {
        return view('host.packs.show', $this->page->data($user));
    }

    public function store(StorePackRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $pack = new QuestionPack([
            'title' => $request->string('title')->trim()->toString(),
            'game_type' => $request->gameType(),
        ]);
        $pack->owner()->associate($user)->save();

        return redirect()->route('host.packs.show', $pack)->with('status', __('packs.created'));
    }

    public function show(QuestionPack $pack, #[CurrentUser] User $user): View
    {
        return view('host.packs.show', $this->page->data($user, $pack));
    }

    public function update(UpdatePackRequest $request, QuestionPack $pack): RedirectResponse
    {
        $pack->update(['title' => $request->string('title')->trim()->toString()]);

        return redirect()->route('host.packs.show', $pack)->with('status', __('packs.saved'));
    }

    public function destroy(QuestionPack $pack): RedirectResponse
    {
        $pack->delete();

        return redirect()->route('host.packs.index')->with('status', __('packs.deleted', ['title' => $pack->title]));
    }
}
