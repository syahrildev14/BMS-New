<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    Route::middleware('web')->group(function () {
        Route::get('/_test/abort/{code}', fn (int $code) => abort($code));
        Route::get('/_test/crash', fn () => throw new RuntimeException('boom'));
    });
});

it('renders the inertia error page for an unknown url', function () {
    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 404));
});

it('renders the inertia error page for :dataset', function (int $code) {
    $this->get("/_test/abort/{$code}")
        ->assertStatus($code)
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', $code));
})->with([401, 403, 503]);

it('renders the inertia error page for an unhandled exception when debug is off', function () {
    config(['app.debug' => false]);

    $this->get('/_test/crash')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 500));
});

it('keeps the laravel debug page for 500 when debug is on', function () {
    config(['app.debug' => true]);

    $response = $this->get('/_test/crash')->assertStatus(500);

    expect($response->headers->get('X-Inertia'))->toBeNull();
    expect($response->getContent())->not->toContain('"component":"error"');
});

it('renders the error page for an authenticated user lacking permission', function () {
    $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('gudang.stock.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
});

it('keeps json errors for json requests', function () {
    $this->getJson('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('falls back to the static blade view when inertia cannot render', function () {
    // Simulasikan kegagalan render Inertia (mis. DB mati saat app.blade.php memuat tema).
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->app->bind(\App\Services\AppearanceService::class, fn () => throw new RuntimeException('db down'));

    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee('Halaman Tidak Ditemukan')
        ->assertDontSee('"component":"error"', false);
});
