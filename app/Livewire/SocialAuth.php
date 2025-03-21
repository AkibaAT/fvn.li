<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;

class SocialAuth extends Component
{
    public bool $showLoginDialog = false;

    public function toggleLoginDialog(): void
    {
        $this->showLoginDialog = !$this->showLoginDialog;
    }

    public function logout(): void
    {
        $redirectTo = url()->previous();

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect($redirectTo);
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return view('livewire.social-auth');
    }
}
