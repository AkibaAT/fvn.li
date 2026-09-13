<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller against the entry's list
        return true;
    }

    public function rules(): array
    {
        $entry = $this->route('entry');

        return [
            'game_version_id' => ['nullable', Rule::exists('game_versions', 'id')->where('game_id', $entry?->game_id)],
            'personal_notes' => ['nullable', 'string'],
            'private_notes' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
