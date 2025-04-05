@props(['game', 'label' => null, 'justify' => 'justify-end', 'compact' => false])

@auth
    @php
        $userProgress = App\Models\UserGameProgress::where('user_id', auth()->id())
            ->where('game_id', $game->id)
            ->first();
        $receiveNotifications = $userProgress ? $userProgress->receive_updates : false;
    @endphp

    @include('lists.partials.toggle-switch', [
        'action' => route('user-progress.toggle-updates', ['game' => $game->id]),
        'name' => 'receive_updates',
        'value' => '1',
        'checked' => $receiveNotifications,
        'srText' => $receiveNotifications ? 'Turn off notifications' : 'Turn on notifications',
        'justify' => $justify,
        'label' => $label ?? ($compact ? null : 'Receive notifications'),
        'formClass' => 'toggle-updates-form'
    ])
@endauth
