<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Livewire\Component;

class SocialLoginButtons extends Component
{
    public function render()
    {
        return view('users.components.social-login-buttons');
    }
}
