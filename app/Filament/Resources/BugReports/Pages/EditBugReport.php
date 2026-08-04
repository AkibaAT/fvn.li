<?php

declare(strict_types=1);

namespace App\Filament\Resources\BugReports\Pages;

use App\Filament\Resources\BugReports\BugReportResource;
use App\Models\BugReport;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditBugReport extends EditRecord
{
    protected static string $resource = BugReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $originalStatus = $this->record->status;
        $newStatus = $data['status'];

        // If status is changing to a resolved state, set resolution tracking
        $resolvedStatuses = [BugReport::STATUS_RESOLVED, BugReport::STATUS_WONT_FIX];
        $wasResolved = in_array($originalStatus, $resolvedStatuses, true);
        $isNowResolved = in_array($newStatus, $resolvedStatuses, true);

        if (! $wasResolved && $isNowResolved) {
            $user = Auth::user();
            if ($user instanceof User) {
                $data['resolved_at'] = now();
                $data['resolved_by'] = $user->id;
            }
        }

        if ($wasResolved && ! $isNowResolved) {
            $data['resolved_at'] = null;
            $data['resolved_by'] = null;
        }

        return $data;
    }
}
