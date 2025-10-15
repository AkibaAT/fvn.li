<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVnListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vnListId = $this->route('vnList')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:vn_lists,name,' . $vnListId . ',id,user_id,' . ($this->user()->id ?? 'NULL'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'You already have another list with this name.',
        ];
    }
}
