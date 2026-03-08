<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewReports\Pages;

use App\Filament\Resources\ReviewReports\ReviewReportResource;
use Filament\Resources\Pages\ListRecords;

class ListReviewReports extends ListRecords
{
    protected static string $resource = ReviewReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
