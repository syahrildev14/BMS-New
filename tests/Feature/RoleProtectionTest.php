<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Role;

it('refuses to delete a role that still has users assigned', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');
    $dapurRole = Role::findByName('dapur');
    User::factory()->create()->assignRole('dapur');

    $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $dapurRole));

    $response->assertSessionHasErrors();
    expect(Role::where('name', 'dapur')->exists())->toBeTrue();
});

it('allows deleting a role with no users assigned', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');
    $emptyRole = Role::create(['name' => 'unused-role', 'guard_name' => 'web']);

    $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $emptyRole));

    $response->assertSessionHasNoErrors();
    expect(Role::where('name', 'unused-role')->exists())->toBeFalse();
});

it('refuses to strip roles.manage from the only role that holds it, leaving the system with no way to manage roles', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');
    $adminRole = Role::findByName('admin');

    $response = $this->actingAs($admin)->patch(route('admin.roles.update', $adminRole), [
        'name' => 'admin',
        'permissions' => ['users.manage'], // dropping roles.manage
    ]);

    $response->assertSessionHasErrors();
    expect($adminRole->fresh()->hasPermissionTo('roles.manage'))->toBeTrue();
});

it('allows stripping roles.manage from a role when another role with an assigned user still has it', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');

    $otherRole = Role::create(['name' => 'secondary-admin', 'guard_name' => 'web']);
    $otherRole->syncPermissions(['roles.manage']);
    User::factory()->create()->assignRole('secondary-admin');

    $adminRole = Role::findByName('admin');

    $response = $this->actingAs($admin)->patch(route('admin.roles.update', $adminRole), [
        'name' => 'admin',
        'permissions' => ['users.manage'],
    ]);

    $response->assertSessionHasNoErrors();
    expect($adminRole->fresh()->hasPermissionTo('roles.manage'))->toBeFalse();
});
