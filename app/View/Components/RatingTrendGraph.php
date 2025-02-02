<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class RatingTrendGraph extends Component
{
    public function __construct(
        public Collection $data,
        public string $lineColor = '#EAB308', // yellow-500
        public string $textColor = '#6B7280', // gray-500
        public string $gridColor = '#E5E7EB'  // gray-200
    ) {}

    public function render(): View
    {
        // Use view-box instead of fixed dimensions for responsiveness
        $width = 1000;
        $height = 300;
        $padding = [
            'top' => 20,
            'right' => 10,
            'bottom' => 60,
            'left' => 45,
        ];

        // Calculate actual graph dimensions
        $graphWidth = $width - ($padding['left'] + $padding['right']);
        $graphHeight = $height - ($padding['top'] + $padding['bottom']);

        // Get min/max values
        $maxCount = $this->data->max('count');
        $minCount = 0;

        // Calculate Y-axis scale (round up to nearest 100)
        $yMax = ceil($maxCount / 100) * 100;
        $yScale = $graphHeight / ($yMax - $minCount);

        // Calculate X-axis scale
        $totalMonths = $this->data->count();
        $xScale = $graphWidth / ($totalMonths - 1);

        // Generate points for the line
        $points = $this->data->map(function ($point, $index) use ($xScale, $yScale, $padding, $height) {
            $x = ($index * $xScale) + $padding['left'];
            $y = $height - $padding['bottom'] - ($point->count * $yScale);

            return "{$x},{$y}";
        })->join(' ');

        // Generate Y-axis labels (5 evenly spaced points)
        $yLabels = collect(range(0, 4))->map(function ($i) use ($yMax, $graphHeight, $height, $padding) {
            $value = round($yMax * ($i / 4));
            $y = $height - $padding['bottom'] - ($value * ($graphHeight / $yMax));

            return [
                'value' => $value,
                'y' => $y,
            ];
        });

        // Generate X-axis labels (show every 3rd month)
        $xLabels = $this->data->map(function ($point, $index) use ($xScale, $padding) {
            return [
                'label' => Carbon::parse($point->month)->format('M Y'),
                'x' => ($index * $xScale) + $padding['left'],
                'show' => $index % 3 === 0,
            ];
        });

        return view('components.rating-trend-graph', [
            'width' => $width,
            'height' => $height,
            'padding' => $padding,
            'points' => $points,
            'yLabels' => $yLabels,
            'xLabels' => $xLabels,
            'viewBox' => "0 0 {$width} {$height}",
        ]);
    }
}
