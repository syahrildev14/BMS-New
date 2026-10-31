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
        $timestamp = now()->format('Y-m-d_His').'_'.uniqid();

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

    private function dumpSqlite(string $timestamp): string
    {
        $diskPath = "backups/backup_{$timestamp}.sqlite";
        Storage::disk('local')->makeDirectory('backups');

        $absolutePath = Storage::disk('local')->path($diskPath);

        // VACUUM INTO cannot run within a transaction.
        // Use a separate PDO connection to avoid interfering with Laravel's transaction management.
        $dbPath = config('database.connections.sqlite.database');
        $pdo = new \PDO("sqlite:{$dbPath}");
        $pdo->exec('VACUUM INTO '.$pdo->quote($absolutePath));
        unset($pdo);

        return $diskPath;
    }

    private function dumpMysql(string $timestamp): string
    {
        $diskPath = "backups/backup_{$timestamp}.sql";
        Storage::disk('local')->makeDirectory('backups');

        $absolutePath = Storage::disk('local')->path($diskPath);

        $connection = config('database.connections.mysql');

        $process = new Process([
            'mysqldump',
            '-h', $connection['host'],
            '-P', (string) $connection['port'],
            '-u', $connection['username'],
            '--password='.$connection['password'],
            $connection['database'],
        ]);
        // mysqldump of a non-trivial production database can easily run past
        // Symfony Process's default 60s timeout. This is an admin-triggered,
        // not time-critical, operation, so disable the timeout entirely.
        $process->setTimeout(null);

        // Disable Symfony Process's internal output buffering so the (potentially
        // multi-GB) STDOUT stream is never held in PHP memory. Instead, stream each
        // STDOUT chunk straight to the destination file as it arrives. Only STDERR
        // (a small error message on failure, at most) is buffered in memory.
        $process->disableOutput();

        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Gagal membuka file tujuan backup: {$absolutePath}");
        }

        $errorOutput = '';

        try {
            $process->run(function (string $type, string $buffer) use ($handle, &$errorOutput) {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                } else {
                    $errorOutput .= $buffer;
                }
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Gagal menjalankan mysqldump: '.$errorOutput);
        }

        return $diskPath;
    }
}
