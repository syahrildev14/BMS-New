<?php

use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\Storage;

it('creates a sqlite backup file and a matching Backup row', function () {
    $user = User::factory()->create();

    $backup = app(BackupService::class)->create($user);

    expect($backup->id)->not->toBeNull();
    expect($backup->created_by)->toBe($user->id);
    expect($backup->size_bytes)->toBeGreaterThan(0);
    expect(Storage::disk('local')->exists($backup->disk_path))->toBeTrue();
});

it('deletes both the physical file and the Backup row', function () {
    $user = User::factory()->create();
    $backup = app(BackupService::class)->create($user);
    $path = $backup->disk_path;

    app(BackupService::class)->delete($backup);

    expect(Storage::disk('local')->exists($path))->toBeFalse();
    expect(Backup::find($backup->id))->toBeNull();
});

it('refuses to back up an unsupported database driver', function () {
    config(['database.default' => 'pgsql']);
    $user = User::factory()->create();

    expect(fn () => app(BackupService::class)->create($user))
        ->toThrow(RuntimeException::class);

    expect(Backup::count())->toBe(0);
});
