<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupRestore extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return ! ($user && $user->is_admin && ! $user->isSuperAdmin());
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected string $view = 'filament.pages.backup-restore';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

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
                        ->label('Select Backup ZIP File')
                        ->disk('local')
                        ->directory('backups')
                        ->required()
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'zip'])
                        ->maxSize(524288), // 512MB
                ])
                ->action(function (array $data) {
                    $path = is_array($data['backup_file']) ? reset($data['backup_file']) : $data['backup_file'];

                    if (! $path) {
                        Notification::make()
                            ->title('Restore failed')
                            ->body('No file was uploaded.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if (pathinfo($path, PATHINFO_EXTENSION) !== 'zip') {
                        Notification::make()
                            ->title('Restore failed')
                            ->body('The uploaded file must be a .zip archive.')
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
            ->description('Manage full backups for the database and public uploaded files. Current database size: '.$this->formatBytes(File::exists(database_path('database.sqlite')) ? File::size(database_path('database.sqlite')) : 0))
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
        if (! File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);

            return [];
        }

        $files = File::files($backupPath);
        $records = [];

        foreach ($files as $file) {
            if (in_array($file->getExtension(), ['sqlite', 'zip'])) {
                $records[$file->getFilename()] = [
                    'id' => $file->getFilename(),
                    'name' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'created_at' => Carbon::createFromTimestamp($file->getMTime()),
                    'path' => 'backups/'.$file->getFilename(),
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

        if (! File::exists($databasePath)) {
            Notification::make()
                ->title('Backup failed')
                ->body('Database file not found.')
                ->danger()
                ->send();

            return;
        }

        $backupFileName = 'backup-'.date('Y-m-d-H-i-s').'.zip';
        $backupPath = storage_path('app/private/backups/'.$backupFileName);

        try {
            if (! File::exists(dirname($backupPath))) {
                File::makeDirectory(dirname($backupPath), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $this->addDatabaseToZip($zip);
                $this->addPublicStorageToZip($zip);

                $zip->close();

                Notification::make()
                    ->title('Backup created successfully')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Backup failed')
                    ->body('Could not create zip archive.')
                    ->danger()
                    ->send();
            }
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
        $backupPath = storage_path('app/private/'.$path);
        $databasePath = database_path('database.sqlite');
        $restoreTempPath = storage_path('app/private/backups/restore-'.date('Y-m-d-H-i-s').'-'.uniqid().'.sqlite');

        if (! File::exists($backupPath)) {
            Notification::make()
                ->title('Restore failed')
                ->body('Backup file not found.')
                ->danger()
                ->send();

            return;
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($backupPath) !== true) {
                Notification::make()
                    ->title('Restore failed')
                    ->body('Could not open backup zip archive.')
                    ->danger()
                    ->send();

                return;
            }

            if ($zip->locateName('database.sqlite') === false) {
                $zip->close();

                Notification::make()
                    ->title('Restore failed')
                    ->body('The backup does not contain database.sqlite.')
                    ->danger()
                    ->send();

                return;
            }

            if (! copy('zip://'.realpath($backupPath).'#database.sqlite', $restoreTempPath)) {
                $zip->close();

                Notification::make()
                    ->title('Restore failed')
                    ->body('Could not extract the database from the backup.')
                    ->danger()
                    ->send();

                return;
            }

            $integrity = $this->checkSqliteIntegrity($restoreTempPath);
            if ($integrity !== 'ok') {
                $zip->close();
                File::delete($restoreTempPath);

                Notification::make()
                    ->title('Restore failed')
                    ->body('The backup database failed integrity check: '.$integrity)
                    ->danger()
                    ->send();

                return;
            }

            // It's safer to backup the current DB before restoring
            $currentBackup = 'pre-restore-'.date('Y-m-d-H-i-s').'.zip';
            $currentBackupPath = storage_path('app/private/backups/'.$currentBackup);

            // Create a pre-restore backup of the current state (DB + public uploads)
            $preRestoreZip = new ZipArchive;
            if ($preRestoreZip->open($currentBackupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $this->addDatabaseToZip($preRestoreZip);
                $this->addPublicStorageToZip($preRestoreZip);
                $preRestoreZip->close();
            } else {
                $zip->close();
                File::delete($restoreTempPath);

                Notification::make()
                    ->title('Pre-restore backup failed')
                    ->body('Could not create pre-restore zip archive.')
                    ->danger()
                    ->send();

                return;
            }

            DB::disconnect();

            File::copy($restoreTempPath, $databasePath);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);

                if (str_starts_with($filename, 'public/')) {
                    $zip->extractTo(storage_path('app'), $filename);
                }
            }

            $zip->close();
            File::delete($restoreTempPath);
            DB::purge();

            // ── Post-restore: ensure workspace columns & assign to admin ──
            $this->finishRestore();

            Notification::make()
                ->title('Database restored successfully')
                ->body('A pre-restore backup was created: '.$currentBackup)
                ->success()
                ->send();
        } catch (\Exception $e) {
            if (File::exists($restoreTempPath)) {
                File::delete($restoreTempPath);
            }

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

    protected function addDatabaseToZip(ZipArchive $zip): void
    {
        $databasePath = database_path('database.sqlite');
        $snapshotPath = storage_path('app/private/backups/database-snapshot-'.date('Y-m-d-H-i-s').'-'.uniqid().'.sqlite');

        if (! File::exists($databasePath)) {
            return;
        }

        if (! File::exists(dirname($snapshotPath))) {
            File::makeDirectory(dirname($snapshotPath), 0755, true);
        }

        $escapedSnapshotPath = str_replace("'", "''", $snapshotPath);
        DB::statement("VACUUM INTO '{$escapedSnapshotPath}'");

        $zip->addFile($snapshotPath, 'database.sqlite');
        register_shutdown_function(static function () use ($snapshotPath): void {
            if (File::exists($snapshotPath)) {
                File::delete($snapshotPath);
            }
        });
    }

    protected function checkSqliteIntegrity(string $databasePath): string
    {
        try {
            $pdo = new \PDO('sqlite:'.$databasePath);
            $result = $pdo->query('PRAGMA integrity_check')->fetchColumn();

            return is_string($result) ? $result : 'Unable to read integrity result.';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    protected function addPublicStorageToZip(ZipArchive $zip): void
    {
        $publicStoragePath = storage_path('app/public');

        if (! File::exists($publicStoragePath)) {
            return;
        }

        foreach (File::allFiles($publicStoragePath) as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getPathname(), 'public/'.$relativePath);
        }
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    /**
     * Run after a database restore to ensure all workspace columns exist
     * and assign all data to the admin@example.com super admin.
     *
     * This handles backups created before workspace migrations were added.
     */
    protected function finishRestore(): void
    {
        // Step 1: Run any pending migrations to ensure workspace columns exist
        Artisan::call('migrate', ['--force' => true]);

        // Step 2: Find or create the admin@example.com super admin
        $admin = DB::table('users')->where('email', 'admin@example.com')->first();

        if ($admin) {
            $adminId = $admin->id;
            // Ensure they have admin + super admin status
            DB::table('users')->where('id', $adminId)->update([
                'is_admin' => true,
                'is_super_admin' => true,
            ]);
        } else {
            // Create the admin user
            $adminId = DB::table('users')->insertGetId([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'is_super_admin' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Step 3: Assign admin_id on all workspace tables where it's null
        $tables = [
            'sections',
            'exams',
            'assignments',
            'courses',
            'seasons',
            'badges',
            'rewards',
            'announcements',
            'ai_question_drafts',
            'anonymous_messages',
            'td_maps',
            'td_enemies',
            'td_towers',
            'td_difficulties',
        ];

        foreach ($tables as $table) {
            DB::table($table)->whereNull('admin_id')->update(['admin_id' => $adminId]);
        }
    }
}
