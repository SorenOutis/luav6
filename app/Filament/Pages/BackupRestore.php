<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Collection;

class BackupRestore extends Page implements HasTable, HasSchemas, HasActions
{
    use InteractsWithTable;
    use InteractsWithSchemas;
    use InteractsWithActions;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    protected string $view = 'filament.pages.backup-restore';

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    protected static ?string $title = 'Backup & Restore';

    public function mount(): void
    {
        // No longer needed for main page body
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadBackup')
                ->label('Upload & Restore')
                ->color('warning')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('backup_file')
                        ->label('Select SQLite File')
                        ->disk('local')
                        ->directory('backups')
                        ->visibility('private')
                        ->required()
                        ->preserveFilenames(),
                ])
                ->action(function (array $data) {
                    $path = is_array($data['backup_file']) ? reset($data['backup_file']) : $data['backup_file'];
                    
                    if (pathinfo($path, PATHINFO_EXTENSION) !== 'sqlite') {
                        Notification::make()
                            ->title('Upload failed')
                            ->body('The uploaded file must have a .sqlite extension.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $this->restoreBackup($path);
                }),
            Action::make('createBackup')
                ->label('Create Backup')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->action(fn () => $this->createBackup()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->getBackupRecords())
            ->description('Manage your database backups. Current database size: ' . $this->formatBytes(File::exists(database_path('database.sqlite')) ? File::size(database_path('database.sqlite')) : 0))
            ->columns([
                TextColumn::make('name')
                    ->label('Backup Name'),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $this->formatBytes($state)),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label('Restore')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (array $record) => $this->restoreBackup($record['path'])),
                Action::make('download')
                    ->label('Download')
                    ->color('success')
                    ->action(fn (array $record) => Storage::disk('local')->download($record['path'])),
                Action::make('delete')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (array $record) => $this->deleteBackup($record['path'])),
            ]);
    }

    protected function getBackupRecords(): array
    {
        $backupPath = storage_path('app/private/backups');
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
            return [];
        }

        $files = File::files($backupPath);
        $records = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'sqlite') {
                $records[$file->getFilename()] = [
                    'id' => $file->getFilename(),
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'created_at' => \Illuminate\Support\Carbon::createFromTimestamp($file->getMTime()),
                    'path' => 'backups/' . $file->getFilename(),
                ];
            }
        }

        // Sort by created_at descending
        uasort($records, fn ($a, $b) => $b['created_at']->timestamp <=> $a['created_at']->timestamp);

        return $records;
    }

    public function getTableRecordKey($record): string
    {
        return $record['id'];
    }

    public function getTableRecordsPerPage(): int
    {
        return 50;
    }

    public function createBackup()
    {
        $databasePath = database_path('database.sqlite');
        
        if (!File::exists($databasePath)) {
            Notification::make()
                ->title('Backup failed')
                ->body('Database file not found.')
                ->danger()
                ->send();
            return;
        }

        $backupFileName = 'backup-' . date('Y-m-d-H-i-s') . '.sqlite';
        $backupPath = storage_path('app/private/backups/' . $backupFileName);

        try {
            if (!File::exists(dirname($backupPath))) {
                File::makeDirectory(dirname($backupPath), 0755, true);
            }
            File::copy($databasePath, $backupPath);
            
            Notification::make()
                ->title('Backup created successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Backup failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function restoreBackup(string $path)
    {
        $backupPath = storage_path('app/private/' . $path);
        $databasePath = database_path('database.sqlite');

        if (!File::exists($backupPath)) {
            Notification::make()
                ->title('Restore failed')
                ->body('Backup file not found.')
                ->danger()
                ->send();
            return;
        }

        try {
            // It's safer to backup the current DB before restoring
            $currentBackup = 'pre-restore-' . date('Y-m-d-H-i-s') . '.sqlite';
            File::copy($databasePath, storage_path('app/private/backups/' . $currentBackup));

            File::copy($backupPath, $databasePath);

            Notification::make()
                ->title('Database restored successfully')
                ->body('A pre-restore backup was created: ' . $currentBackup)
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Restore failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteBackup(string $path)
    {
        try {
            Storage::disk('local')->delete($path);
            
            Notification::make()
                ->title('Backup deleted successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Delete failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
