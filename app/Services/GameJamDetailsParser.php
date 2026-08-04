<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameJam;
use DateTime;
use Dom\HTMLDocument;
use Exception;

class GameJamDetailsParser
{
    public function extractDates(GameJam $gameJam, HTMLDocument $doc): void
    {
        $divs = $doc->querySelectorAll('div');

        if ($this->extractDateRangeFromDivText($gameJam, $divs, 'This jam is now over', '/ran from (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) to (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/')) {
            return;
        }

        if ($this->extractDateSpans($gameJam, $doc)) {
            return;
        }

        if ($this->extractDateRangeFromDivText($gameJam, $divs, 'Submissions open from', '/Submissions open from (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) to (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/')) {
            return;
        }

        $this->extractStatsDates($gameJam, $doc);
        $this->extractInfoLineDates($gameJam, $doc);
    }

    public function extractSubmissionCount(GameJam $gameJam, HTMLDocument $doc): void
    {
        $entriesDisplay = $doc->querySelector('a[href$="/entries"]');
        if ($entriesDisplay) {
            $text = trim($entriesDisplay->textContent);
            if (preg_match('/^\s*([0-9,]+)\s*Entries\s*$/i', $text, $matches)) {
                $gameJam->submission_count = (int) str_replace(',', '', $matches[1]);

                return;
            }
        }

        $submissionText = $doc->querySelector('.jam_entries_header');
        if ($submissionText) {
            $text = $submissionText->textContent;
            if (preg_match('/([0-9,]+)\s+entries/i', $text, $matches)) {
                $gameJam->submission_count = (int) str_replace(',', '', $matches[1]);

                return;
            }
        }

        foreach ($doc->querySelectorAll('h2') as $h2) {
            if (str_contains($h2->textContent, 'Submitted so far')) {
                $text = $h2->textContent;
                if (preg_match('/Submitted so far\(([0-9]+)\)/i', $text, $matches)) {
                    $gameJam->submission_count = (int) $matches[1];

                    return;
                }
            }
        }

        if (! $gameJam->submission_count) {
            $entries = $doc->querySelectorAll('.game_cell');
            if (count($entries) > 0) {
                $gameJam->submission_count = count($entries);
            }
        }
    }

    private function extractDateRangeFromDivText(GameJam $gameJam, $divs, string $needle, string $pattern): bool
    {
        foreach ($divs as $div) {
            if (! str_contains($div->textContent, $needle)) {
                continue;
            }

            if (preg_match($pattern, $div->textContent, $matches)) {
                return $this->assignDateRange($gameJam, $matches[1], $matches[2]);
            }
        }

        return false;
    }

    private function extractDateSpans(GameJam $gameJam, HTMLDocument $doc): bool
    {
        $dateSpans = $doc->querySelectorAll('span.date_format');
        if (count($dateSpans) < 2) {
            return false;
        }

        $firstDate = trim($dateSpans[0]->textContent);
        $secondDate = trim($dateSpans[1]->textContent);
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $firstDate) ||
            ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $secondDate)) {
            return false;
        }

        return $this->assignDateRange($gameJam, $firstDate, $secondDate);
    }

    private function extractStatsDates(GameJam $gameJam, HTMLDocument $doc): void
    {
        foreach ($doc->querySelectorAll('.jam_stats_container .stat_box, .jam_stats .stat_box') as $element) {
            $label = $element->querySelector('.label');
            $value = $element->querySelector('.value');

            if (! $label || ! $value) {
                continue;
            }

            $labelText = trim($label->textContent);
            $valueText = trim($value->textContent);

            if (str_contains($labelText, 'Start')) {
                $gameJam->start_date = $this->parseDate($valueText) ?? $gameJam->start_date;
            } elseif (str_contains($labelText, 'End')) {
                $gameJam->end_date = $this->parseDate($valueText) ?? $gameJam->end_date;
            } elseif (str_contains($labelText, 'Submissions')) {
                $gameJam->submission_count = (int) $valueText;
            } elseif (str_contains($labelText, 'Participants')) {
                $gameJam->participant_count = (int) $valueText;
            }
        }
    }

    private function extractInfoLineDates(GameJam $gameJam, HTMLDocument $doc): void
    {
        foreach ($doc->querySelectorAll('.info_line, .jam_info_line') as $line) {
            $text = $line->textContent;

            if (str_contains($text, 'Starts:')) {
                $parts = explode('Starts:', $text, 2);
                $gameJam->start_date = $this->parseDate(trim($parts[1] ?? '')) ?? $gameJam->start_date;
            } elseif (str_contains($text, 'Ends:')) {
                $parts = explode('Ends:', $text, 2);
                $gameJam->end_date = $this->parseDate(trim($parts[1] ?? '')) ?? $gameJam->end_date;
            }
        }
    }

    private function assignDateRange(GameJam $gameJam, string $startDate, string $endDate): bool
    {
        $start = $this->parseDate($startDate);
        $end = $this->parseDate($endDate);

        if (! $start || ! $end) {
            return false;
        }

        $gameJam->start_date = $start;
        $gameJam->end_date = $end;

        return true;
    }

    private function parseDate(string $date): ?DateTime
    {
        try {
            return new DateTime($date);
        } catch (Exception $exception) {
            report($exception);

            return null;
        }
    }
}
