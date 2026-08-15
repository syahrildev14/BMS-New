
<!--=========== Struktur Folder Frontend ===========-->

resources/js/
├── app/
│   ├── app.tsx                     # createInertiaApp entry + resolver
│   ├── providers/
│   │   ├── QueryProvider.tsx
│   │   └── AppProviders.tsx
│   └── layouts/
│       ├── AppLayout.tsx           # Sidebar + Navbar + Breadcrumb
│       ├── AuthLayout.tsx
│       └── GuestLayout.tsx
│
├── pages/                          # thin entries, mapping 1:1 Inertia::render()
│   ├── Master/Barang/index.tsx
│   ├── Master/Supplier/index.tsx
│   ├── Pembelian/OrderPenawaran/index.tsx
│   ├── Warehouse/Inbound/index.tsx
│   └── Penjualan/Invoice/index.tsx
│
├── features/
│   ├── master/
│   │   ├── barang/
│   │   │   ├── components/ (BarangTable, BarangDialog, BarangForm)
│   │   │   ├── hooks/useBarang.ts
│   │   │   ├── services/barang.service.ts
│   │   │   ├── schemas/barang.schema.ts
│   │   │   ├── types/barang.type.ts
│   │   │   ├── constants/
│   │   │   ├── BarangPage.tsx
│   │   │   └── index.ts            # public API
│   │   ├── supplier/ ...
│   │   ├── gudang/ ...
│   │   ├── satuan/ ...
│   │   ├── armada/ ...
│   │   └── sppg/ ...
│   ├── pembelian/
│   │   ├── order-penawaran/
│   │   ├── daftar-pembelanjaan/
│   │   └── daftar-pembelanjaan-supplier/
│   ├── warehouse/
│   │   ├── inbound/
│   │   ├── stok-gudang/
│   │   └── retur/
│   └── penjualan/
│       ├── penjualan/
│       ├── surat-jalan/
│       ├── tanda-terima/
│       └── invoice/
│
└── shared/
    ├── components/
    │   ├── ui/                     # hasil generate shadcn
    │   ├── data-table/
    │   ├── layout/ (Sidebar, Navbar, Breadcrumb, PageHeader)
    │   └── form/ (FormDialog wrapper shadcn Dialog)
    ├── hooks/
    │   ├── createResourceHooks.ts  # factory generic
    │   ├── useDebounce.ts
    │   └── useConfirmDelete.ts
    ├── lib/
    │   ├── axios.ts                # instance + interceptor Sanctum
    │   ├── queryClient.ts
    │   └── alerts.ts               # confirmDelete, successAlert, errorAlert, warningAlert
    ├── services/
    │   └── createResourceService.ts
    ├── types/
    │   └── api.type.ts             # ApiResponse<T>, PaginatedResponse<T>
    └── constants/
        └── menu.config.ts          # sidebar menu, driven by config bukan hardcode