# Design: Tombol "Tambah Item" di Halaman Detail Order (Dapur)

**Status:** Approved
**Tanggal:** 2026-10-31

## Tujuan

Dapur saat ini hanya bisa menambah item saat membuat Order baru (`dapur/orders/create`). Setelah Order terkirim, tidak ada cara menambah item susulan (misal revisi sore/malam, atau tambahan darurat hari-H) tanpa membuat Order baru — yang salah secara desain karena satu Order = satu kombinasi (dapur_id, delivery_date).

## Alur

1. Tombol **"+ Tambah Item"** muncul di halaman `dapur/orders/show` (header tabel "Item Dipesan"), disembunyikan jika `order.status` adalah `CANCELLED` atau `COMPLETED`.
2. Klik tombol membuka `Dialog` (komponen yang sudah ada, `shared/components/ui/dialog.tsx`) berisi form multi-baris — pola identik dengan `dapur/orders/create.tsx`: per baris ada Combobox Item, Combobox Supplier (default dari `item.default_supplier_id`), Input Qty, Input Estimasi Harga, tombol hapus baris, dan tombol "+ Tambah Baris" untuk menambah baris baru di dalam modal yang sama.
3. Submit mengirim `POST dapur/orders/{order}/items` dengan payload `{ items: [{item_id, supplier_id, qty_ordered, estimated_price}, ...] }`.
4. Backend: method baru `OrderController::addItems(Request $request, Order $order, RevisionGateService $revisionGate)`:
   - `abort_unless($order->dapur_id === $request->user()->dapur_id, 403)` (pola scoping yang sudah konsisten di semua method lain di controller ini).
   - `abort_if(in_array($order->status, ['CANCELLED', 'COMPLETED'], true), 422, 'Tidak bisa menambah item ke Order berstatus '.$order->status.'.')` — guard baru, konsisten dengan guard yang sudah ada di `DeliveryNoteService::generateSuratJalan()`.
   - Validasi inline (pola yang sama seperti `submitStt`/`updateItem`): `items` array wajib, tiap baris `item_id` (exists:items,id), `supplier_id` (exists:suppliers,id), `qty_ordered` (numeric, min:0.01), `estimated_price` (numeric, min:0).
   - Panggil `$revisionGate->submitRevision($order, $validated['items'], $request->user())` — **mekanisme yang SAMA PERSIS** dengan submit Order pertama kali (Tier 1 auto-merge / Tier 2 pending approval Ops / Tier 3 emergency override, ditentukan otomatis dari jam saat ini vs `delivery_date` Order).
   - Redirect `back()->with('success', "{itemCount} item ditambahkan (tier: {tier}).")` — pesan menyebutkan tier hasil keputusan, supaya Dapur tahu apakah item langsung aktif atau masih menunggu approval Ops.
5. Route baru: `POST dapur/orders/{order}/items` bernama `dapur.orders.items.store`, di dalam group route `dapur.` yang sudah ada (sudah digate `permission:orders.manage`) — tidak ada permission baru.

## Validasi Desain

- **Tidak ada FormRequest baru** — ikut pola `submitStt`/`updateItem`/`destroyItem` yang sudah pakai `$request->validate()` inline di controller, bukan class terpisah.
- **Tidak ada permission baru** — endpoint ini murni operasi atas Order milik Dapur sendiri, sudah tercakup `orders.manage` + scoping `dapur_id`.
- **Reuse komponen frontend** — Dialog, Combobox, Input, Button semua sudah ada di `shared/components/ui`; tidak perlu komponen baru.
- **Konsisten dengan Tier 2 gate fix sebelumnya** — item yang masuk sebagai Tier 2 (pending approval) otomatis TIDAK ikut ke Surat Jalan manapun sampai Ops approve (sudah ditegakkan oleh `AuditLog::pendingOrderItemIds()` yang dibuat di sesi sebelumnya) — fitur ini tidak perlu logic tambahan untuk itu, cukup memanggil `submitRevision()` yang sudah benar.

## Testing

- Pest feature test: Dapur user menambah item ke Order miliknya sendiri saat Tier 1 (pagi) → item langsung `surat_jalan_id IS NULL` tapi TIDAK pending (bisa langsung masuk generate SJ berikutnya).
- Pest feature test: Dapur user menambah item saat Tier 2 (malam) → item tercatat `ORDER_REVISION_PENDING_APPROVAL` di `audit_logs`, dan (regresi dari fix sebelumnya) tidak ikut ke SJ sampai di-approve.
- Pest feature test: Dapur user TIDAK bisa menambah item ke Order dapur LAIN (403).
- Pest feature test: Dapur user TIDAK bisa menambah item ke Order berstatus `CANCELLED`/`COMPLETED` (422).

## Di Luar Scope

- Tidak membangun approval UI baru — halaman approval Ops (`ops/revisions`) yang sudah ada otomatis menampilkan item Tier 2 baru ini, tidak ada perubahan di sana.
- Tidak menambah kemampuan edit/hapus item yang SUDAH ada lewat modal ini — itu sudah ada di tabel utama (`updateItem`/`destroyItem`).
