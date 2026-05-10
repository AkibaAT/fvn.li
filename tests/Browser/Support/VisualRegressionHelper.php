<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use Exception;

class VisualRegressionHelper
{
    private string $baselineDir;

    private string $currentDir;

    private string $diffDir;

    public function __construct()
    {
        $this->baselineDir = storage_path('app/screenshots/baseline');
        $this->currentDir = storage_path('app/screenshots/current');
        $this->diffDir = storage_path('app/screenshots/diff');

        // Ensure directories exist
        $this->ensureDirectoryExists($this->baselineDir);
        $this->ensureDirectoryExists($this->currentDir);
        $this->ensureDirectoryExists($this->diffDir);
    }

    /**
     * Compare current screenshot with baseline and generate diff if differences found.
     */
    public function compareScreenshots(
        string $testName,
        float $tolerance = 0.1,
        array $options = []
    ): array {
        $baselinePath = $this->getBaselinePath($testName);
        $currentPath = $this->getCurrentPath($testName);
        $diffPath = $this->getDiffPath($testName);

        if (! file_exists($baselinePath)) {
            // First run - create baseline
            if (file_exists($currentPath)) {
                copy($currentPath, $baselinePath);

                return [
                    'status' => 'baseline_created',
                    'message' => 'Baseline screenshot created',
                    'baseline_path' => $baselinePath,
                ];
            }

            throw new Exception("Neither baseline nor current screenshot exists for test: {$testName}");
        }

        if (! file_exists($currentPath)) {
            throw new Exception("Current screenshot not found for test: {$testName}");
        }

        // Compare images
        $comparison = $this->compareImages($baselinePath, $currentPath, $tolerance, $options);

        if ($comparison['different']) {
            // Generate diff image
            $this->generateDiffImage($baselinePath, $currentPath, $diffPath);

            return [
                'status' => 'failed',
                'message' => "Visual differences detected (difference: {$comparison['difference']}%)",
                'difference_percentage' => $comparison['difference'],
                'tolerance' => $tolerance,
                'baseline_path' => $baselinePath,
                'current_path' => $currentPath,
                'diff_path' => $diffPath,
            ];
        }

        return [
            'status' => 'passed',
            'message' => 'Screenshots match within tolerance',
            'difference_percentage' => $comparison['difference'],
            'tolerance' => $tolerance,
        ];
    }

    /**
     * Update baseline screenshot with current screenshot.
     */
    public function updateBaseline(string $testName): bool
    {
        $baselinePath = $this->getBaselinePath($testName);
        $currentPath = $this->getCurrentPath($testName);

        if (! file_exists($currentPath)) {
            throw new Exception("Current screenshot not found for test: {$testName}");
        }

        return copy($currentPath, $baselinePath);
    }

    /**
     * Clean up old screenshots.
     */
    public function cleanup(int $daysOld = 7): int
    {
        $deleted = 0;
        $cutoff = time() - ($daysOld * 24 * 60 * 60);

        foreach ([$this->currentDir, $this->diffDir] as $dir) {
            $files = glob($dir.'/*.png');
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Get statistics about visual regression test results.
     */
    public function getStatistics(): array
    {
        $baselineCount = count(glob($this->baselineDir.'/*.png'));
        $currentCount = count(glob($this->currentDir.'/*.png'));
        $diffCount = count(glob($this->diffDir.'/*.png'));

        return [
            'baseline_screenshots' => $baselineCount,
            'current_screenshots' => $currentCount,
            'diff_screenshots' => $diffCount,
            'baseline_dir' => $this->baselineDir,
            'current_dir' => $this->currentDir,
            'diff_dir' => $this->diffDir,
        ];
    }

    /**
     * Compare two images and return similarity information.
     */
    private function compareImages(
        string $baseline,
        string $current,
        float $tolerance,
        array $options = []
    ): array {
        // For demonstration - in production you'd use ImageMagick or similar
        // This is a simplified comparison based on file size and basic properties

        $baselineSize = filesize($baseline);
        $currentSize = filesize($current);

        if (function_exists('getimagesize')) {
            $baselineInfo = getimagesize($baseline);
            $currentInfo = getimagesize($current);

            // Check dimensions
            if ($baselineInfo[0] !== $currentInfo[0] || $baselineInfo[1] !== $currentInfo[1]) {
                return [
                    'different' => true,
                    'difference' => 100,
                    'reason' => 'Dimension mismatch',
                ];
            }
        }

        // Simple file size comparison (for demonstration)
        $sizeDifference = abs($baselineSize - $currentSize) / max($baselineSize, $currentSize) * 100;

        // In a real implementation, you would do pixel-by-pixel comparison
        // or use a library like ImageMagick's compare function

        return [
            'different' => $sizeDifference > ($tolerance * 100),
            'difference' => $sizeDifference,
            'reason' => $sizeDifference > ($tolerance * 100) ? 'Size difference exceeds tolerance' : null,
        ];
    }

    /**
     * Generate a diff image showing differences between baseline and current.
     */
    private function generateDiffImage(string $baseline, string $current, string $diffPath): void
    {
        // In a real implementation, you would use ImageMagick or similar
        // For now, just copy the current image as the diff
        copy($current, $diffPath);

        // With ImageMagick, you could do something like:
        // exec("compare '{$baseline}' '{$current}' '{$diffPath}'");
    }

    /**
     * Get path for baseline screenshot.
     */
    private function getBaselinePath(string $testName): string
    {
        return $this->baselineDir.'/'.$this->sanitizeFilename($testName).'.png';
    }

    /**
     * Get path for current screenshot.
     */
    private function getCurrentPath(string $testName): string
    {
        return $this->currentDir.'/'.$this->sanitizeFilename($testName).'.png';
    }

    /**
     * Get path for diff screenshot.
     */
    private function getDiffPath(string $testName): string
    {
        return $this->diffDir.'/'.$this->sanitizeFilename($testName).'.png';
    }

    /**
     * Sanitize filename for filesystem.
     */
    private function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '_', $filename);
    }

    /**
     * Ensure directory exists.
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
