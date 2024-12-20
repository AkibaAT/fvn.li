@props(['platforms' => []])

<div class="flex space-x-1">
    @if($platforms['windows'] ?? false)
        <span title="Windows" class="text-base">🪟</span>
    @endif
    @if($platforms['linux'] ?? false)
        <span title="Linux" class="text-base">🐧</span>
    @endif
    @if($platforms['mac'] ?? false)
        <span title="Mac" class="text-base">🍎</span>
    @endif
    @if($platforms['android'] ?? false)
        <span title="Android" class="text-base">📱</span>
    @endif
    @if($platforms['web'] ?? false)
        <span title="Web" class="text-base">🌐</span>
    @endif
</div>
