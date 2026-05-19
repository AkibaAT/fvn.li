<?php

declare(strict_types=1);

namespace App\Services;

class RouteGraphFunctionMenuDetector
{
    public function isFunctionMenu(string $labelName, $choices, ?string $prompt): bool
    {
        $labelPatterns = [
            '/chapter[_\s]?select/i',
            '/chapter[_\s]?menu/i',
            '/chapitre[_\s]?(?:select|selec|menu)/i',
            '/(?:selection|sélection)[_\s]?chapitre/i',
            '/gallery|galerie/i',
            '/select[_\s]?screen/i',
            '/main[_\s]?menu/i',
            '/extras/i',
            '/bonus/i',
        ];

        foreach ($labelPatterns as $pattern) {
            if (preg_match($pattern, $labelName)) {
                return true;
            }
        }

        if ($prompt) {
            $promptPatterns = [
                '/^(select|choose)\s+(a\s+)?chapter/i',
                '/^(select|choose)\s+(a\s+)?scene/i',
                '/^(choisir|sélectionner|selectionner)\s+(un\s+|une\s+)?chapitre/i',
                '/^chapter\s+select/i',
                '/^chapitre\s+(select|sélection|selection)/i',
                '/^scene\s+select/i',
                '/^jump\s+to/i',
            ];

            foreach ($promptPatterns as $pattern) {
                if (preg_match($pattern, trim($prompt))) {
                    return true;
                }
            }
        }

        if ($choices->count() > 5) {
            $chapterLikeChoices = 0;
            foreach ($choices as $choice) {
                $text = $choice->text ?? '';
                if (preg_match('/^(chapter|chapitre|scene|scène|day|jour|route|part|partie|act|acte)\s*\d+/i', $text) ||
                    preg_match('/^(prologue|epilogue|épilogue|intro|ending|fin|bonus|extra|gallery|galerie|cg)/i', $text)) {
                    $chapterLikeChoices++;
                }
            }

            if ($chapterLikeChoices / $choices->count() > 0.8) {
                return true;
            }
        }

        return false;
    }
}
