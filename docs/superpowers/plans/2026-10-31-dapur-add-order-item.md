# Dapur Add-Item-To-Order Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "+ Tambah Item" button to the Dapur order detail page that lets Dapur add new item lines to an already-submitted Order, going through the exact same Tier 1/2/3 revision logic as the initial order submission.

**Architecture:** One new backend endpoint (`OrderController::addItems`) that validates input and delegates entirely to the existing `RevisionGateService::submitRevision()` — no new business logic. One new frontend `Dialog` on the existing `dapur/orders/show.tsx` page, reusing the exact item/supplier/qty/price line-form pattern already used on `dapur/orders/create.tsx`.

**Tech Stack:** Laravel 12, Inertia.js + React 19, Pest (feature tests), shadcn/Radix `Dialog` + `Combobox` (already in the codebase).

## Global Constraints

- Scoping: every method on `Dapur\OrderController` checks `$order->dapur_id === $request->user()->dapur_id` (403 otherwise) — this endpoint must follow the same pattern.
- No new permission — reuse `orders.manage` (already gates the whole `dapur.` route group).
- No new Form Request class — this controller uses inline `$request->validate()` for every mutation (`submitStt`, `updateItem`), not a class per action.
- Guard against mutating a terminal Order: block if `status` is `CANCELLED` or `COMPLETED` (422), mirroring `DeliveryNoteService::generateSuratJalan()`'s existing guard.

---

### Task 1: Backend endpoint — `OrderController::addItems`

**Files:**
- Modify: `app/Http/Controllers/Dapur/OrderController.php`
- Modify: `routes/web.php:31-41` (the `dapur.` route group)
- Test: `tests/Feature/DapurAddOrderItemTest.php`

**Interfaces:**
- Consumes: `App\Services\RevisionGateService::submitRevision(Order $order, array $itemsPayload, User $user, ?Carbon $now = null): array{tier: string, order_items: Collection}` (already exists, unchanged).
- Produces: route `dapur.orders.items.store` (`POST dapur/orders/{order}/items`), used by Task 2's frontend.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/DapurAddOrderItemTest.php`:

```php
<?php

use App\Models\User;
use App\Services\RevisionGateService;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;

it('adds a Tier 1 item to an existing Order without needing Ops approval', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $this->travelTo(Carbon::parse('2026-11-02 10:00:00', 'Asia/Jakarta'));

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.items.store', $order), [
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 5, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $order->refresh();
    expect($order->orderItems)->toHaveCount(1);
    expect((float) $order->orderItems->first()->qty_ordered)->toBe(5.0);
});

it('adds a Tier 2 item that lands in the Ops approval queue instead of being confirmed immediately', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $this->travelTo(Carbon::parse('2026-11-02 19:00:00', 'Asia/Jakarta'));

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.items.store', $order), [
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 3, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $pendingIds = \App\Models\AuditLog::pendingOrderItemIds($order->id);
    $order->refresh();
    expect($order->orderItems)->toHaveCount(1);
    expect($pendingIds)->toHaveCount(1);
});

it('refuses to add items to another dapur\'s Order', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $dapurA = makeDapur();
    $dapurB = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $dapurUserA = makeUserWithRole('dapur', ['dapur_id' => $dapurA->id]);
    $orderB = makeOrder($dapurB, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $response = $this->actingAs($dapurUserA)->post(route('dapur.orders.items.store', $orderB), [
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 3, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertForbidden();
});

it('refuses to add items to a CANCELLED or COMPLETED Order', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $dapurUser = makeUserWithRole('dapur', ['dapur_id' => $dapur->id]);
    $order = makeOrder($dapur, ['status' => 'CANCELLED', 'delivery_date' => '2030-01-15']);

    $response = $this->actingAs($dapurUser)->post(route('dapur.orders.items.store', $order), [
        'items' => [
            ['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 3, 'estimated_price' => 1000],
        ],
    ]);

    $response->assertStatus(422);
    expect($order->orderItems()->count())->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/DapurAddOrderItemTest.php`
Expected: FAIL — route `dapur.orders.items.store` does not exist yet (`RouteNotFoundException` or similar).

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the existing `dapur.` group (right after the `orders/{order}/stt` line, so around line 34), add:

