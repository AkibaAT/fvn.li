<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderListEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_ids' => ['required', 'array'],
            'entry_ids.*' => ['integer', 'min:1'],
        ];
    }
}
