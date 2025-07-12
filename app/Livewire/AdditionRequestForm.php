<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Services\AdditionRequestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AdditionRequestForm extends Component
{
    public string $urls = '';
    public bool $showSuccessMessage = false;
    public array $submissionResults = [];

    protected $rules = [
        'urls' => 'required|string|max:10000',
    ];

    protected $messages = [
        'urls.required' => 'Please enter at least one itch.io URL.',
        'urls.max' => 'The input is too long. Please submit fewer URLs at once.',
    ];

    public function mount(): void
    {
        // Reset form state
        $this->reset(['urls', 'showSuccessMessage', 'submissionResults']);
    }

    public function submitRequests(): void
    {
        $this->validate();

        $user = Auth::user();
        if (! $user || ! ($user instanceof User)) {
            $this->addError('auth', 'You must be logged in to submit requests.');

            return;
        }

        $service = new AdditionRequestService;
        $urlList = $service->parseUrls($this->urls);

        if (empty($urlList)) {
            $this->addError('urls', 'Please enter at least one valid URL.');

            return;
        }

        if (count($urlList) > 50) {
            $this->addError('urls', 'Please submit no more than 50 URLs at once.');

            return;
        }

        $this->submissionResults = $service->submitRequests($user, $urlList);

        if ($this->submissionResults['success_count'] > 0) {
            $this->showSuccessMessage = true;
            $this->reset('urls');
        }

        // Emit event to refresh any request lists on the page
        $this->dispatch('addition-requests-updated');
    }

    public function clearForm(): void
    {
        $this->reset(['urls', 'showSuccessMessage', 'submissionResults']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.addition-request-form');
    }
}
