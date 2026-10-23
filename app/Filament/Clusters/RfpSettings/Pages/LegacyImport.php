<?php

namespace App\Filament\Clusters\RfpSettings\Pages;

use App\Enums\RoleName;
use App\Filament\Clusters\RfpSettings\RfpSettingsCluster;
use App\Jobs\Rfp\RunLegacyImport;
use App\Models\Rfp\LegacyImportRun;
use App\Models\Rfp\Rfp;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs the legacy RFP Portal import (`rfp:import-legacy`) before go-live. It REPLACES
 * every RFP here, so it is Admin-only, needs "IMPORT" typed to start, and disappears
 * once RFP_LEGACY_IMPORT_ENABLED is false (the default in production).
 */
class LegacyImport extends Page
{
    protected string $view = 'filament.clusters.rfp-settings.pages.legacy-import';

    protected static ?string $cluster = RfpSettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Legacy Import';

    protected static ?string $title = 'Legacy RFP Import';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return config('rfp.legacy_import_enabled') && (auth()->user()?->hasRole(RoleName::Admin) ?? false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runImport')
                ->label('Run import')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('danger')
                ->disabled(fn (): bool => LegacyImportRun::isRunning())
                ->modalHeading('Replace every RFP with the legacy data?')
                ->modalDescription('Every RFP in this system is deleted, then copied again from the legacy RFP Portal, including RFPs created or approved here. Users, roles and master data are updated. This cannot be undone.')
                ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                ->schema([
                    TextInput::make('confirmation')
                        ->label('Type IMPORT to confirm')
                        ->required()
                        ->in(['IMPORT'])
                        ->validationMessages(['in' => 'Type IMPORT in capitals to confirm.']),
                ])
                ->modalSubmitActionLabel('Replace and import')
                ->action(function (): void {
                    $user = auth()->user();
                    $run = LegacyImportRun::create(['started_by' => $user->name, 'status' => LegacyImportRun::RUNNING]);

                    RunLegacyImport::dispatchAfterResponse($run->id, $user->email);

                    Notification::make()
                        ->title('Import started')
                        ->body('It runs in the background and can take a few minutes. This page updates when it finishes.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array{connected: bool, database: string, legacyRfps: ?int, error: ?string}
     */
    public function legacyConnection(): array
    {
        $database = (string) config('database.connections.legacy_rfp.database');

        try {
            return ['connected' => true, 'database' => $database, 'legacyRfps' => DB::connection('legacy_rfp')->table('rfps')->count(), 'error' => null];
        } catch (Throwable $exception) {
            return ['connected' => false, 'database' => $database, 'legacyRfps' => null, 'error' => $exception->getMessage()];
        }
    }

    public function rfpsHere(): int
    {
        return Rfp::withTrashed()->count();
    }

    /**
     * @return Collection<int, LegacyImportRun>
     */
    public function runs(): Collection
    {
        return LegacyImportRun::query()->latest('id')->limit(5)->get();
    }
}
