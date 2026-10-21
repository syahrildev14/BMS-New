# Finding Registry — Sistem BMS Rebuild

> Penomoran **F-N adalah jangkar permanen** — tidak diubah/di-renumber tanpa konfirmasi Boss (sesuai protokol kerja). Status **LOCKED** = fakta hasil pembacaan kode atau keputusan default yang sudah final di blueprint. Status **OPEN** = menunggu keputusan Boss; begitu dikonfirmasi, statusnya berubah jadi LOCKED tapi nomornya tetap sama.
>
> Sumber: `BLUEPRINT_SISTEM.md` (§ = section dokumen itu) dan `BLUEPRINT_REBUILD_v2_LARAVEL_INERTIA.md` (disebut "Rebuild §").

---

## A. Arsitektur & Infrastruktur Lama (fakta)

| ID | Temuan | Sumber | Status |
|---|---|---|---|
| F-1 | Sistem lama dua repo terpisah: backend Laravel 12 REST API (JSON only) + frontend Next.js 16 App Router SPA konsumsi via Axios | §1 | LOCKED |
| F-2 | Backend: PHP ^8.2, Laravel ^12.0; package utama dompdf, phpword, predis (predis terpasang tapi tidak wajib dipakai) | §2 | LOCKED |
| F-3 | Deployment: Dockerfile `php:8.2-cli`, extension `pdo_pgsql` (indikasi target Postgres), port 10000 (pola Render.com), CMD `migrate+seed+serve` | §2 | LOCKED |
| F-4 | Testing lama: 34 Feature test API + 1 Unit test contoh; tidak ada Jobs/Events/Listeners/Notifications/Mail/Policies | §3.4–3.5 | LOCKED |
| F-5 | Cache, queue, session semua driver `database`, bukan Redis, meski `predis` terpasang | §7 | LOCKED |
| F-6 | CORS `allowed_origins: ['*']`, `supports_credentials: false` — konsisten dgn bearer token bukan cookie | §7 | LOCKED |
| F-7 | Mail driver `log`; tidak ada payment gateway/SMS/WA gateway/3rd-party API aktif | §7 | LOCKED |

## B. Auth & Permission Lama (fakta)

| ID | Temuan | Sumber | Status |
|---|---|---|---|
| F-8 | Auth custom bearer token: `Str::random(60)`, hash SHA-256 di `user_api_tokens`, expiry 8 jam — bukan Sanctum/Passport | §4.2 | LOCKED |
| F-9 | Login: `Hash::check` + rate limit manual 5x percobaan/300 detik per kombinasi email+IP | §4.2 | LOCKED |
| F-10 | Ganti password otomatis revoke SEMUA token aktif user (force re-login semua device) | §4.2 | LOCKED |
| F-11 | Token disimpan **plain di localStorage** sisi frontend — rentan XSS | §8.7 | LOCKED |
| F-12 | Permission custom: tabel `permissions`+`user_permissions`, 89 kode format `<modul>.<resource>.<aksi>`, `PermissionCatalog` sbg source of truth + legacy fallback (`master.view`/`master.manage` → expand otomatis) | §4.1, §3.4 | LOCKED |
| F-13 | Role hanya 2 level (`admin`/`superadmin`) + `role_label` bebas untuk tampilan | §4.1 | LOCKED |
| F-14 | Middleware permission per-route per-aksi (bukan di controller) — 1 resource CRUD = 4 grup middleware terpisah | §4.1, §6 | LOCKED |
| F-15 | Tidak ada `middleware.ts` di frontend — proteksi route hanya di client, backend tetap jadi lapisan keamanan sesungguhnya | §8.7 | LOCKED |
| F-16 | `PermissionBoundary.tsx` melakukan DOM manipulation via `MutationObserver` berbasis teks tombol/selector SVG icon — bukan kontrol keamanan sungguhan | §8.7 | LOCKED |
| F-17 | `lib/permissions.ts` frontend adalah duplikasi manual dari `PermissionCatalog` backend — berpotensi drift | §9.7 | LOCKED |

## C. Business Logic Transaksi Lama (fakta)

