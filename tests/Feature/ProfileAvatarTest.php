<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

it('uploads an avatar and stores it on the public disk', function () {
    $this->actingAs($this->user)
        ->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.jpg')])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    $path = $this->user->fresh()->avatar_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('replaces the old avatar file when a new one is uploaded', function () {
    $this->actingAs($this->user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $old = $this->user->fresh()->avatar_path;

    $this->actingAs($this->user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('b.jpg')]);
    $new = $this->user->fresh()->avatar_path;

    expect($new)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($new);
});

it('rejects non-image files and keeps the existing avatar', function () {
    $this->actingAs($this->user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $old = $this->user->fresh()->avatar_path;

    $this->actingAs($this->user)
        ->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('avatar');

    expect($this->user->fresh()->avatar_path)->toBe($old);
    Storage::disk('public')->assertExists($old);
});

it('removes the avatar file when the avatar is deleted', function () {
    $this->actingAs($this->user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $path = $this->user->fresh()->avatar_path;

    $this->actingAs($this->user)->delete(route('profile.avatar.destroy'))->assertRedirect(route('profile.edit'));

    expect($this->user->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('removes the avatar file when the account is deleted', function () {
    $this->actingAs($this->user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $path = $this->user->fresh()->avatar_path;

    $this->actingAs($this->user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    Storage::disk('public')->assertMissing($path);
});
