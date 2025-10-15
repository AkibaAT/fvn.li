<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVnListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles authorization where needed
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:vn_lists,name,NULL,id,user_id,' . ($this->user()->id ?? 'NULL'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
            'game_id' => ['nullable', 'exists:games,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a list with this name.',
        ];
    }
}
