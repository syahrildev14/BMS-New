// Entry point Inertia untuk route GET /master/barang.
// File ini SENGAJA tipis — semua logic ada di features/master/barang.
// Inertia::render('Master/Barang/index') di controller Laravel akan me-resolve file ini.
export { SatuanPage as default } from '@/features/master/satuan';