| ID | Temuan | Sumber | Status |
|---|---|---|---|
| F-18 | Activity log global middleware catat semua POST/PUT/PATCH/DELETE sukses (2xx); modul & target diekstrak dari mapping manual hardcoded path→label, bukan generik dari model | §4.3 | LOCKED |
| F-19 | Ada 2 mekanisme sinkronisasi stok gudang tidak konsisten: `WarehouseStockSynchronizer` (agregasi, tidak dipanggil dari route manapun) vs logic langsung di `WarehouseInboundController` (1:1 via FK) | §4.4, §9.3 | LOCKED |
| F-20 | Alur penjualan: Order Penawaran → Penjualan (item bisa diimpor dari Order Penawaran, item belum ditarik tampil sbg virtual row "pending") → Surat Jalan/Tanda Terima → Invoice Penjualan | §4.5 | LOCKED |
| F-21 | Pencarian Penjualan terkait Invoice pakai join by nama string (`OrderPenawaran.nama_pembeli = Sppg.nama_sppg`), bukan FK langsung | §4.5(5), §5 | LOCKED |
| F-22 | Invoice mewajibkan semua Penjualan terkait berstatus `selesai` + sudah ada TandaTerima di tanggal+SPPG sama; duplikasi invoice utk SPPG+tanggal sama dicegah | §4.5(5) | LOCKED |
| F-23 | Pengurangan stok terjadi SAAT invoice dibuat (bukan saat penjualan/surat jalan) — `deductStockForInvoice()` lock baris stok (`lockForUpdate`), kurangi FIFO by id, catat ke `invoice_penjualan_stock_movements` | §4.5(6) | LOCKED |
| F-24 | Update/delete invoice pakai pola reversal-then-reapply — restore stok dari `stock_movements` dulu sebelum re-deduct data baru | §4.5(7) | LOCKED |
| F-25 | Export invoice 2 jalur: PDF (dompdf, generate dari data) & DOCX (PhpWord `TemplateProcessor`, isi placeholder ke template upload user, clone-row tabel item) | §4.5(8), §4.6 | LOCKED |
| F-26 | Logo perusahaan diserve lewat endpoint API tanpa auth (`GET perusahaan/{id}/logo`) DAN diinject sbg base64 data URL langsung ke response JSON invoice | §4.5(9) | LOCKED |
| F-27 | Template surat menyimpan file DOCX mentah (bukan HTML) di disk public; per jenis dokumen bisa banyak template, 1 ditandai `is_default` (constraint dijaga manual di controller, bukan di DB) | §4.6 | LOCKED |
| F-28 | Dashboard/laporan di-cache per kombinasi filter tanggal (TTL ±5 menit), diinvalidasi TOTAL (`Cache::flush()`) tiap ada mutasi terkait — bukan cache per-tag | §4.7, §9.2 | LOCKED |
| F-29 | Query pencarian banyak modul pakai `whereRaw('LOWER(...) LIKE ?')` manual — berpotensi tidak portable penuh ke driver DB lain | §4.7 | LOCKED |
| F-30 | Tidak ada Form Request class, tidak ada Repository/Service/Action layer utk mayoritas modul — hampir semua logic langsung di controller (termasuk logic ratusan baris di `InvoicePenjualanController`) | §3.5, §9.5 | LOCKED |

## D. Skema Database Lama (fakta)

| ID | Temuan | Sumber | Status |
|---|---|---|---|
| F-31 | 58 file migration, banyak migration "tambal" (add column/FK belakangan) — skema berevolusi organik | §5 | LOCKED |
| F-32 | Banyak relasi kritis (SPPG↔Penjualan, Produk↔Stok) berbasis pencocokan string (nama), bukan FK murni — warisan skema field-bebas-teks yang ditambal FK belakangan | §5, §9.4 | LOCKED |
| F-33 | Tabel `users` & `perusahaan` pakai `$timestamps = false` | §3.1 | LOCKED |
| F-34 | Ambiguitas target DB: `.env.example` default mysql, Dockerfile install `pdo_pgsql` — belum dikunci (terkait **F-52**, OPEN) | §2, §7, §9.2 | LOCKED (fakta) |
| F-35 | Storage `template-surat` & logo perusahaan kosong di working copy — data upload aktual kemungkinan hanya ada di environment produksi | §9.10 | LOCKED |

## E. Frontend Lama (fakta)

| ID | Temuan | Sumber | Status |
|---|---|---|---|
| F-36 | Next.js 16.2.2, React 19.2.4, TypeScript ^5, App Router; Tailwind v4; shadcn/ui (style radix-nova, baseColor neutral, icon lucide) | §8.1 | LOCKED |
| F-37 | Dua library data-fetching dipakai bersamaan: React Query v5 (dominan) DAN SWR v2 (dashboard + `useFetch.ts`) — digantikan **F-51** | §8.1, §9.8 | LOCKED (fakta, solusi lihat F-51) |
| F-38 | Dua jalur pembuatan PDF berbeda: jspdf (client-side) vs dompdf (server-side), tergantung halaman | §8.1 | LOCKED |
| F-39 | Struktur routing modul tidak konsisten: `transaksiPembelian` (camelCase) vs `transaksi-penjualan` (kebab-case) — digantikan **F-50** | §8.2 | LOCKED (fakta, solusi lihat F-50) |
| F-40 | Halaman `logistik/jadwal-pengiriman` ada di frontend TANPA padanan endpoint backend (terkait **F-56**, OPEN) | §8.2 | LOCKED (fakta) |
| F-41 | Folder `types/` kosong — tidak ada sentralisasi tipe domain | §8.6 | LOCKED |
| F-42 | Hanya 1 provider (`QueryProvider`) dipasang di `admin/layout.tsx`, bukan root layout; tidak ada AuthProvider/ThemeProvider | §8.6 | LOCKED |

## F. Keputusan Arsitektur Rebuild (rekomendasi default, LOCKED sampai ada OPEN terkait dikonfirmasi)

