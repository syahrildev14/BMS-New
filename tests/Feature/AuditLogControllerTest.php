<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('lets an admin view the audit log list', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');
    AuditLog::create([
        'user_id' => $admin->id,
        'action' => 'ORDER_REVISION_AUTO_MERGED',
        'description' => 'Order #1 (ORD/TEST/01)',
        'new_data' => ['order_id' => 1, 'items' => []],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.audit-logs.index'));

    $response->assertOk();
});

it('refuses access to a user without system.audit-log.view', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();
});

it('exposes no write routes for audit logs', function () {
    expect(\Illuminate\Support\Facades\Route::has('admin.audit-logs.store'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Route::has('admin.audit-logs.update'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Route::has('admin.audit-logs.destroy'))->toBeFalse();
});
