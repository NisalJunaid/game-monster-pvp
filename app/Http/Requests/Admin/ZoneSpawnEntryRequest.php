<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ZoneSpawnEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'species_id' => ['required', 'integer', 'exists:monster_species,id'],
            'weight' => ['required', 'integer', 'min:1'],
            'min_level' => ['required', 'integer', 'min:1'],
            'max_level' => ['required', 'integer', 'min:1', 'gte:min_level'],
            'rarity_tier' => ['nullable', 'string', 'max:50'],
            'conditions_json' => ['nullable', 'array'],
        ];
    }
}
