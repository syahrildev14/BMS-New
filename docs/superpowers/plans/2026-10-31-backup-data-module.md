# Backup Data Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin trigger an on-demand database backup from the UI, see a history of past backups, download any of them, and delete old ones — admin-only via a new `system.backup.manage` permission.

**Architecture:** A `backups` table records metadata (filename, path, size, who triggered it). `BackupService` does the actual dump — SQLite via `VACUUM INTO` (a SQLite-native, driver-safe live-snapshot command that works whether the connection is a file or `:memory:`, so it also works under the test suite without special-casing), MySQL via `mysqldump` shelled out through Symfony Process (the project's stated production target per the PRD). A thin `Admin\BackupController` (index/store/download/destroy) and one Inertia page follow the same shape as every other admin CRUD module in this codebase.

**Tech Stack:** Laravel 12 migrations/Eloquent, `symfony/process` (already a transitive dependency — verified present in `vendor/symfony/process`), Pest, Inertia + React, existing shared UI components (`Table`, `SortableTableHead`, `TablePagination`, `confirmDelete`).

## Global Constraints

- New permission `system.backup.manage`, added to the `$permissions` catalog array in `database/seeders/RoleAndPermissionSeeder.php` — admin-only (the `admin` role is already `'admin' => $permissions`, i.e. every permission in the catalog, so no separate role-assignment line is needed).
- No restore-from-backup feature — explicitly out of scope per the approved design.
- No scheduled/automatic backup — manual on-demand only, per the approved design.
- Deleting a backup removes both the physical file and the DB row (not a soft-delete) — per the approved design's "manual delete" requirement.

---

### Task 1: `backups` table, `Backup` model, `BackupService`

**Files:**
- Create: `database/migrations/2026_10_31_010000_create_backups_table.php`
- Create: `app/Models/Backup.php`
- Create: `app/Services/BackupService.php`
- Test: `tests/Feature/BackupServiceTest.php`

**Interfaces:**
- Produces: `App\Models\Backup` (fillable `filename`, `disk_path`, `size_bytes`, `created_by`; `creator(): BelongsTo` → `User`). `App\Services\BackupService::create(User $user): Backup` and `::delete(Backup $backup): void` — both consumed by Task 2's controller.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/BackupServiceTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/BackupServiceTest.php`
Expected: FAIL — `Class "App\Services\BackupService" not found`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_10_31_010000_create_backups_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();

            $table->string('filename');
            $table->string('disk_path');
            $table->unsignedBigInteger('size_bytes');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
```

- [ ] **Step 4: Create the `Backup` model**

Create `app/Models/Backup.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'disk_path',
        'size_bytes',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 5: Create `BackupService`**

Create `app/Services/BackupService.php`:

```php
<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * On-demand database backup, dispatched by the active connection driver:
 * SQLite uses `VACUUM INTO` (a SQLite-native consistent-snapshot command
 * that works for both file-backed and `:memory:` connections — unlike a
 * raw file copy, which would both be unsafe against a live file-backed DB
 * and impossible against `:memory:`, the exact connection the test suite
 * uses per phpunit.xml). MySQL (the PRD's stated production target) shells
 * out to `mysqldump` via Symfony Process.
 */
class BackupService
{
    public function create(User $user): Backup
    {
        $driver = config('database.default');
        $timestamp = now()->format('Y-m-d_His');

        $diskPath = match ($driver) {
            'sqlite' => $this->dumpSqlite($timestamp),
            'mysql' => $this->dumpMysql($timestamp),
            default => throw new \RuntimeException("Backup otomatis belum didukung untuk driver database \"{$driver}\"."),
        };

        $absolutePath = Storage::disk('local')->path($diskPath);

        return Backup::create([
            'filename' => basename($diskPath),
            'disk_path' => $diskPath,
            'size_bytes' => filesize($absolutePath),
            'created_by' => $user->id,
        ]);
    }

    public function delete(Backup $backup): void
    {
        Storage::disk('local')->delete($backup->disk_path);
        $backup->delete();
    }

    private function dumpSqlite(string $timestamp): string
    {
        $diskPath = "backups/backup_{$timestamp}.sqlite";
        Storage::disk('local')->makeDirectory('backups');

        $absolutePath = Storage::disk('local')->path($diskPath);
        DB::statement('VACUUM INTO ?', [$absolutePath]);

        return $diskPath;
    }

    private function dumpMysql(string $timestamp): string
    {
        $diskPath = "backups/backup_{$timestamp}.sql";
        Storage::disk('local')->makeDirectory('backups');

        $connection = config('database.connections.mysql');

        $process = new Process([
            'mysqldump',
            '-h', $connection['host'],
            '-P', (string) $connection['port'],
            '-u', $connection['username'],
            '--password='.$connection['password'],
            $connection['database'],
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Gagal menjalankan mysqldump: '.$process->getErrorOutput());
        }

        Storage::disk('local')->put($diskPath, $process->getOutput());

        return $diskPath;
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/BackupServiceTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: same baseline as before (only the pre-existing unrelated `ExampleTest` failure).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_10_31_010000_create_backups_table.php app/Models/Backup.php app/Services/BackupService.php tests/Feature/BackupServiceTest.php
git commit -m "Add Backup model and BackupService (sqlite VACUUM INTO / mysqldump)"
```

---

### Task 2: `Admin\BackupController`, routes, permission

**Files:**
- Create: `app/Http/Controllers/Admin/BackupController.php`
- Modify: `routes/web.php`
- Modify: `database/seeders/RoleAndPermissionSeeder.php`
- Test: `tests/Feature/BackupControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\BackupService` (Task 1).
- Produces: routes `admin.backups.index` (GET), `admin.backups.store` (POST), `admin.backups.download` (GET `{backup}`), `admin.backups.destroy` (DELETE `{backup}`) — consumed by Task 3's frontend.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/BackupControllerTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/BackupControllerTest.php`
Expected: FAIL — route `admin.backups.index` not defined.

- [ ] **Step 3: Add the permission to the seeder catalog**

In `database/seeders/RoleAndPermissionSeeder.php`, the `$permissions` array currently ends with:

```php
            'purchase-orders.manage',
            'stock.manage',
        ];
```

Change it to:

```php
            'purchase-orders.manage',
            'stock.manage',
            'system.backup.manage',
            'system.audit-log.view',
        ];
```

(Both new permissions are added here together since the next plan, Audit Log, needs `system.audit-log.view` in the same catalog — adding both now avoids a second edit to this exact line later. Neither is added to `$rolePermissions` for any non-admin role, so only `admin` gets them, via the existing `'admin' => $permissions` line.)

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Admin/BackupController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/backups/index', [
            'backups' => Backup::with('creator')->latest()->get(),
        ]);
    }

    public function store(Request $request, BackupService $service): RedirectResponse
    {
        $service->create($request->user());

        return back()->with('success', 'Backup berhasil dibuat.');
    }

    public function download(Backup $backup): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($backup->disk_path), 404);

        return Storage::disk('local')->download($backup->disk_path, $backup->filename);
    }

    public function destroy(Backup $backup, BackupService $service): RedirectResponse
    {
        $service->delete($backup);

        return back()->with('success', 'Backup berhasil dihapus.');
    }
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Admin\BackupController;
```

(Insert alphabetically among the other `Admin\*` imports, i.e. right after `use App\Http\Controllers\Admin\DapurController;` — wait, `BackupController` sorts before `DapurController` alphabetically, so insert it as the first `Admin\*` import, before `use App\Http\Controllers\Admin\DapurController;`.)

Inside the `admin.` prefix group (the block starting `Route::prefix('admin')->name('admin.')->group(function () {`), add this new sub-group — place it right after the existing `Route::resource('dapurs', ...)` block, before the closing `});` of the `admin.` group:

```php
        Route::prefix('backups')->name('backups.')->middleware('permission:system.backup.manage')->group(function () {
            Route::get('/', [BackupController::class, 'index'])->name('index');
            Route::post('/', [BackupController::class, 'store'])->name('store');
            Route::get('{backup}/download', [BackupController::class, 'download'])->name('download');
            Route::delete('{backup}', [BackupController::class, 'destroy'])->name('destroy');
        });
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/BackupControllerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: same baseline as before.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/BackupController.php routes/web.php database/seeders/RoleAndPermissionSeeder.php tests/Feature/BackupControllerTest.php
git commit -m "Add Admin\BackupController, routes, and system.backup.manage permission"
```

---

### Task 3: Frontend — Backup Data page + sidebar entry

**Files:**
- Modify: `resources/js/shared/types/prd.type.ts`
- Modify: `resources/js/shared/lib/format.ts`
- Create: `resources/js/pages/admin/backups/index.tsx`
- Modify: `resources/js/shared/constants/menu.config.ts`

**Interfaces:**
- Consumes: routes from Task 2 (`admin.backups.index/store/download/destroy`); `confirmDelete`/`errorAlert` from `@/shared/lib/alerts` (already exist).
- Produces: nothing consumed by later tasks — this is the last task in this plan.

- [ ] **Step 1: Add the `Backup` type**

In `resources/js/shared/types/prd.type.ts`, add (anywhere alongside the other interfaces, e.g. right after the `AppUser` interface at the end of the file):

```ts
export interface Backup {
  id: number;
  filename: string;
  size_bytes: number;
  created_at: string;
  creator: { id: number; name: string } | null;
}
```

- [ ] **Step 2: Add a `formatBytes` helper**

In `resources/js/shared/lib/format.ts`, add:

```ts
export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;

  const units = ['KB', 'MB', 'GB'];
  let value = bytes / 1024;
  let unitIndex = 0;

  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex++;
  }

  return `${value.toFixed(1)} ${units[unitIndex]}`;
}
```

- [ ] **Step 3: Create the page**

Create `resources/js/pages/admin/backups/index.tsx`:

```tsx
import { Head, router } from '@inertiajs/react';
import { Database } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { confirmDelete, errorAlert } from '@/shared/lib/alerts';
import { formatBytes } from '@/shared/lib/format';
import type { Backup } from '@/shared/types/prd.type';

