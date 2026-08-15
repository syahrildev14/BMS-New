/**
 * Public API modul Satuan.
 * Modul lain (mis. Barang yang butuh daftar satuan) HANYA boleh import dari sini,
 * TIDAK boleh deep-import ke features/master/satuan/components/* atau hooks/* secara langsung.
 * Ini menjaga batas antar-modul tetap jelas saat aplikasi bertumbuh ke puluhan modul.
 */
export { SatuanPage } from './SatuanPage';
export { useSatuanList, useSatuanDetail } from './hooks/useSatuan';
export type { Satuan } from './types/satuan.type';