| ID | Keputusan | Sumber | Status |
|---|---|---|---|
| F-43 | Auth dihapus total dari custom token → session guard bawaan Laravel (`Auth::attempt`, cookie same-origin), rate limit pakai `RateLimiter` facade bawaan | Rebuild §4 | LOCKED — final tunggu **F-57** |
| F-44 | Permission custom dipertahankan penuh (89 kode), dibungkus `Gate::before()` agar bisa `$this->authorize()`/`@can`, di-share ke Inertia via `HandleInertiaRequests::share()` | Rebuild §5 | LOCKED |
| F-45 | Cache pindah ke Redis dengan cache tags per modul (dashboard, laporan-stok, laporan-laba-rugi, stok), gantikan `Cache::flush()` total | Rebuild §6 | LOCKED — final tunggu **F-53** |
| F-46 | Activity log pindah dari mapping hardcoded ke Model Observer generik + trait `LogsActivity` | Rebuild §7 | LOCKED |
| F-47 | Business logic kompleks (Invoice, sinkronisasi stok) diekstrak jadi Action class (`app/Actions/`) — port logic apa adanya dulu, refactor bertahap | Rebuild §8 | LOCKED |
| F-48 | Migrasi relasi string → FK id-based, 2 tahap: tambah kolom FK + backfill matching, lalu ganti query ke JOIN FK setelah validasi 100% | Rebuild §9 | LOCKED — scope final tunggu **F-54** |
| F-49 | Scaffold awal pakai Laravel 12 React Starter Kit resmi (React 19 + TypeScript + Tailwind + shadcn/ui) | Rebuild §2 | LOCKED |
| F-50 | Ziggy dipakai utk `route()` helper JS — menutup F-39 karena satu sumber kebenaran nama route Laravel | Rebuild §2, §10 | LOCKED |
| F-51 | Frontend data-fetching: Inertia props + `useForm` utk seluruh CRUD standar; React Query dipertahankan HANYA utk widget polling dashboard — menutup F-37 | Rebuild §10 | LOCKED |

## G. Keputusan Terbuka (OPEN — menunggu konfirmasi Boss)

| ID | Keputusan yang perlu diambil | Terkait | Sumber | Status |
|---|---|---|---|---|
| F-52 | Database target final — MySQL atau PostgreSQL? | F-3, F-34 | Rebuild §14.1 | **OPEN — BLOCKING** |
| F-53 | Redis tersedia di environment hosting target? | F-45 | Rebuild §14.2 | **OPEN** |
| F-54 | Scope migrasi FK (F-48) — sekaligus di awal atau bertahap per modul mengikuti fasing? | F-48 | Rebuild §14.3 | **OPEN** |
| F-55 | PHP version — tetap 8.2 atau naik ke 8.3/8.4? | F-2 | Rebuild §14.4 | **OPEN** |
| F-56 | Halaman `logistik/jadwal-pengiriman` — dibangun sungguhan atau di-drop dari scope? | F-40 | Rebuild §14.5 | **OPEN** |
| F-57 | Auth — setuju migrasi total ke session-based, atau tetap butuh token-based karena rencana API eksternal/mobile app? | F-43 | Rebuild §14.6 | **OPEN** |

## H. Progress Repo Baru (`Z:\WEB\bms-new`) — Laporan Progress #1

| ID | Temuan | Status |
|---|---|---|
| F-58 | Repo baru sudah pakai Laravel 12 + Inertia + React 19 (TypeScript), scaffold dari `laravel/react-starter-kit` resmi — menutup **F-49** | LOCKED |
| F-59 | Stack frontend aktual: shadcn/Radix + Tailwind v4, **TanStack Query/Table** utk state server, react-hook-form + zod utk form | LOCKED (fakta) — cakupan pemakaian TanStack Query perlu klarifikasi, lihat **F-71** |
| F-60 | Database lokal SQLite (`DB_CONNECTION=sqlite`), 27 migrasi, semua sudah jalan (batch 1–4), tidak ada pending | LOCKED (fakta) — **bukan** jawaban final F-52, SQLite murni utk dev lokal |
| F-61 | 27 migrasi (vs 58 di sistem lama, F-31) — indikasi skema sudah dikonsolidasi sejak awal, bukan "migration tambal" | LOCKED |
| F-62 | 30 Controller, 5 grup Model (Master, Pembelian, Penjualan, Warehouse, User) — grup Keuangan & Laporan belum muncul di struktur model | LOCKED |
| F-63 | `routes/web.php` DAN `routes/api.php` sama-sama ada, dipecah rapi per modul | LOCKED (fakta) — tujuan `routes/api.php` di arsitektur Inertia monolith perlu klarifikasi, lihat **F-72** |
| F-64 | Session/Cache/Queue driver masih `database` — **F-45** (cache tags Redis) & **F-53** belum diimplementasi/dijawab | LOCKED |
| F-65 | Mail driver `log`, belum ada SMTP nyata — konsisten dgn sistem lama (F-7), bukan regresi | LOCKED |
| F-66 | `APP_ENV=local`, `APP_DEBUG=true` — sesuai tahap development, wajib diganti sebelum deploy produksi | LOCKED |
| F-67 | Log Laravel 2 MB belum pernah dirotasi — direkomendasikan `LOG_CHANNEL=daily` (sudah diidentifikasi sendiri di laporan) | LOCKED |
| F-68 | Git repository sudah diinisialisasi dan di-push ke GitHub | **RESOLVED** — dikonfirmasi Boss, menutup risiko tinggi sebelumnya |
| F-69 | Modul yang sudah tercermin di struktur: Master Data (Barang, Supplier, Gudang, Satuan, Armada, SPPG, Mitra, Karyawan, Bank Account, Template Surat), Pembelian (Order Penawaran, Daftar Pembelanjaan, Daftar Pembelanjaan Supplier), Warehouse (Inbound, Stok Gudang, Retur Rusak), Penjualan (Penjualan, Surat Jalan, Tanda Terima, Invoice) | LOCKED |
| F-70 | Modul yang BELUM tercermin: **Perusahaan**, **Wilayah** (bagian Master Data lama), Keuangan & Akuntansi, Laporan & Analisa, Dashboard, Activity Log, User Management/Permission (89 kode) | LOCKED (fakta) — Perusahaan & Wilayah dikonfirmasi TIDAK ADA sama sekali (grep bersih), lihat **F-74** dst |
| F-71 | Cakupan pemakaian TanStack Query — dipakai luas utk semua CRUD, atau terbatas sesuai rencana Rebuild §10 (React Query hanya utk widget polling dashboard, CRUD standar pakai Inertia props+`useForm`)? | **RESOLVED (fakta)** — dipakai luas utk SEMUA CRUD via REST API, lihat **F-79**. Keputusan dipertahankan/tidak ada di **F-82** |
| F-72 | Tujuan `routes/api.php` di arsitektur baru — direncanakan API eksternal/mobile beneran, atau sisa scaffold starter kit? | **RESOLVED** — aktif dipakai, bukan boilerplate. Detail di **F-79** |
| F-73 | Status modul yang belum tercermin (F-70) — belum dibangun sesuai fasing PRD §10 (wajar di tahap ini), atau memang di luar scope/direstrukturisasi? | **SUPERSEDED oleh F-80** — verifikasi Perusahaan membuka temuan lebih besar: Invoice/Surat Jalan ternyata bukan tabel tersendiri (lihat F-74) |

