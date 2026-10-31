<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * On-demand database backup, dispatched by the active connection driver:
 * SQLite uses `VACUUM INTO` (a SQLite-native consistent-snapshot command
 * that works for both file-backed and `:memory:` connections — unlike a
 * raw file copy, which would both be unsafe against a live file-backed DB
 * and impossible against `:memory:`, the exact connection the test suite
 * uses per phpunit.xml). MySQL (the PRD's stated production target) shells
 * out to `mysqldump` via Symfony Process.
 */
class BackupService
{
    public function create(User $user): Backup
    {
        $driver = config('database.default');
        $timestamp = now()->format('Y-m-d_His') . '_' . uniqid();

        $diskPath = match ($driver) {
            'sqlite' => $this->dumpSqlite($timestamp),
            'mysql' => $this->dumpMysql($timestamp),
            default => throw new \RuntimeException("Backup otomatis belum didukung untuk driver database \"{$driver}\"."),
        };

        $absolutePath = Storage::disk('local')->path($diskPath);

        return Backup::create([
            'filename' => basename($diskPath),
            'disk_path' => $diskPath,
            'size_bytes' => filesize($absolutePath),
            'created_by' => $user->id,
        ]);
    }

    public function delete(Backup $backup): void
    {
        Storage::disk('local')->delete($backup->disk_path);
        $backup->delete();
    }

    private function dumpSqlite(string $timestamp): string
    {
        $diskPath = "backups/backup_{$timestamp}.sqlite";
        Storage::disk('local')->makeDirectory('backups');

        $absolutePath = Storage::disk('local')->path($diskPath);

        // VACUUM INTO cannot run within a transaction.
        // Use a separate PDO connection to avoid interfering with Laravel's transaction management.
        $dbPath = config('database.connections.sqlite.database');
        $pdo = new \PDO("sqlite:{$dbPath}");
        $pdo->exec('VACUUM INTO ' . $pdo->quote($absolutePath));
        unset($pdo);

        return $diskPath;
    }

    private function dumpMysql(string $timestamp): string
    {
        $diskPath = "backups/backup_{$timestamp}.sql";
        Storage::disk('local')->makeDirectory('backups');

        $connection = config('database.connections.mysql');

        $process = new Process([
            'mysqldump',
            '-h', $connection['host'],
            '-P', (string) $connection['port'],
            '-u', $connection['username'],
            '--password='.$connection['password'],
            $connection['database'],
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Gagal menjalankan mysqldump: '.$process->getErrorOutput());
        }

        Storage::disk('local')->put($diskPath, $process->getOutput());

        return $diskPath;
    }
}
