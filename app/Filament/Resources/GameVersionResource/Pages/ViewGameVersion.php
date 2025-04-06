<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersionResource\Pages;

use App\Filament\Resources\GameVersionResource;
use App\Services\GameVersionStatsImportService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewGameVersion extends ViewRecord
{
    protected static string $resource = GameVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('importStats')
                ->label('Import Stats')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('stats_file')
                        ->label('Stats JSON File')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])
                        ->disk('local')
                        ->directory('temp/stats')
                        ->visibility('private')
                        ->preserveFilenames()
                        ->storeFileNamesIn('original_filename')
                        ->maxSize(10240) // 10MB max
                        ->required()
                        ->helperText('Upload a JSON file containing game version statistics.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $filePath = $data['stats_file'];

                        // Use the service to import stats
                        $importService = app(GameVersionStatsImportService::class);
                        $importService->importFromStorage($filePath, $this->record);

                        Notification::make()
                            ->title('Stats imported successfully')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Error importing stats')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        // Clean up the temporary file
                        if (isset($data['stats_file'])) {
                            Storage::delete($data['stats_file']);
                        }
                    }
                }),
        ];
    }
}
