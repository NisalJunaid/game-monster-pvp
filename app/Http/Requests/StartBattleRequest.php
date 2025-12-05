<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartBattleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attacker_id' => ['required', 'integer', 'different:defender_id', 'exists:monsters,id'],
            'defender_id' => ['required', 'integer', 'different:attacker_id', 'exists:monsters,id'],
        ];
    }
}
