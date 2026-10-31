# Audit Log Viewer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A read-only admin page that lists `audit_logs` (already populated continuously by `RevisionGateService`, `SplitInvoiceService`, etc., but with no UI to view it) with a detail view of the raw `old_data`/`new_data` JSON — admin-only via a new `system.audit-log.view` permission.

**Architecture:** One read-only controller method (`index` only — no store/update/destroy, so the audit trail can never be edited through this UI), one Inertia page reusing the same table/search/pagination components every other admin list page already uses, and a `Dialog` (already in the codebase) for the JSON detail view.

**Tech Stack:** Laravel 12, Inertia + React, Pest, existing shared UI components.

## Global Constraints

- **Read-only, no exceptions** — only an `index` route/method exists. Do not add create/update/delete for audit logs under any circumstance; that would defeat the point of an audit trail.
- Permission `system.audit-log.view` — already added to the `$permissions` catalog array by Task 2, Step 3 of `docs/superpowers/plans/2026-10-31-backup-data-module.md`. **If that plan has not been executed yet, add it to the same array in `database/seeders/RoleAndPermissionSeeder.php` as part of this plan's Task 1 instead** (check first: `grep system.audit-log.view database/seeders/RoleAndPermissionSeeder.php` — if it prints nothing, add the line).
- Do not widen the existing shared `AuditLog` TypeScript interface in `resources/js/shared/types/prd.type.ts` — `resources/js/pages/ops/revisions/index.tsx` relies on its current narrow `new_data` shape (`new_data.order_id`, `new_data.items.length`). Define a separate, page-local type for this feature instead.

---

### Task 1: `Admin\AuditLogController`, route, permission

