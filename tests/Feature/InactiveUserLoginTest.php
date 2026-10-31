<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('refuses login for a deactivated user even with correct credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('correct-password'),
        'is_active' => false,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Auth::check())->toBeFalse();
});

it('still allows login for an active user', function () {
    $user = User::factory()->create([
        'password' => bcrypt('correct-password'),
        'is_active' => true,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Auth::check())->toBeTrue();
});