```php
        Route::post('orders/{order}/items', [OrderController::class, 'addItems'])->name('orders.items.store');
```

So the group reads (unchanged lines omitted for brevity — insert the new line directly after the `orders.stt` route):

```php
    Route::prefix('dapur')->name('dapur.')->middleware('permission:orders.manage')->group(function () {
        Route::resource('orders', OrderController::class)
            ->only(['index', 'create', 'store', 'show']);
        Route::post('orders/{order}/stt', [OrderController::class, 'submitStt'])->name('orders.stt');
        Route::post('orders/{order}/items', [OrderController::class, 'addItems'])->name('orders.items.store');
        Route::patch('orders/{order}/items/{orderItem}', [OrderController::class, 'updateItem'])->name('orders.items.update');
        Route::delete('orders/{order}/items/{orderItem}', [OrderController::class, 'destroyItem'])->name('orders.items.destroy');
        Route::get('orders/{order}/surat-jalan/download', [OrderController::class, 'downloadSuratJalan'])
            ->name('orders.surat-jalan.download');
        Route::get('orders/{order}/tanda-terima/download', [OrderController::class, 'downloadTandaTerima'])
            ->name('orders.tanda-terima.download');
    });
```

- [ ] **Step 4: Add the `addItems` controller method**

In `app/Http/Controllers/Dapur/OrderController.php`, add this method right after `submitStt` (before `downloadSuratJalan`):

```php
    /**
     * Dapur adds new item lines to an Order that's already been submitted —
     * e.g. a same-day add-on or a late-evening revision. Goes through the
     * exact same Tier 1/2/3 cut-off logic as the initial submission
     * (RevisionGateService::submitRevision), not a separate code path.
     */
    public function addItems(Request $request, Order $order, RevisionGateService $revisionGate): RedirectResponse
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);
        abort_if(
            in_array($order->status, ['CANCELLED', 'COMPLETED'], true),
            422,
            "Tidak bisa menambah item ke Order berstatus {$order->status}."
        );

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'items.*.qty_ordered' => ['required', 'numeric', 'min:0.01'],
            'items.*.estimated_price' => ['required', 'numeric', 'min:0'],
        ]);

        $result = $revisionGate->submitRevision($order, $validated['items'], $request->user());

        return back()->with(
            'success',
            count($validated['items']) . " item ditambahkan (tier: {$result['tier']})."
        );
    }
```

`RevisionGateService` is already imported at the top of this file (used by `store()`), so no new `use` statement is needed.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/DapurAddOrderItemTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: same baseline as before this change (all passing except the pre-existing unrelated `Tests\Feature\ExampleTest` failure).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Dapur/OrderController.php routes/web.php tests/Feature/DapurAddOrderItemTest.php
git commit -m "Add endpoint for Dapur to add items to an existing Order via RevisionGateService"
```

---

### Task 2: Frontend — "+ Tambah Item" dialog on the order detail page

**Files:**
- Modify: `app/Http/Controllers/Dapur/OrderController.php` (`show()` method — needs to pass `items`/`suppliers` props)
- Modify: `resources/js/pages/dapur/orders/show.tsx`

**Interfaces:**
- Consumes: route `dapur.orders.items.store` (from Task 1); `Item`/`Supplier` types from `@/shared/types/prd.type` (already defined, used by `create.tsx`).
- Produces: nothing consumed by later tasks — this is the last task in this plan.

- [ ] **Step 1: Pass `items`/`suppliers` to the show page**

In `app/Http/Controllers/Dapur/OrderController.php`, `show()` currently reads:

```php
    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);

        return Inertia::render('dapur/orders/show', [
            'order' => $order->load([
                'orderItems.item',
                'suratJalans' => fn ($query) => $query->orderBy('sequence_number'),
            ]),
        ]);
    }
```

Change it to also pass the same `items`/`suppliers` props that `create()` already passes (copy that exact query):

```php
    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);

        return Inertia::render('dapur/orders/show', [
            'order' => $order->load([
                'orderItems.item',
                'suratJalans' => fn ($query) => $query->orderBy('sequence_number'),
            ]),
            'items' => Item::with(['defaultSupplier', 'satuan'])->where('is_active', true)->get(),
            'suppliers' => Supplier::all(),
        ]);
    }
