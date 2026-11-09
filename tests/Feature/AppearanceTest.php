<?php

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AppearanceService;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->withoutVite();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('stores a preset color and logs the change', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.appearance.update'), ['color' => '#DC2626'])
        ->assertSessionHasNoErrors();

    expect(app(AppearanceService::class)->primaryColor())->toBe('#DC2626');

    $log = AuditLog::where('action', 'APPEARANCE_UPDATED')->firstOrFail();
    expect($log->old_data['primary_color'])->toBe('#2563EB')
        ->and($log->new_data['primary_color'])->toBe('#DC2626');
});

it('accepts a free color and normalizes it to uppercase', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.appearance.update'), ['color' => '#0e7490'])
        ->assertSessionHasNoErrors();

    expect(AppSetting::where('key', 'primary_color')->value('value'))->toBe('#0E7490');
});

it('accepts every preset', function () {
    $service = app(AppearanceService::class);

    foreach (AppearanceService::PRESETS as $preset) {
        expect($service->problemWith($preset['color']))->toBeNull();
    }
});

it('refuses users without the permission', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');

    $this->actingAs($user)->get(route('admin.appearance.index'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.appearance.update'), ['color' => '#DC2626'])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.appearance.destroy'))->assertForbidden();

    expect(AppSetting::count())->toBe(0);
});

it('rejects a malformed color', function (string $color) {
    $this->actingAs($this->admin)
        ->put(route('admin.appearance.update'), ['color' => $color])
        ->assertSessionHasErrors('color');

    expect(AppSetting::count())->toBe(0);
})->with(['red', '#FFF', '#12345G', '#2563EB;}body{display:none']);

it('rejects colors that are unreadable on a light or dark surface', function (string $color, string $message) {
    $this->actingAs($this->admin)
        ->put(route('admin.appearance.update'), ['color' => $color])
        ->assertSessionHasErrors('color');

    expect(session('errors')->first('color'))->toContain($message);
})->with([
    'too light' => ['#FACC15', 'terlalu terang'],
    'too dark' => ['#0B1120', 'terlalu gelap'],
]);

it('picks the more readable text color', function () {
    $service = app(AppearanceService::class);

    expect($service->foregroundFor('#2563EB'))->toBe('#FFFFFF')
        ->and($service->foregroundFor('#DC2626'))->toBe('#FFFFFF')
        ->and($service->foregroundFor('#F59E0B'))->toBe('#000000');
});

it('writes the saved color into the login page', function () {
    app(AppearanceService::class)->update('#DC2626', $this->admin);

    $this->get('/login')
        ->assertOk()
        ->assertSee(':root{--brand:#DC2626;--brand-foreground:#FFFFFF}', false);
});

it('uses the default color when nothing is saved', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee(':root{--brand:#2563EB;--brand-foreground:#FFFFFF}', false);
});

it('falls back to the default when the stored value is invalid', function () {
    AppSetting::create(['key' => 'primary_color', 'value' => 'red;}body{display:none']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('--brand:#2563EB', false)
        ->assertDontSee('display:none', false);
});

it('resets to the default and clears the cached color', function () {
    $service = app(AppearanceService::class);
    $service->update('#DC2626', $this->admin);
    expect($service->primaryColor())->toBe('#DC2626');

    $this->actingAs($this->admin)->delete(route('admin.appearance.destroy'))->assertRedirect();

    expect($service->primaryColor())->toBe('#2563EB')
        ->and(AppSetting::count())->toBe(0)
        ->and(AuditLog::where('action', 'APPEARANCE_RESET')->count())->toBe(1);
});

it('does not log a reset when nothing was customized', function () {
    $this->actingAs($this->admin)->delete(route('admin.appearance.destroy'))->assertRedirect();

    expect(AuditLog::where('action', 'APPEARANCE_RESET')->count())->toBe(0);
});
