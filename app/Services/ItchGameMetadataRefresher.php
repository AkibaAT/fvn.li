<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Log;

class ItchGameMetadataRefresher
{
    public function refresh(Game $game, string $html): void
    {
        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $extractor = app(ItchGameMetadataExtractor::class);

        $originalThumbUrl = $game->thumb_url;
        $originalScreenshots = $game->screenshots;

        $extractor->checkForDemo($game, $doc);
        $this->refreshStatus($game, $doc);

        $extractor->extractFullDescription($game, $doc, app(ItchHtmlProcessor::class));
        $extractor->extractScreenshots($game, $doc);
        $extractor->extractCustomCss($game, $html, app(ItchCssProcessor::class));
        $extractor->extractGameJamInfo($game, $doc);

        $this->syncInfoTable($game, $doc);

        $game->is_nsfw = $doc->querySelector('div.content_warning_inner') !== null;
        $game->is_delisted = $this->hasNoindexTag($doc);

        app(GameMetadataImageProcessor::class)->process($game, $originalThumbUrl, $originalScreenshots, 'Metadata');

        Log::info('Game metadata prepared for saving', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'has_custom_css' => isset($game->custom_css),
            'custom_css_length' => strlen($game->custom_css ?? ''),
            'dirty_attributes' => $game->getDirty(),
        ]);
    }

    public function hasNoindexTag(HTMLDocument $doc): bool
    {
        foreach ($doc->querySelectorAll('meta[name="robots"]') as $meta) {
            $content = strtolower($meta->getAttribute('content') ?? '');
            if (str_contains($content, 'noindex')) {
                return true;
            }
        }

        return false;
    }

    private function refreshStatus(Game $game, HTMLDocument $doc): void
    {
        if (in_array($game->status, ['Abandoned', 'Canceled'])) {
            return;
        }

        $gameInfo = $doc->querySelector('div.game_info_panel_widget');
        if (! $gameInfo) {
            return;
        }

        foreach ($gameInfo->querySelectorAll('a') as $index => $link) {
            if ($index === 0) {
                $game->status = $link->textContent;

                return;
            }
        }
    }

    private function syncInfoTable(Game $game, HTMLDocument $doc): void
    {
        $infoTable = $doc->querySelector('div.game_info_panel_widget table');
        if (! $infoTable) {
            return;
        }

        foreach ($infoTable->querySelectorAll('tr') as $row) {
            $cells = $row->querySelectorAll('td');
            if (count($cells) < 2) {
                continue;
            }

            $label = trim($cells[0]->textContent);
            $value = trim($cells[1]->textContent);

            match ($label) {
                'Tags' => $game->syncTagsFromString($value),
                'Author', 'Authors' => $this->syncAuthors($game, $cells[1]),
                default => null,
            };
        }
    }

    private function syncAuthors(Game $game, mixed $authorsCell): void
    {
        $game->authors = '';
        foreach ($authorsCell->querySelectorAll('a') as $author) {
            if ($game->authors !== '') {
                $game->authors .= ',<br>';
            }
            $game->authors .= sprintf(
                '<a href="%s" target="_blank">%s</a>',
                $author->getAttribute('href'),
                $author->textContent
            );
        }
    }
}