```

`Item` and `Supplier` are already imported at the top of this file (used by `create()`).

- [ ] **Step 2: Verify no PHP errors**

Run: `php artisan test tests/Feature/DapurOrderShowTest.php`
Expected: PASS (this existing test asserts `assertOk()` on the show page — if the new props cause a query error, it will fail).

- [ ] **Step 3: Add the Dialog + line-form state to `show.tsx`**

In `resources/js/pages/dapur/orders/show.tsx`, update the imports at the top:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Combobox } from '@/shared/components/ui/combobox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { confirmDelete, errorAlert } from '@/shared/lib/alerts';
import { formatQty, formatRupiah } from '@/shared/lib/format';
import type { Item, Order, Supplier } from '@/shared/types/prd.type';
```

(This replaces the existing import block — adds `Plus`, `Dialog*`, `Combobox`, and the `Item`/`Supplier` types; everything else stays the same.)

- [ ] **Step 4: Add new-item-line state and the add-item dialog component**

Right after the `interface SttLine { ... }` block (before `export default function DapurOrdersShow`), add:

```tsx
interface NewItemLine {
  key: number;
  item_id: string;
  supplier_id: string;
  qty_ordered: string;
  estimated_price: string;
}

let nextNewItemKey = 1;

function emptyNewItemLine(): NewItemLine {
  return { key: nextNewItemKey++, item_id: '', supplier_id: '', qty_ordered: '', estimated_price: '' };
}
```

Change the function signature to accept the new props:

```tsx
export default function DapurOrdersShow({ order, items, suppliers }: { order: Order; items: Item[]; suppliers: Supplier[] }) {
```

Inside the component, after the existing `const [deletingId, setDeletingId] = useState<number | null>(null);` line, add:

```tsx
  const [addItemOpen, setAddItemOpen] = useState(false);
  const [newLines, setNewLines] = useState<NewItemLine[]>([emptyNewItemLine()]);
  const [addingItems, setAddingItems] = useState(false);

  const openAddItem = () => {
    setNewLines([emptyNewItemLine()]);
    setAddItemOpen(true);
  };

  const updateNewLine = (key: number, patch: Partial<NewItemLine>) => {
    setNewLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  };

  const onPickNewItem = (key: number, itemId: string) => {
    const item = items.find((i) => String(i.id) === itemId);
    updateNewLine(key, {
      item_id: itemId,
      supplier_id: item?.default_supplier_id ? String(item.default_supplier_id) : '',
      estimated_price: item?.base_selling_price ?? '',
    });
  };

  const addNewLine = () => setNewLines((prev) => [...prev, emptyNewItemLine()]);
  const removeNewLine = (key: number) => setNewLines((prev) => prev.filter((line) => line.key !== key));

  const submitAddItems = () => {
    const payload = newLines
      .filter((line) => line.item_id && line.supplier_id && line.qty_ordered)
      .map((line) => ({
        item_id: Number(line.item_id),
        supplier_id: Number(line.supplier_id),
        qty_ordered: Number(line.qty_ordered),
        estimated_price: Number(line.estimated_price || 0),
      }));

    if (payload.length === 0) return;

    setAddingItems(true);
    router.post(
      route('dapur.orders.items.store', order.id),
      { items: payload },
      {
        onSuccess: () => setAddItemOpen(false),
        onFinish: () => setAddingItems(false),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menambah item.'),
      },
    );
  };
```

- [ ] **Step 5: Add the "+ Tambah Item" button and render the dialog**

Find this block (the "Item Dipesan" section header):

```tsx
        <div>
          <h2 className="mb-2 text-sm font-semibold text-gray-700">Item Dipesan</h2>
          <Table>
```

Replace it with:

```tsx
        <div>
          <div className="mb-2 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-700">Item Dipesan</h2>
            {order.status !== 'CANCELLED' && order.status !== 'COMPLETED' && (
              <Button type="button" variant="outline" size="sm" onClick={openAddItem}>
                <Plus className="h-4 w-4" />
                Tambah Item
              </Button>
            )}
          </div>
          <Table>
```

