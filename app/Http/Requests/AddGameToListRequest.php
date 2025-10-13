<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddGameToListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'list_id' => ['nullable', 'exists:vn_lists,id'],
            'list_type' => ['nullable', 'string', 'in:reading,completed,plan_to_read,on_hold,dropped'],
        ];
    }
}