## I. Temuan Kritis dari Verifikasi Langkah 2 & 4 (Laporan Progress #2)

| ID | Temuan | Status |
|---|---|---|
| F-74 | Tabel `invoice_penjualan` dan `surat_jalan` sebagai entitas tersendiri **TIDAK PERNAH DIBUAT** di bms-new — 27 migration tidak satupun membuatnya. Invoice & Surat Jalan saat ini murni **view/preview turunan** dari `Penjualan::load(...)` (relasi ke listOrderPenawaran.sppg, gudang, details.barang.satuan, tandaTerima), dibungkus API Resource — bukan entitas tersimpan dengan state sendiri | LOCKED (fakta) |
| F-75 | `downloadPdf()` di `InvoiceController` & `SuratJalanController` masih stub ("Generate PDF akan dibuat pada tahap berikutnya") — belum ada generate PDF | LOCKED |
| F-76 | Konsekuensi teknis F-74: desain lama (F-22–F-25 — `status_pembayaran`, `stock_deducted_at`, cegah duplikat per SPPG+tanggal, pola reversal-then-reapply, `invoice_penjualan_stock_movements`) **tidak bisa diimplementasikan** selama Invoice/Surat Jalan hanya view — butuh state persisten sendiri yang independen dari Penjualan | LOCKED (analisis teknis) |
| F-77 | `karyawans.role_id` migration pakai `->constrained('roles')`, tapi tabel `roles` **tidak pernah dibuat** di migration manapun — FK menggantung (dangling). Lolos migrate karena SQLite tidak menegakkan FK constraint secara default. **Akan GAGAL migrate kalau pindah ke MySQL/PostgreSQL** (terkait F-52) | LOCKED — **bug, perlu diperbaiki** |
| F-78 | Model `Role` (`app/Models/Role.php`) baru berisi `nama_role` + relasi `karyawans()` — bukan sistem permission granular. Tidak ada model/tabel Permission, tidak ada pivot role-permission. Satu-satunya jejak rencana: komentar TODO di `menu.config.ts` baris 20. **Sistem permission (F-44) belum dibangun sama sekali** — saat ini tidak ada otorisasi aktif di modul manapun | LOCKED |
| F-79 | `routes/api.php` dikonfirmasi **aktif dipakai** (menutup F-72). Arsitektur aktual: Inertia untuk render halaman shell, **REST API (`/api/*`) untuk SEMUA operasi CRUD** via axios + TanStack Query (`axios.ts` baseURL `/api`, komentar eksplisit "SEMUA request HTTP wajib lewat sini") — pola established, bukan boilerplate. **Menyimpang dari F-51** (rencana: Inertia props+`useForm` utk CRUD) | LOCKED (fakta) — keputusan lanjut di **F-82** |
| F-80 | Invoice/Surat Jalan/Tanda Terima — jadi **tabel persisten sendiri**, sesuai skema lama (F-20–F-27), bukan view turunan | **RESOLVED** — keputusan Boss. Detail konsekuensi di **F-87** |
| F-81 | Sistem otorisasi final | **RESOLVED** — keputusan Boss: adopsi konsep RBAC sistem TEMPO + tambahan RBAC CRUD. Detail di **F-84**, **F-85** |
| F-82 | Pola data-fetching final | **RESOLVED** — keputusan Boss: **pertahankan** hybrid Inertia(halaman)+REST API(CRUD via axios/TanStack Query). Closes juga **F-51** (revisi) |
| F-83 | Perbaiki dangling FK `roles` (F-77) | **RESOLVED** — dikerjakan sebagai bagian RBAC Fase A (skema `roles`), lihat **F-89** |

