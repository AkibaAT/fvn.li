<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\AdditionRequest;
use App\Models\User;
use App\Services\AdditionRequestService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UserAdditionRequests extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public string $search = '';

    protected $listeners = ['addition-requests-updated' => '$refresh'];

    public function mount(): void
    {
        // Initialize component
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function getRequestsProperty(): Collection
    {
        $user = Auth::user();
        if (! $user || ! ($user instanceof User)) {
            return collect();
        }

        $service = new AdditionRequestService;
        $requests = $service->getUserRequests(
            $user,
            $this->statusFilter === 'all' ? null : $this->statusFilter
        );

        if (! empty($this->search)) {
            $search = strtolower($this->search);
            $requests = $requests->filter(function ($request) use ($search) {
                return str_contains(strtolower($request->itch_url), $search) ||
                       str_contains(strtolower($request->status_label), $search);
            });
        }

        return $requests;
    }

    public function cancelRequest(int $requestId): void
    {
        $user = Auth::user();
        if (! $user || ! ($user instanceof User)) {
            $this->addError('auth', 'You must be logged in to cancel requests.');

            return;
        }

        $request = AdditionRequest::find($requestId);
        if (! $request) {
            $this->addError('request', 'Request not found.');

            return;
        }

        $service = new AdditionRequestService;
        $result = $service->cancelUserRequest($user, $request);

        if ($result['success']) {
            session()->flash('success', $result['message']);
            $this->dispatch('addition-requests-updated');
        } else {
            $this->addError('cancel', $result['message']);
        }
    }

    public function render()
    {
        return view('livewire.user-addition-requests', [
            'requests' => $this->requests,
            'statusOptions' => [
                'all' => 'All Requests',
                AdditionRequest::STATUS_PENDING => 'Pending',
                AdditionRequest::STATUS_APPROVED => 'Approved',
                AdditionRequest::STATUS_REJECTED => 'Rejected',
            ],
        ]);
    }
}
