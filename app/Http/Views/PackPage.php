<?php

declare(strict_types=1);

namespace App\Http\Views;

use App\Games\GameEngines;
use App\Models\PackQuestion;
use App\Models\QuestionPack;
use App\Models\User;

/**
 * Data for host/packs/show: the pack list on the left, the open pack and the question form.
 */
final readonly class PackPage
{
    public function __construct(private GameEngines $engines) {}

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, ?QuestionPack $pack = null, ?PackQuestion $editing = null, bool $creating = false): array
    {
        return [
            'packs' => $user->questionPacks()->withCount('questions')->orderBy('title')->get()
                ->groupBy(fn (QuestionPack $p): string => $p->game_type->value),
            'types' => $this->engines->supportedTypes(),
            'pack' => $pack,
            'questions' => $pack?->questions()->with(['pentahoot', 'kata', 'gambar.questionImage.variants'])->get(),
            'editing' => $editing?->load(['pentahoot', 'kata']),
            'creating' => $creating,
        ];
    }
}
