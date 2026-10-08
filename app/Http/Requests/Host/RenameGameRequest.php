<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Http\Concerns\LiveValidation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D-9: a game's own title, as long as a pack title may be.
 */
final class RenameGameRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:100']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['title' => __('games.game_title')];
    }
}
