<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdditionRequests\Pages;

use App\Filament\Resources\AdditionRequests\AdditionRequestResource;
use App\Models\AdditionRequest;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAdditionRequest extends EditRecord
{
    protected static string $resource = AdditionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $originalStatus = $this->record->status;
        $newStatus = $data['status'];

        // If status is changing from pending to approved/rejected, set review tracking
        if ($originalStatus === AdditionRequest::STATUS_PENDING && $newStatus !== AdditionRequest::STATUS_PENDING) {
            $user = Auth::user();
            if ($user instanceof User) {
                $data['reviewed_at'] = now();
                $data['reviewed_by'] = $user->id;
            }
        }

        // Clear review tracking if changing back to pending
        if ($newStatus === AdditionRequest::STATUS_PENDING && $originalStatus !== AdditionRequest::STATUS_PENDING) {
            $data['reviewed_at'] = null;
            $data['reviewed_by'] = null;
        }

        return $data;
    }
}
