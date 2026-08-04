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
    protected function getImportStatsAction(): Action
    {
        return Action::make('importStats')
            ->label('Import Stats')
            ->icon('heroicon-o-arrow-up-tray')
            ->schema([
                FileUpload::make('stats_file')
                    ->label('Stats File')
                    ->acceptedFileTypes(['application/x-ndjson', 'application/json', 'text/plain', 'text/json'])
                    ->disk('local')
                    ->directory('temp/stats')
                    ->visibility('private')
                    ->preserveFilenames()
                    ->storeFileNamesIn('original_filename')
                    ->maxSize(102400) // 100MB max (matches PHP limit)
                    ->required()
                    ->helperText('Upload the stats document produced by the analyzer (.ndjson).'),
            ])
            ->action(function (array $data): void {
                try {
                    $filePath = $data['stats_file'];

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

    protected function saveSupportedLanguages(GameVersion $gameVersion, array $languagesData): void
    {
        $gameVersion->supportedLanguages()->delete();

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
