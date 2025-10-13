<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersions\Traits;

use App\Models\GameVersion;
use App\Models\VersionSupportedLanguage;
use App\Services\GameVersionStatsImportService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

trait HandlesGameVersionLanguages
{
    /**
     * Get the import stats action for the header
     */
    protected function getImportStatsAction(): Action
    {
        return Action::make('importStats')
            ->label('Import Stats')
            ->icon('heroicon-o-arrow-up-tray')
            ->schema([
                FileUpload::make('stats_file')
                    ->label('Stats JSON File')
                    ->acceptedFileTypes(['application/json', 'text/plain', 'text/json'])
                    ->disk('local')
                    ->directory('temp/stats')
                    ->visibility('private')
                    ->preserveFilenames()
                    ->storeFileNamesIn('original_filename')
                    ->maxSize(102400) // 100MB max (matches PHP limit)
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
            });
    }

    /**
     * Load the supported languages data
     */
    protected function loadSupportedLanguages(GameVersion $gameVersion): array
    {
        return $gameVersion->supportedLanguages()
            ->with('language')
            ->get()
            ->map(function (VersionSupportedLanguage $language) {
                return [
                    'iso_code' => $language->iso_code,
                    'is_available' => $language->is_available,
                ];
            })
            ->toArray();
    }

    /**
     * Save the supported languages data
     */
    protected function saveSupportedLanguages(GameVersion $gameVersion, array $languagesData): void
    {
        // First, remove all existing supported languages
        $gameVersion->supportedLanguages()->delete();

        // Then add the new ones
        if (! empty($languagesData)) {
            foreach ($languagesData as $language) {
                if (isset($language['iso_code'])) {
                    $gameVersion->addSupportedLanguage(
                        $language['iso_code'],
                        $language['is_available'] ?? true
                    );
                }
            }
        }
    }
}
