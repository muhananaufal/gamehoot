<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Games\GameEngines;
use App\Http\Concerns\LiveValidation;
use App\Models\QuestionPack;
use App\Models\User;
use App\Rules\HostPack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;
use RuntimeException;

/**
 * D-9: games are made from the host's own packs, of a type that can be played live. Several
 * packs can be picked at once (pack_ids, in the order picked); a single pack_id still works.
 */
final class StoreGameRequest extends FormRequest
{
    use LiveValidation;

    protected function prepareForValidation(): void
    {
        if ($this->has('pack_id') && ! $this->has('pack_ids')) {
            $this->merge(['pack_ids' => [$this->input('pack_id')]]);
        }
    }

    /**
     * @return array<string, list<string|ValidationRule|Exists>>
     */
    public function rules(GameEngines $engines): array
    {
        $user = $this->user();

        return [
            'pack_ids' => ['required', 'array', 'min:1'],
            'pack_ids.*' => [
                'distinct',
                'uuid',
                HostPack::of($user instanceof User ? $user : null, $engines),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['pack_ids' => __('games.pack'), 'pack_ids.*' => __('games.pack')];
    }

    /**
     * @return list<QuestionPack>
     */
    public function packs(): array
    {
        $ids = array_values(array_filter((array) $this->input('pack_ids', []), is_string(...)));
        $packs = QuestionPack::query()->whereKey($ids)->get()->keyBy('id');

        return array_map(fn (string $id): QuestionPack => $packs->get($id) ?? throw new RuntimeException('Pack not found.'), $ids);
    }
}
