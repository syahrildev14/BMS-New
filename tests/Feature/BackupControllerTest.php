<?php

use App\Models\Backup;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('lets an admin trigger a backup and download it in one request', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.backups.export'));

    $response->assertOk();
    $response->assertHeader('Content-Disposition');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect(Backup::count())->toBe(1);
    expect(Backup::first()->created_by)->toBe($admin->id);
});

it('refuses access to a user without system.backup.manage', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('admin.backups.export'))->assertForbidden();
    expect(Backup::count())->toBe(0);
});