## J. Keputusan Boss (Laporan Progress #2 — Lanjutan)

| ID | Temuan/Keputusan | Status |
|---|---|---|
| F-84 | **RBAC final: adopsi arsitektur sistem TEMPO** — tabel `roles` + `permissions` + pivot `role_permissions`, **1 role per user** (`users.role_id`), retire total pendekatan direct user-permission (`user_permissions` pivot ala BMS lama, F-12) dan retire role enum (`admin`/`superadmin`). Role bisa jadi role sistem (`is_system=true`, mis. Superadmin) ATAU role custom buatan user. Satu sumber kebenaran izin — tidak ada enum & RBAC hidup berdampingan (pelajaran dari TEMPO) | LOCKED — keputusan Boss |
| F-85 | Tambahan scope BMS (beda dari TEMPO): **RBAC CRUD** — UI manajemen penuh (create/edit/delete role, assign/lepas permission ke role, assign role ke user), bukan cuma backend enforcement | LOCKED — keputusan Boss |
| F-86 | Granularitas nama permission — **format 3 level BMS dipertahankan** (`<modul>.<resource>.<aksi>`, sesuai katalog 89 kode lama, F-12), bukan format 2 level TEMPO | **RESOLVED** — keputusan Boss |
| F-87 | Konsekuensi F-80: Surat Jalan, Tanda Terima, Invoice Penjualan harus dibangun sebagai tabel + items + business logic lengkap sesuai skema lama (F-20–F-27) — deduksi stok, pola reversal-then-reapply, `invoice_penjualan_stock_movements`, cegah duplikat per SPPG+tanggal. ~~`perusahaan_id` di keempat titik~~ **[TIDAK BERLAKU LAGI — lihat F-121]**. **Scope kerja jauh lebih besar** dari sekadar Master Data — perlu dipecah jadi beberapa prompt bertahap (pola Fase ala TEMPO) | LOCKED |
| F-88 | Pola data-fetching REST API+axios+TanStack Query **dikonfirmasi final** — bukan lagi rencana migrasi ke Inertia props murni | LOCKED — keputusan Boss |
| F-89 | Tabel `roles` (F-77 dangling FK) dibangun sebagai bagian RBAC Fase A (F-84) — bukan tambalan terpisah | LOCKED |

---

## K. RBAC Fase B — Hasil & Temuan Kritis (Laporan Progress #3)

| ID | Temuan | Status |
|---|---|---|
| F-90 | RBAC Fase B selesai — migration `jabatans`, `permissions`, `roles`, `role_permissions`, `add_role_id_to_users`, `repoint_role_id_to_jabatan_id_on_karyawans` (6 migration baru, semua jalan sukses). Model `Role` lama (`app/Models/Master/Role.php`, semantik "nama_role") dihapus total, diganti `app/Models/Role.php` (semantik RBAC baru) — tidak ada sisa referensi (diverifikasi grep) | LOCKED |
| F-91 | `karyawans.role_id` (F-77) dikonfirmasi **bukan** konsep yang sama dengan RBAC role — direname jadi `jabatan_id` → tabel `jabatans` baru (job title/posisi, bukan akses sistem). `users.role_id` tetap terpisah sebagai RBAC role sesungguhnya. Menutup **F-77** & **F-89** tepat sesuai kekhawatiran di Prompt 04 §3. Kolom lama `jabatan_karyawan` (teks bebas redundan) di-retire | LOCKED |
| F-92 | `RbacSeeder.php` — 77 kode permission (daftar hasil Fase A, mencakup modul yang SUDAH ada: Master Data termasuk Perusahaan/Wilayah, Pembelian, Warehouse, Penjualan — belum termasuk Keuangan/Laporan/Dashboard/Activity Log). Role sistem `Superadmin` (`is_system=true`) dibuat dengan semua 77 permission, auto-assign ke user tanpa role | LOCKED |
| F-93 | Verifikasi fungsional RBAC: `$user->hasPermission('master.satuan.view')` → true; kode tidak terdaftar → false. DB state: roles=1, permissions=77, role_permissions=77, users 4/4 punya role_id | LOCKED |
| F-94 | 🔴 **[TEMUAN KRITIS]** `App\Models\Karyawan`, `Armada`, `BankRekening`, `TemplateSurat` — namespace class TIDAK COCOK lokasi file fisik, TIDAK TERDAFTAR di `autoload_classmap.php`. Akibatnya: **tidak pernah bisa di-resolve PHP sejak awal dibangun** — bukan rapuh, benar-benar "Class not found" kalau dipanggil. **Merevisi F-69** — modul-modul ini secara fisik ada di struktur, tapi TIDAK FUNGSIONAL sejak dibangun | LOCKED — **butuh tindak lanjut segera** |
| F-95 | `Karyawan` sudah diperbaiki (namespace → `App\Models\Master`) — diverifikasi fungsional penuh via CRUD test. Dua bug independen lain ikut diperbaiki agar bisa dites: (1) nama kolom salah di Request/Controller/Resource (`nomor_telepon`/`jabatan` vs kolom asli `no_telepon_karyawan`/`jabatan_karyawan`), (2) mismatch enum status (`nonaktif` di validasi vs `non_aktif` di DB) | LOCKED |
| F-96 | `Armada`, `BankRekening`, `TemplateSurat` **sengaja belum diperbaiki** (di luar scope RBAC) — saat ini masih "Class not found" kalau dipanggil. **Risiko naik**: Surat Jalan (F-87, Stream 1) di skema lama punya FK ke `armada_id` — kalau Stream 1 dikerjakan sebelum ini diperbaiki, langsung kena bug yang sama | LOCKED — **prioritas tinggi sebelum Stream 1 lanjut** |
| F-97 | Modul `Jabatan` baru sebatas tabel + model — belum ada controller/route/Inertia page (CRUD UI), beda dari Perusahaan/Wilayah yang sudah modul penuh | **OPEN** — urgensi rendah |
| F-98 | RBAC Fase C (enforcement) | **RESOLVED** — dikonfirmasi sudah selesai dikerjakan (laporan awal tidak sempat di-paste ke Jarvis), diverifikasi ulang lewat audit terpisah, lihat **F-105** |