**Files:**
- Create: `app/Http/Controllers/Admin/AuditLogController.php`
- Modify: `routes/web.php`
- Modify: `database/seeders/RoleAndPermissionSeeder.php` (only if the permission isn't already present — see Global Constraints)
- Test: `tests/Feature/AuditLogControllerTest.php`

**Interfaces:**
- Produces: route `admin.audit-logs.index` (GET only), consumed by Task 2's frontend.

- [ ] **Step 1: Check whether the permission already exists**

Run: `grep -n "system.audit-log.view" database/seeders/RoleAndPermissionSeeder.php`

If this prints a matching line, skip Step 2 below (it was already added by the Backup Data plan). If it prints nothing, do Step 2.

- [ ] **Step 2 (only if needed): Add the permission to the seeder catalog**

In `database/seeders/RoleAndPermissionSeeder.php`, find the `$permissions` array's closing line:

```php
            'purchase-orders.manage',
            'stock.manage',
        ];
```

Change it to:

```php
            'purchase-orders.manage',
            'stock.manage',
            'system.audit-log.view',
        ];
```

- [ ] **Step 3: Write the failing tests**

Create `tests/Feature/AuditLogControllerTest.php`:

```php
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
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `php artisan test tests/Feature/AuditLogControllerTest.php`
Expected: FAIL — route `admin.audit-logs.index` not defined (first two tests error; the third trivially passes already since nothing is defined yet — that's fine, it stays green through the whole plan).

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/Admin/AuditLogController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only by design — an audit trail that can be edited or deleted
 * through its own viewer defeats the point of being an audit trail. Only
 * `index` exists; do not add store/update/destroy here.
 */
class AuditLogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/audit-logs/index', [
            'auditLogs' => AuditLog::with('user')->latest()->get(),
        ]);
    }
}
```

- [ ] **Step 6: Add the route**

In `routes/web.php`, add the import (alphabetically, right after `use App\Http\Controllers\Admin\BackupController;` if present, otherwise right after `use App\Http\Controllers\Admin\DapurController;` — `AuditLogController` sorts between `BackupController` and `DapurController`):

```php
use App\Http\Controllers\Admin\AuditLogController;
```

Inside the `admin.` prefix group, add (anywhere in the group — e.g. right after the `backups` sub-group added by the Backup Data plan, or right after the `dapurs` resource if that plan hasn't run yet):

```php
        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:system.audit-log.view')
            ->name('audit-logs.index');
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/AuditLogControllerTest.php`
Expected: PASS (3 tests)

- [ ] **Step 8: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: same baseline as before.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/AuditLogController.php routes/web.php database/seeders/RoleAndPermissionSeeder.php tests/Feature/AuditLogControllerTest.php
git commit -m "Add read-only Admin\AuditLogController and system.audit-log.view permission"
```

---

### Task 2: Frontend — Audit Log list + detail dialog + sidebar entry

**Files:**
- Create: `resources/js/pages/admin/audit-logs/index.tsx`
- Modify: `resources/js/shared/constants/menu.config.ts`

**Interfaces:**
- Consumes: route `admin.audit-logs.index` (Task 1); `Dialog`/`DialogContent`/`DialogHeader`/`DialogTitle` from `@/shared/components/ui/dialog` (already exist).
- Produces: nothing consumed by later tasks — this is the last task in this plan.

- [ ] **Step 1: Create the page with a page-local type**

Create `resources/js/pages/admin/audit-logs/index.tsx`:

```tsx
import { Head } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';

// Page-local type: intentionally NOT the shared `AuditLog` type from
// prd.type.ts, which narrowly types `new_data` for the Ops revisions page
// (`new_data.order_id`, `new_data.items.length`). This viewer shows ANY
// audit_logs row regardless of action, so old_data/new_data must stay
// loosely typed here instead.
interface AuditLogRow {
  id: number;
  action: string;
  description: string | null;
  old_data: Record<string, unknown> | null;
  new_data: Record<string, unknown> | null;
  created_at: string;
  user: { id: number; name: string } | null;
}

const columns: TableColumn<AuditLogRow>[] = [
  { key: 'created_at', accessor: (log) => log.created_at },
  { key: 'user', accessor: (log) => log.user?.name },
  { key: 'action', accessor: (log) => log.action },
  { key: 'description', accessor: (log) => log.description },
];

export default function AdminAuditLogsIndex({ auditLogs }: { auditLogs: AuditLogRow[] }) {
  const [detailLog, setDetailLog] = useState<AuditLogRow | null>(null);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(
    auditLogs,
    columns,
  );

  return (
    <>
      <Head title="Log Aktivitas" />

      <PageHeader
        title="Log Aktivitas"
        description="Riwayat aktivitas sistem (revisi order, approve/reject, dll) — read-only, tidak bisa diedit atau dihapus dari sini."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Log Aktivitas' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari action atau deskripsi..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Waktu" columnKey="created_at" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="User" columnKey="user" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Action" columnKey="action" sort={sort} onSort={toggleSort} />
            <TableHead>Deskripsi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                {auditLogs.length === 0 ? 'Belum ada aktivitas tercatat.' : 'Tidak ada yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((log) => (
              <TableRow key={log.id} className="cursor-pointer" onClick={() => setDetailLog(log)}>
                <TableCell className="font-medium">{log.created_at}</TableCell>
                <TableCell>{log.user?.name ?? '-'}</TableCell>
                <TableCell>{log.action}</TableCell>
                <TableCell>{log.description ?? '-'}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />

      <Dialog open={detailLog !== null} onOpenChange={(open) => !open && setDetailLog(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Detail Log #{detailLog?.id}</DialogTitle>
          </DialogHeader>

          <div className="flex flex-col gap-4">
            <div>
              <p className="mb-1 text-xs font-semibold text-gray-500">old_data</p>
              <pre className="max-h-48 overflow-auto rounded-md bg-gray-50 p-3 text-xs dark:bg-gray-800">
                {JSON.stringify(detailLog?.old_data ?? null, null, 2)}
              </pre>
            </div>
            <div>
              <p className="mb-1 text-xs font-semibold text-gray-500">new_data</p>
              <pre className="max-h-48 overflow-auto rounded-md bg-gray-50 p-3 text-xs dark:bg-gray-800">
                {JSON.stringify(detailLog?.new_data ?? null, null, 2)}
              </pre>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}

AdminAuditLogsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
```

- [ ] **Step 2: Add the sidebar entry**

In `resources/js/shared/constants/menu.config.ts`, the `Admin` section's `children` array should currently look like one of these two shapes depending on whether the Backup Data plan already ran:

```ts
    children: [
      { label: 'Role & Permission', href: '/admin/roles', permission: 'roles.manage' },
      { label: 'Pengguna', href: '/admin/users', permission: 'users.manage' },
    ],
```

or

```ts
    children: [
      { label: 'Role & Permission', href: '/admin/roles', permission: 'roles.manage' },
      { label: 'Pengguna', href: '/admin/users', permission: 'users.manage' },
      { label: 'Backup Data', href: '/admin/backups', permission: 'system.backup.manage' },
    ],
```

Either way, add this line as the new last entry in that array:

```ts
      { label: 'Log Aktivitas', href: '/admin/audit-logs', permission: 'system.audit-log.view' },
```

- [ ] **Step 3: Typecheck**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: same 2 pre-existing unrelated errors — no new errors.

- [ ] **Step 4: Run the full backend suite once more**

Run: `php artisan test`
Expected: same baseline as before.

- [ ] **Step 5: Manually verify in the browser**

Log in as `admin@gmail.com` / `12341234`, open "Admin > Log Aktivitas" from the sidebar, confirm existing audit_logs rows show up (e.g. from earlier Order revisions in the seeded data), click a row, confirm the `old_data`/`new_data` JSON renders in the dialog.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/admin/audit-logs/index.tsx resources/js/shared/constants/menu.config.ts
git commit -m "Add read-only Audit Log viewer page and sidebar entry"
```

## Self-Review Notes

- **Spec coverage:** read-only list (time, user, action, description) ✓ Task 2; detail view of old_data/new_data JSON ✓ Task 2; admin-only permission ✓ Task 1; explicitly no create/update/destroy ✓ enforced by Global Constraints + Task 1 Step 3's third test.
- **Type consistency:** the page-local `AuditLogRow` type avoids touching the shared `AuditLog` interface, so `resources/js/pages/ops/revisions/index.tsx` (which depends on that shared type's narrow `new_data` shape) is untouched and unaffected.
- **Coordination with the Backup Data plan:** both plans edit the same `Admin` block in `menu.config.ts` and the same `$permissions` array in the seeder. Task 1 Step 1 and Task 2 Step 2 above both explicitly check current file state before editing, so this plan is safe to run whether the Backup Data plan ran first, after, or not at all.
