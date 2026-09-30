<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\GameType;
use App\Enums\MediaVisibility;
use App\Enums\QuestionStatus;
use App\Models\PackQuestion;
use App\Models\Question;

/**
 * F13: everything that differs per game type lives behind this contract, so controllers
 * never branch on games.type. The contract grows with each stage: live actions and
 * snapshots are added with the realtime and game stages (spec section 12).
 */
interface GameEngine
{
    public function type(): GameType;

    /**
     * Validation rules for the question form, shared by pack questions and unplayed copies (D-2).
     * $creating is false when an existing question is edited, so stored images may stay as they are.
     *
     * @return array<string, mixed>
     */
    public function questionRules(bool $creating): array;

    /**
     * E8, G11: form fields that carry an image, with the visibility it is stored with (E9). Each
     * field comes with a {field}_small file (E14). The images are stored before detailAttributes()
     * runs, which then finds the id of the stored image under {field}_id.
     *
     * @return array<string, MediaVisibility>
     */
    public function imageFields(): array;

    /**
     * Maps validated form data to the columns of the type specific detail table.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function detailAttributes(array $validated): array;

    /**
     * Maps validated form data to the shared question columns (for example points, E16).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function questionAttributes(array $validated): array;

    /**
     * @param  array<string, mixed>  $attributes  output of detailAttributes()
     */
    public function savePackDetail(PackQuestion $question, array $attributes): void;

    /**
     * D-9: copies the detail row of a pack question to its copy in a game.
     */
    public function copyDetail(PackQuestion $source, Question $copy): void;

    /**
     * D-2: saves an edit of a copied question that has not been on screen. The pack is not changed.
     *
     * @param  array<string, mixed>  $attributes  output of detailAttributes()
     */
    public function saveCopyDetail(Question $question, array $attributes): void;

    /**
     * G5: status of a copied question before it is shown.
     */
    public function initialStatus(): QuestionStatus;
}
