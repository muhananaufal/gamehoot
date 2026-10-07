<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Http\Concerns\LiveValidation;
use App\Models\Game;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D-9: the new order names every copied question of the game once.
 */
final class ReorderGameQuestionsRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                $game = $this->route('game');
                $current = $game instanceof Game ? $game->questions()->pluck('id')->sort()->values()->all() : [];
                $given = is_array($value) ? collect($value)->sort()->values()->all() : null;

                if ($given !== $current) {
                    $fail('games.question_order_mismatch')->translate();
                }
            }],
            'order.*' => ['string', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function order(): array
    {
        return array_values(array_filter((array) $this->input('order', []), is_string(...)));
    }
}
