<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMonsterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'attack' => ['required', 'integer', 'min:1'],
            'defense' => ['required', 'integer', 'min:0'],
            'health' => ['required', 'integer', 'min:1'],
        ];
    }
}
