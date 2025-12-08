<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ZoneSpawnBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.species_id' => ['required', 'integer', 'exists:monster_species,id'],
            'entries.*.weight' => ['required', 'integer', 'min:1'],
            'entries.*.min_level' => ['required', 'integer', 'min:1'],
            'entries.*.max_level' => ['required', 'integer', 'min:1', 'gte:entries.*.min_level'],
            'entries.*.rarity_tier' => ['nullable', 'string', 'max:50'],
            'entries.*.conditions_json' => ['nullable', 'array'],
        ];
    }
}
