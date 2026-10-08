<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Actions\Games\ReorderGames;
use App\Http\Concerns\LiveValidation;
use App\Models\Event;
use App\Models\Game;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D-9: the new order names every game still to play, and nothing else.
 */
final class ReorderGamesRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                $event = $this->route('event');
                $current = $event instanceof Event
                    ? $event->games()->get()->filter(fn (Game $game): bool => ReorderGames::stillToPlay($event, $game))->pluck('id')->sort()->values()->all()
                    : [];
                $given = is_array($value) ? collect($value)->sort()->values()->all() : null;

                if ($given !== $current) {
                    $fail('games.order_mismatch')->translate();
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
