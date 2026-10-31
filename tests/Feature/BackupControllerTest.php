<?php

use App\Models\Backup;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('lets an admin create, list, download, and delete a backup', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');

    $storeResponse = $this->actingAs($admin)->post(route('admin.backups.store'));
    $storeResponse->assertRedirect();

    $backup = Backup::first();
    expect($backup)->not->toBeNull();

    $indexResponse = $this->actingAs($admin)->get(route('admin.backups.index'));
    $indexResponse->assertOk();

    $downloadResponse = $this->actingAs($admin)->get(route('admin.backups.download', $backup));
    $downloadResponse->assertOk();

    $destroyResponse = $this->actingAs($admin)->delete(route('admin.backups.destroy', $backup));
    $destroyResponse->assertRedirect();
    expect(Backup::find($backup->id))->toBeNull();
});

it('refuses access to a user without system.backup.manage', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.backups.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.backups.store'))->assertForbidden();
});
