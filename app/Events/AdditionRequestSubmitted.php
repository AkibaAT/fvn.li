<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AdditionRequest;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdditionRequestSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AdditionRequest $additionRequest,
        public User $user,
        public bool $isNewRequest
    ) {
        //
    }
}
