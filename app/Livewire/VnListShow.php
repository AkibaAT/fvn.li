<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\VnList;
use Illuminate\View\View;
use Livewire\Component;

class VnListShow extends Component
{
    public VnList $vnList;
    public bool $isOwner = false;

    public function mount(VnList $vnList, bool $isOwner): void
    {
        $this->vnList = $vnList;
        $this->isOwner = $isOwner;
    }

    public function render(): View
    {
        return view('lists.partials.show-content');
    }
}
