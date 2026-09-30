<?php

declare(strict_types=1);

namespace App\Media;

use App\Games\GameEngine;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * E8, G11: stores the images of a question form before the engine maps the form (F13). Engines
 * never touch uploads (they stay free of HTTP classes, K1); they name their image fields and
 * receive the id of each stored image under {field}_id. A field left empty on an edit keeps the
 * stored image, so its id is not set.
 */
final readonly class QuestionImages
{
    public function __construct(private MediaStore $store) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function store(GameEngine $engine, array $validated, User $uploader): array
    {
        foreach ($engine->imageFields() as $field => $visibility) {
            $large = $validated[$field] ?? null;
            $small = $validated["{$field}_small"] ?? null;

            if ($large instanceof UploadedFile && $small instanceof UploadedFile) {
                $validated["{$field}_id"] = $this->store->store($large, $small, $visibility, $uploader)->id;
            }

            unset($validated[$field], $validated["{$field}_small"]);
        }

        return $validated;
    }
}