const columns: TableColumn<Backup>[] = [
  { key: 'created_at', accessor: (backup) => backup.created_at },
  { key: 'size_bytes', accessor: (backup) => backup.size_bytes },
  { key: 'creator', accessor: (backup) => backup.creator?.name },
];

export default function AdminBackupsIndex({ backups }: { backups: Backup[] }) {
  const [creating, setCreating] = useState(false);
  const { sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(backups, columns);

  const createBackup = () => {
    setCreating(true);
    router.post(route('admin.backups.store'), {}, {
      onFinish: () => setCreating(false),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal membuat backup.'),
    });
  };

  const destroy = async (backup: Backup) => {
    const ok = await confirmDelete({ title: `Hapus backup "${backup.filename}"?` });
    if (ok) router.delete(route('admin.backups.destroy', backup.id));
  };

  return (
    <>
      <Head title="Backup Data" />

      <PageHeader
        title="Backup Data"
        description="Backup database on-demand. Tidak ada backup otomatis terjadwal — klik tombol di bawah setiap kali butuh snapshot baru."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Backup Data' }]}
        actions={
          <Button onClick={createBackup} disabled={creating}>
            <Database className="h-4 w-4" />
            {creating ? 'Membuat Backup...' : 'Backup Sekarang'}
          </Button>
        }
      />

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Dibuat" columnKey="created_at" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Ukuran" columnKey="size_bytes" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dibuat Oleh" columnKey="creator" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                Belum ada backup dibuat.
              </TableCell>
            </TableRow>
          ) : (
            rows.map((backup) => (
              <TableRow key={backup.id}>
                <TableCell className="font-medium">{backup.created_at}</TableCell>
                <TableCell>{formatBytes(backup.size_bytes)}</TableCell>
                <TableCell>{backup.creator?.name ?? '-'}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <a
                      href={route('admin.backups.download', backup.id)}
                      className="text-sm font-medium text-blue-600 hover:underline"
                    >
                      Download
                    </a>
                    <Button variant="ghost" size="sm" onClick={() => destroy(backup)}>
                      Hapus
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />
    </>
  );
}

AdminBackupsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
```

- [ ] **Step 4: Add the sidebar entry**

In `resources/js/shared/constants/menu.config.ts`, the `Admin` section currently reads:

```ts
  {
    label: 'Admin',
    icon: ShieldCheck,
    children: [
      { label: 'Role & Permission', href: '/admin/roles', permission: 'roles.manage' },
      { label: 'Pengguna', href: '/admin/users', permission: 'users.manage' },
    ],
  },
```

Change it to:

```ts
  {
    label: 'Admin',
    icon: ShieldCheck,
    children: [
      { label: 'Role & Permission', href: '/admin/roles', permission: 'roles.manage' },
      { label: 'Pengguna', href: '/admin/users', permission: 'users.manage' },
      { label: 'Backup Data', href: '/admin/backups', permission: 'system.backup.manage' },
    ],
  },
```

(The "Log Aktivitas" entry is intentionally left for the separate Audit Log plan, which touches this exact same block next — adding it here too would conflict when that plan's diff is applied.)

- [ ] **Step 5: Typecheck**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: same 2 pre-existing unrelated errors — no new errors.

- [ ] **Step 6: Run the full backend suite once more**

Run: `php artisan test`
Expected: same baseline as before.

- [ ] **Step 7: Manually verify in the browser**

Log in as `admin@gmail.com` / `12341234`, open "Admin > Backup Data" from the sidebar, click "Backup Sekarang", confirm a row appears with a non-zero size, click "Download" (file should download), then "Hapus" (row disappears, confirmation dialog shown first).

- [ ] **Step 8: Commit**

```bash
git add resources/js/shared/types/prd.type.ts resources/js/shared/lib/format.ts resources/js/pages/admin/backups/index.tsx resources/js/shared/constants/menu.config.ts
git commit -m "Add Backup Data admin page and sidebar entry"
```

## Self-Review Notes

- **Spec coverage:** manual on-demand trigger ✓ Task 3; history list (date, size, creator) ✓ Task 3; download ✓ Task 2+3; manual delete with confirmation ✓ Task 2+3; sqlite + mysql driver support ✓ Task 1; admin-only permission ✓ Task 2 Step 3.
- **Out of scope confirmed (per approved design):** no restore UI, no scheduled backups — neither appears in any task above.
- **Type consistency:** `BackupService::create(User $user): Backup` (Task 1) matches the call in `BackupController::store()` (Task 2) and `BackupService::delete(Backup $backup): void` matches `BackupController::destroy()`.
