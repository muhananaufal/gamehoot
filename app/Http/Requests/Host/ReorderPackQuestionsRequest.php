<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Models\QuestionPack;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new order must list every question of the pack exactly once.
 */
final class ReorderPackQuestionsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                $pack = $this->route('pack');
                $current = $pack instanceof QuestionPack ? $pack->questions()->pluck('id')->sort()->values()->all() : [];
                $given = is_array($value) ? collect($value)->sort()->values()->all() : null;

                if ($given !== $current) {
                    $fail('packs.order_mismatch')->translate();
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
