<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('shape') && $this->filled('shape_json')) {
            $decoded = json_decode((string) $this->input('shape_json'), true);

            if (is_array($decoded)) {
                $this->merge(['shape' => $decoded]);
            }
        }

        $this->merge([
            'spawn_types' => array_filter((array) $this->input('spawn_types', [])),
            'generate_spawns' => $this->boolean('generate_spawns', false),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'shape_type' => ['required', 'in:polygon,circle'],
            'rules_json' => ['nullable', 'array'],
            'spawn_strategy' => ['nullable', 'in:manual,type_weighted,rarity_weighted'],
            'spawn_types' => ['array'],
            'spawn_types.*' => ['integer', 'exists:types,id'],
            'generate_spawns' => ['sometimes', 'boolean'],
            'shape.path' => ['required_if:shape_type,polygon', 'array', 'min:3'],
            'shape.path.*.lat' => ['required_with:shape.path', 'numeric'],
            'shape.path.*.lng' => ['required_with:shape.path', 'numeric'],
            'shape.center.lat' => ['required_if:shape_type,circle', 'numeric'],
            'shape.center.lng' => ['required_if:shape_type,circle', 'numeric'],
            'shape.radius_m' => ['required_if:shape_type,circle', 'numeric', 'min:1'],
        ];
    }
}