---

## L. Audit & Perbaikan Namespace/Autoload — Hasil (Laporan Progress #4)

| ID | Temuan | Status |
|---|---|---|
| F-99 | Audit menyeluruh (30/30 model, diverifikasi via `class_exists()` nyata) — hanya 3 model bermasalah (Armada, BankRekening, TemplateSurat), tidak ada model baru yang ketahuan. 27 model lain aman | LOCKED — menutup **F-96**, tidak ada bug sistemik lebih luas |
| F-100 | Armada, BankRekening, TemplateSurat diperbaiki — namespace → `App\Models\Master` (ikut mayoritas sibling 10/13 file di folder sama, bukan pindah file). Terverifikasi fungsional penuh via CRUD test (create/list/update/search/delete) | LOCKED |
| F-101 | Bug tersembunyi tambahan ditemukan & diperbaiki: (1) Armada — mismatch kolom `nomor_polisi` vs `no_polisi`; (2) BankRekening — mismatch kolom `nomor_rekening`/`atas_nama` vs `no_rekening`/`atas_nama_rekening`; (3) BankRekening — migration `down()` salah nama tabel (`bank_accounts` vs `bank_rekenings`, tidak berdampak state DB); (4) BankRekening — bug routing: `UpdateBankRekeningRequest` pakai `$this->bankRekening` (camelCase) utk `ignore()`, padahal parameter route resource hyphenated (`bank-rekening`) jadi `{bank_rekening}` (snake_case) — `ignore()` selalu null, update ditolak salah "sudah digunakan". Diperbaiki ke `$this->route('bank_rekening')`, direproduksi dulu sebelum fix | LOCKED |
| F-102 | TemplateSurat — Request mewajibkan `isi_template` & `status`, kolom ini TIDAK PERNAH ada di migration. Field dihapus dari Request/Resource. **Catatan**: kemiripan nama dengan `html_template` di sistem lama (F-27, migrasi awal sebelum diganti `file_path`/`file_name`) indikasi sisa desain lama. **Fungsi inti TemplateSurat (upload & simpan file DOCX, F-27) belum dikonfirmasi berjalan** — perbaikan ini cuma menghilangkan field tidak valid | LOCKED (fakta) — perlu verifikasi terpisah, lihat **F-104** |
| F-103 | Cross-check pola `->ignore($this->camelCase)` di semua `UpdateXRequest` — hanya BankRekening bermasalah (satu-satunya resource hyphenated dgn pola ini). Bug terisolasi, bukan pola sistemik | LOCKED |
| F-104 | TemplateSurat — perlu diverifikasi terpisah apakah upload & penyimpanan file DOCX (fungsi inti F-27) sudah benar-benar berfungsi, atau baru sebatas "tidak lagi error" tanpa fitur upload nyata | **OPEN** — urgensi rendah, bisa digabung F-97 |

---

## M. Audit RBAC Lanjutan — Gap di Luar Fase C (Laporan Progress #5, `LAPORAN_AUDIT_RBAC.md`)