Then, right before the final closing `</div>` of the whole component (i.e. right after the `<Link href={route('dapur.orders.index')} ...>` block and before the outer `</div>` that follows it), add the dialog:

```tsx
      <Dialog open={addItemOpen} onOpenChange={setAddItemOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Tambah Item ke Order</DialogTitle>
          </DialogHeader>

          <div className="flex flex-col gap-3">
            {newLines.map((line) => {
              const selectedItem = items.find((i) => String(i.id) === line.item_id);

              return (
                <div key={line.key} className="grid grid-cols-12 items-end gap-2 border-t border-gray-100 pt-3 first:border-t-0 first:pt-0">
                  <div className="col-span-4 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Item</label>
                    <Combobox
                      value={line.item_id}
                      onValueChange={(v) => onPickNewItem(line.key, v)}
                      placeholder="Ketik untuk cari item..."
                      emptyText="Item tidak ditemukan."
                      options={items.map((item) => ({ value: String(item.id), label: `${item.name} (${item.satuan?.name})` }))}
                    />
                  </div>

                  <div className="col-span-3 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Supplier</label>
                    <Combobox
                      value={line.supplier_id}
                      onValueChange={(v) => updateNewLine(line.key, { supplier_id: v })}
                      placeholder="Pilih supplier..."
                      emptyText="Supplier tidak ditemukan."
                      options={suppliers.map((supplier) => ({ value: String(supplier.id), label: supplier.name }))}
                    />
                  </div>

                  <div className="col-span-2 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Qty {selectedItem ? `(${selectedItem.satuan?.name})` : ''}</label>
                    <Input
                      type="number"
                      min="0.01"
                      step="0.01"
                      value={line.qty_ordered}
                      onChange={(e) => updateNewLine(line.key, { qty_ordered: e.target.value })}
                    />
                  </div>

                  <div className="col-span-2 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Estimasi Harga</label>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      value={line.estimated_price}
                      onChange={(e) => updateNewLine(line.key, { estimated_price: e.target.value })}
                    />
                  </div>

                  <div className="col-span-1 flex justify-end">
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => removeNewLine(line.key)}
                      disabled={newLines.length === 1}
                    >
                      <Trash2 className="h-4 w-4 text-red-500" />
                    </Button>
                  </div>
                </div>
              );
            })}

            <Button type="button" variant="outline" size="sm" onClick={addNewLine} className="self-start">
              <Plus className="h-4 w-4" />
              Tambah Baris
            </Button>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setAddItemOpen(false)}>
              Batal
            </Button>
            <Button type="button" onClick={submitAddItems} disabled={addingItems}>
              {addingItems ? 'Menyimpan...' : 'Simpan'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
```

- [ ] **Step 6: Typecheck**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: same 2 pre-existing unrelated errors in `pages/auth/register.tsx` and `pages/auth/reset-password.tsx` — no new errors.

- [ ] **Step 7: Run the backend test suite once more**

Run: `php artisan test`
Expected: same baseline as Task 1's Step 6.

- [ ] **Step 8: Manually verify in the browser**

Start the dev servers (`php artisan serve` + `npm run dev` if not already running), log in as `dapur1@bms.test` / `12341234`, open an existing Order's detail page, click "+ Tambah Item", add a line, submit, and confirm the success toast shows the tier and the new item appears in the table.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Dapur/OrderController.php resources/js/pages/dapur/orders/show.tsx
git commit -m "Add 'Tambah Item' dialog to the Dapur order detail page"
```

## Self-Review Notes

- **Spec coverage:** button visibility guard (CANCELLED/COMPLETED hidden) ✓ Task 2 Step 5; backend status guard ✓ Task 1 Step 4; scoping (403 cross-dapur) ✓ Task 1 Step 4 + tested; Tier 1/2/3 reuse (no new logic) ✓ Task 1 Step 4 delegates to `submitRevision`; success message shows tier ✓ Task 1 Step 4.
- **Out of scope confirmed:** no new approval UI (existing `ops/revisions` page already surfaces Tier 2 items via `AuditLog::pendingOrderItemIds` / `RevisionGateService::pendingApprovals()` — nothing to change there); no edit/delete changes to existing items (already handled by `updateItem`/`destroyItem`).
