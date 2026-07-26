<?php

declare(strict_types=1);

namespace App\Support\Routes;

/**
 * Recognises labels that exist for authoring rather than for play.
 *
 * Ren'Py projects ship with the developer's test harnesses and scratch files
 * still in the build. Those labels are unreachable by design, so separating
 * them from the rest keeps a coverage gap in the route map meaningful: what
 * remains disconnected is content the extractor failed to connect.
 */
final class AuthoringScaffoldingClassifier
{
    /**
     * Name fragments that mark a label as a harness rather than story.
     *
     * @var list<string>
     */
    private const NAME_PREFIXES = ['test_', 'testing_', 'debug_', 'tmp_', 'scratch_'];

    /**
     * @var list<string>
     */
    private const NAME_SUFFIXES = [
        '_test', '_tests', '_testing', '_workspace', '_wkspace', '_workspace_label', '_debug', '_sandbox',
    ];

    /**
     * @var list<string>
     */
    private const NAME_FRAGMENTS = ['workspace_label', '_test_', '_testing_'];

    /**
     * Directory segments whose contents are tooling rather than story.
     *
     * @var list<string>
     */
    private const DIRECTORY_SEGMENTS = ['/tests/', '/test/', '/debug/', '/sandbox/'];

    /**
     * File basenames that are scratch pads rather than shipped script.
     *
     * @var list<string>
     */
    private const FILE_STEMS = ['workspace', 'wkspace', 'dump', 'scratch', 'sandbox', 'playground'];

    public function isScaffolding(string $labelName, string $filePath): bool
    {
        return $this->nameLooksLikeScaffolding($labelName)
            || $this->pathLooksLikeScaffolding($filePath);
    }

    private function nameLooksLikeScaffolding(string $labelName): bool
    {
        // A label may carry a synthetic ":ending" suffix, which is not part of
        // the authored name.
        $name = mb_strtolower(explode(':', $labelName)[0]);
        if ($name === '') {
            return false;
        }

        foreach (self::NAME_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        foreach (self::NAME_SUFFIXES as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        foreach (self::NAME_FRAGMENTS as $fragment) {
            if (str_contains($name, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function pathLooksLikeScaffolding(string $filePath): bool
    {
        if ($filePath === '') {
            return false;
        }

        $normalized = mb_strtolower(str_replace('\\', '/', $filePath));

        foreach (self::DIRECTORY_SEGMENTS as $segment) {
            if (str_contains($normalized, $segment)) {
                return true;
            }
        }

        // Compiled scripts carry a .rpyc extension, so the stem is compared
        // rather than the full basename.
        $stem = mb_strtolower(pathinfo($normalized, PATHINFO_FILENAME));
        if ($stem === '') {
            return false;
        }

        foreach (self::FILE_STEMS as $fileStem) {
            if ($stem === $fileStem || str_ends_with($stem, '_' . $fileStem)) {
                return true;
            }
        }

        return false;
    }
}