| ID | Temuan | Status |
|---|---|---|
| F-105 | RBAC Fase C dikonfirmasi **sudah selesai** (baru diketahui lewat audit terpisah, bukan lewat laporan langsung ke Jarvis) — middleware `can:<kode>` lengkap di semua `routes/api/*.php`, seluruh `authorize()` Form Request pakai `hasPermission()`, `Gate::before()` default-deny benar (return `null`, bukan `false`), permission+`isSystemRole` di-share ke Inertia, menu sidebar difilter | LOCKED — menutup **F-98** |
| F-106 | UI tidak menegakkan permission granular pada create/update/delete — tombol Tambah/Edit/Hapus tampil ke semua user regardless permission, baru ditolak backend dgn toast generik (kemungkinan besar sumber kesan "sistem ngebug"). Diperbaiki: hook `usePermissions()` baru, diterapkan ke 11 modul/34 file (Master: satuan, barang, supplier, gudang, armada, sppg, mitra, wilayah, jabatan; RBAC: role, user). Guard bisnis lama dipertahankan. `tsc --noEmit` 0 error baru. **Modul `bank-rekening`, `template-surat`, dan SELURUH `pembelian/*`, `warehouse/*`, `penjualan/*` belum punya halaman frontend sama sekali** (baru backend+API) — tidak ada yang perlu digate di sana untuk saat ini | LOCKED |
| F-107 | Halaman web (`routes/web/*.php`) tidak digate permission sama sekali (cuma middleware `auth`), beda dgn API — user tanpa akses tetap bisa buka shell halaman via URL langsung. Diperbaiki: middleware `can:<modul>.<resource>.view` ditambahkan ke seluruh route web, termasuk pemetaan slug≠kode permission (`order-penawaran`→`pembelian.list-order-penawaran.view`, `retur`→`warehouse.retur-rusak.view`). `stok-gudang` sengaja tanpa gate (belum ada kode permission utk resource ini). Diverifikasi via `route:list -v` | LOCKED |
| F-108 | 🔴 **TEMUAN KRITIS** — celah eskalasi privilege lewat `/register` publik. `RbacSeeder` lama auto-promote SEMUA user dgn `role_id` null jadi **Superadmin** setiap seeder dijalankan ulang (rutin saat dev, F-92) — siapa pun bisa daftar sendiri lewat `/register`, lalu otomatis dapat akses Superadmin penuh begitu seeder berikutnya jalan. Diperbaiki: role fallback baru **"Belum Ditugaskan"** (`is_system=true`, `is_default=true`, 0 permission) gantikan Superadmin sbg default; `Superadmin.is_default=false`; `RegisteredUserController::store()` eksplisit assign role saat registrasi; `RoleController` dijaga cuma 1 role boleh `is_default=true`; sudah `db:seed` di dev, diverifikasi user existing lain TIDAK ikut ter-reassign | LOCKED — **celah sudah tertutup** |
| F-109 | Komentar TODO basi di `UserController`/`RoleController` ("TODO RBAC: ...") padahal enforcement sudah jalan (F-105) — berisiko menyesatkan developer lain. Diperbaiki: komentar diarahkan ke lokasi enforcement sesungguhnya | LOCKED |
| F-110 | `/register` publik | **RESOLVED** — keputusan Boss: **matikan total** |
| F-111 | Konfirmasi eksplisit dari Boss: Surat Jalan/Tanda Terima/Invoice Penjualan sebagai tabel sungguhan (F-87) **belum dikerjakan sama sekali** — bukan ada progres tersembunyi di sesi lain seperti kasus F-98/F-105 sebelumnya | LOCKED |

---

## N. Update Terkini — Register, Audit Pembelian/Warehouse, Investigasi Penjualan Fase A (Laporan Progress #6)

