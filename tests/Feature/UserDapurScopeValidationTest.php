<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('rejects creating a user with the dapur role but no dapur_id', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Dapur Tanpa Dapur',
        'email' => 'nodapur@bms.test',
        'password' => 'password123',
        'role' => 'dapur',
        'dapur_id' => null,
    ]);

    $response->assertSessionHasErrors();
    expect(User::where('email', 'nodapur@bms.test')->exists())->toBeFalse();
});

it('allows creating a user with the dapur role when dapur_id is provided', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = makeUserWithRole('admin');
    $dapur = makeDapur();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Dapur Lengkap',
        'email' => 'withdapur@bms.test',
        'password' => 'password123',
        'role' => 'dapur',
        'dapur_id' => $dapur->id,
    ]);

    $response->assertSessionHasNoErrors();
    expect(User::where('email', 'withdapur@bms.test')->first()?->dapur_id)->toBe($dapur->id);
});
