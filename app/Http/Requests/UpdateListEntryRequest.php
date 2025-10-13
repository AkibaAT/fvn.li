<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\VnListEntry;
use Illuminate\Foundation\Http\FormRequest;

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
        $completedAtRule = [];
        if ($entry instanceof VnListEntry && in_array($entry->list->type, ['custom', 'completed'])) {
            $completedAtRule = ['nullable', 'date'];
        }

        return [
            'game_version_id' => ['nullable', 'exists:game_versions,id'],
            'personal_notes' => ['nullable', 'string'],
            'private_notes' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => $completedAtRule ?: ['nullable'],
        ];
    }
}
