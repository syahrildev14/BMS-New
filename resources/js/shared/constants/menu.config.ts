import type { LucideIcon } from 'lucide-react';
import {
  Boxes,
  LayoutDashboard,
  ShoppingCart,
  Truck,
  Warehouse,
} from 'lucide-react';

export interface MenuItem {
  label: string;
  href?: string;
  icon: LucideIcon;
  children?: { label: string; href: string }[];
}

/**
 * Single source of truth untuk sidebar menu.
 * Menambah modul baru = tambah entry di sini, TIDAK perlu sentuh komponen Sidebar.
 * Nanti bisa di-filter berdasarkan permission user (lihat catatan di akhir chat sebelumnya).
 */
export const menuConfig: MenuItem[] = [
  { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  {
    label: 'Master',
    icon: Boxes,
    children: [
      { label: 'Barang', href: '/master/barang' },
      { label: 'Supplier', href: '/master/supplier' },
      { label: 'Gudang', href: '/master/gudang' },
      { label: 'Satuan', href: '/master/satuan' },
      { label: 'Armada', href: '/master/armada' },
      { label: 'SPPG', href: '/master/sppg' },
      { label: 'Karyawan', href: '/master/karyawan' },
    ],
  },
  {
    label: 'Pembelian',
    icon: ShoppingCart,
    children: [
      { label: 'Order Penawaran', href: '/pembelian/order-penawaran' },
      { label: 'Daftar Pembelanjaan', href: '/pembelian/daftar-pembelanjaan' },
      { label: 'Pembelanjaan Supplier', href: '/pembelian/daftar-pembelanjaan-supplier' },
    ],
  },
  {
    label: 'Warehouse',
    icon: Warehouse,
    children: [
      { label: 'Inbound', href: '/warehouse/inbound' },
      { label: 'Stok Gudang', href: '/warehouse/stok-gudang' },
      { label: 'Retur / Rusak', href: '/warehouse/retur' },
    ],
  },
  {
    label: 'Penjualan',
    icon: Truck,
    children: [
      { label: 'Penjualan', href: '/penjualan' },
      { label: 'Surat Jalan', href: '/penjualan/surat-jalan' },
      { label: 'Tanda Terima', href: '/penjualan/tanda-terima' },
      { label: 'Invoice', href: '/penjualan/invoice' },
    ],
  },
];
