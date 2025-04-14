<div class="flex justify-between items-center w-full mb-4">
    <h2 class="text-xl font-semibold">Game Versions</h2>
    <a href="{{ route('filament.admin.resources.game-versions.create', ['game_id' => $gameId]) }}"
       class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-success-600 hover:bg-success-500 focus:bg-success-700 focus:ring-offset-success-700">
        <span class="flex items-center gap-1">
            <svg class="w-5 h-5 -ml-1 rtl:ml-0 rtl:-mr-1 filament-button-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
            </svg>
            <span class="font-bold">Create New Version</span>
        </span>
    </a>
</div>
