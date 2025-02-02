<div class="w-full">
    <svg
        viewBox="{{ $viewBox }}"
        class="w-full h-auto"
        preserveAspectRatio="xMidYMid meet"
    >
        {{-- Background grid lines --}}
        @foreach ($yLabels as $label)
            <line
                x1="{{ $padding['left'] }}"
                y1="{{ $label['y'] }}"
                x2="{{ $width - $padding['right'] }}"
                y2="{{ $label['y'] }}"
                stroke="{{ $gridColor }}"
                stroke-width="1"
                stroke-dasharray="4,4"
            />
            <text
                x="{{ $padding['left'] - 10 }}"
                y="{{ $label['y'] }}"
                text-anchor="end"
                alignment-baseline="middle"
                fill="{{ $textColor }}"
                class="text-xs"
            >
                {{ number_format($label['value']) }}
            </text>
        @endforeach

        {{-- Trend line --}}
        <path
            d="M {{ $points }}"
            fill="none"
            stroke="{{ $lineColor }}"
            stroke-width="2"
            class="transition-all duration-300"
        />

        {{-- Data points --}}
        @foreach (explode(' ', $points) as $point)
            @php
                [$x, $y] = explode(',', $point);
            @endphp
            <circle
                cx="{{ $x }}"
                cy="{{ $y }}"
                r="3"
                fill="{{ $lineColor }}"
                class="transition-all duration-300"
            />
        @endforeach

        {{-- X-axis labels --}}
        @foreach ($xLabels as $label)
            @if ($label['show'])
                <text
                    x="{{ $label['x'] }}"
                    y="{{ $height - $padding['bottom'] + 20 }}"
                    text-anchor="end"
                    fill="{{ $textColor }}"
                    class="text-xs"
                    transform="rotate(-45, {{ $label['x'] }}, {{ $height - $padding['bottom'] + 20 }})"
                >
                    {{ $label['label'] }}
                </text>
            @endif
        @endforeach
    </svg>
</div>