| ID | Temuan | Status |
|---|---|---|
| F-112 | `/register` dimatikan — route dihapus, `RegisteredUserController` dihapus (dicek tidak dipakai jalur lain), `register.tsx` & `RegistrationTest.php` ikut dihapus. Verifikasi: GET/POST `/register` → 404, `route:list` bersih, `tsc` 0 error baru | LOCKED — menutup **F-110** |
| F-113 | `DaftarPembelanjaanController::update()` tidak menegakkan aturan "1 List Order Penawaran = 1 Daftar Pembelanjaan" (sudah ditegakkan `store()`) — reassign bisa melanggar diam-diam. Diperbaiki dgn re-check `exists()` sama seperti `store()`. Dibuktikan: sebelum fix 200 (lolos), sesudah fix 422. Pola bug F-101 dicek eksplisit, **tidak ditemukan** di modul ini. FK dicek bersih, tidak ada sisa string-matching | LOCKED |
| F-114 | `DaftarPembelanjaanSupplier::store()` tidak idempoten — dipanggil 2x input sama → data dobel (2→4 record, dibuktikan). Kolom `status` (draft/proses/selesai) didefinisikan tapi tidak pernah ditransisikan kode manapun | **OPEN** — tidak blocking |
| F-115 | Retur/Rusak bisa dibuat sebelum Inbound 'selesai' — `ReturRusakController::store()` tidak cek `inbound.status`. Dibuktikan: POST terhadap inbound draft → berhasil (201) | **OPEN** — tidak blocking |
| F-116 | Tidak ada batas atas qty Retur/Rusak — tidak ada validasi terhadap `qty_diterima`/stok tersedia. Dibuktikan: qty retur 999 pada stok 15 → stok jadi **-984**, tersimpan tanpa error | **OPEN** — tidak blocking |
| F-117 | Mekanisme sinkronisasi Inbound→Stok bersih — hanya 1 mekanisme (`InboundController::update()`), konsisten, benar dipanggil dari route asli. Tidak ada duplikasi ala F-19 | LOCKED (fakta positif) |
| F-118 | 🔴 **KOREKSI** — Tanda Terima TERNYATA sudah tabel persisten + CRUD penuh (`tanda_terimas`, `penjualan_id` unique 1:1, `TandaTerimaController` lengkap, permission `penjualan.tanda-terima.*` sudah ada di `RbacSeeder`). Premis **F-111** ("Penjualan belum dikerjakan sama sekali") tidak sepenuhnya akurat — pola sama seperti F-98/F-105 terulang. Surat Jalan & Invoice dikonfirmasi masih view turunan (F-74) — bagian itu akurat | LOCKED (fakta, revisi F-111) |
| F-119 | 🔴 **[OPEN — BLOCKING Fase C]** Konflik: `PenjualanController::update()` SUDAH mengurangi `StokGudang` saat Penjualan 'selesai' — bertentangan dgn F-23 (deduksi seharusnya saat Invoice dibuat). Kalau F-23 dipertahankan literal, kode Penjualan yang sudah jalan harus diubah behaviornya | **OPEN** |
| F-120 | 🔴 **[OPEN — BLOCKING Fase C]** FIFO (F-23) tidak representable dgn model stok saat ini — `StokGudang` agregat per barang+gudang dgn `harga_rata_rata` (weighted average), bukan per-lot. FIFO sungguhan butuh tabel lot baru, scope tambahan | **OPEN** |
| F-121 | 🔴 **[OPEN — KONTRADIKSI]** `perusahaan_id` (F-87) tidak bisa dipasang — modul Perusahaan sudah **DIHAPUS TOTAL** (migration `drop_perusahaans_table`, tercatat "atas instruksi Boss"), tidak ada referensi tersisa. **F-87 (LOCKED) mengasumsikan modul itu masih ada** | **OPEN** — kontradiksi dgn keputusan terkunci, perlu klarifikasi Boss langsung |
| F-122 | `driver_id`/`accounting_id` tidak ada tempat berlabuh — skema lama mereferensikan karyawan (employee registry). Laporan sesi ini mengklaim modul itu "sudah dihapus total di bms-new". **Klaim ini bertentangan dengan F-95** (Karyawan CRUD dikonfirmasi berfungsi penuh sebelumnya) — perlu diverifikasi mana yang akurat | **OPEN** — perlu klarifikasi |
| F-123 | 🔴 **[OPEN — PALING BLOCKING]** Arsitektur inti belum ditentukan — rantai 1 Penjualan:1 Surat Jalan:1 Tanda Terima:1 Invoice (asumsi kode existing) vs agregasi Invoice per SPPG+tanggal dari banyak Penjualan (tersirat F-22 lama). Menentukan bentuk skema `invoices` (FK tunggal vs pivot many-to-many) | **OPEN** |
| F-124 | **[Catatan proses]** Pola berulang 3x: status pekerjaan yang dikonfirmasi lewat ingatan Boss (bukan verifikasi langsung ke kode) beberapa kali tidak akurat (RBAC Fase C F-98/105, Tanda Terima F-111/118, kemungkinan status Karyawan F-95/122, status Perusahaan F-87/121) — banyak sesi Claude Code berjalan paralel tanpa laporan balik konsisten. **Rekomendasi Jarvis**: untuk pertanyaan "apakah X sudah dikerjakan", defaultkan ke instruksi "cek dulu ke kode" di prompt, bukan tanya ingatan Boss langsung | LOCKED (observasi/rekomendasi proses) |

**Belum ada "LANJUT" ke Fase B Penjualan** — F-119, F-120, F-121, F-123 masih terbuka dan langsung menentukan bentuk skema.

---

| F-119 | Titik deduksi stok Penjualan | **RESOLVED** — keputusan Boss: **terima**, deduksi tetap di Penjualan, Invoice tidak deduksi lagi. F-23 (deduksi saat invoice) **tidak diadopsi utuh** di bms-new, direvisi sesuai kenyataan kode existing |
| F-120 | FIFO literal vs average-deduction | **Perlu didiskusikan lebih lanjut** — belum diputuskan Boss, lihat catatan diskusi di respons Jarvis |
| F-121 | Modul Perusahaan dihapus total, kontradiksi F-87 | **RESOLVED** — dikonfirmasi Boss: memang disengaja. `perusahaan_id` **dihapus total** dari rencana skema Surat Jalan/Tanda Terima/Invoice — bukan lagi bagian dari F-87 |
| F-122 | Status Karyawan (diklaim dihapus, kontradiksi F-95) | **RESOLVED** — dikonfirmasi Boss: memang disengaja. `driver_id`/`accounting_id` butuh alternatif — kandidat: teks bebas (pola `tanda_terimas.nama_penerima`) atau referensi ke `users`. Keputusan final di Fase A2 |
| F-123 | Arsitektur Invoice | **RESOLVED** — keputusan Boss: **agregasi per SPPG+tanggal** (ikuti F-22 lama). Implikasi: `invoices` butuh relasi many-to-many ke `penjualans` (pivot), bukan FK tunggal |
| F-125 | Implikasi F-123: status Surat Jalan — tetap 1:1 per Penjualan (seperti Tanda Terima, F-118), atau ikut pola agregasi seperti Invoice? Perlu dikonfirmasi saat investigasi lanjutan | **OPEN** |

---

**Total: 125 finding terkunci sebagai jangkar (F-1 s/d F-125), 15 di antaranya berstatus OPEN (F-52–F-57, F-97, F-104, F-114, F-115, F-116, F-120, F-121, F-122, F-125).** Begitu Boss konfirmasi satu per satu, statusnya diubah jadi LOCKED di dokumen ini — nomornya tidak berubah.** Begitu Boss konfirmasi satu per satu, statusnya diubah jadi LOCKED di dokumen ini — nomornya tidak berubah.
