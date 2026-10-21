import type { LucideIcon } from 'lucide-react';
import {
  LayoutDashboard,
  ClipboardList,
  Truck,
  Receipt,
  ShieldCheck,
  Boxes,
  ShoppingCart,
  Warehouse,
} from 'lucide-react';

export interface MenuChild {
  label: string;
  href: string;
  /** Spatie permission name required to see this entry. Omit = visible to all authenticated users. */
  permission?: string;
}

export interface MenuItem {
  label: string;
  href?: string;
  icon: LucideIcon;
  permission?: string;
  children?: MenuChild[];
}

/**
 * Single source of truth untuk sidebar menu.
 * Menambah modul baru = tambah entry di sini, TIDAK perlu sentuh komponen Sidebar.
 * Mengikuti modul PRD v5.0.0 (Dapur Portal, Multi-SJ, Reconciled Split Billing).
 * `permission` difilter di AppLayout berdasarkan `auth.permissions` dari backend.
 */
export const menuConfig: MenuItem[] = [
  { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  {
    label: 'Master',
    icon: Boxes,
    children: [
      { label: 'Barang', href: '/admin/items', permission: 'items.manage' },
      { label: 'Satuan', href: '/admin/satuans', permission: 'satuans.manage' },
      { label: 'Supplier', href: '/admin/suppliers', permission: 'suppliers.manage' },
      { label: 'Dapur', href: '/admin/dapurs', permission: 'dapurs.manage' },
      { label: 'Harga Harian', href: '/admin/pricing', permission: 'pricing.manage' },
    ],
  },
  {
    label: 'Dapur',
    icon: ClipboardList,
    children: [
      { label: 'Order Saya', href: '/dapur/orders', permission: 'orders.manage' },
    ],
  },
  {
    label: 'Ops',
    icon: Truck,
    children: [
      { label: 'Surat Jalan', href: '/ops/delivery-notes', permission: 'delivery-notes.manage' },
      { label: 'Approval Revisi', href: '/ops/revisions', permission: 'revisions.approve' },
    ],
  },
  {
    label: 'Purchasing',
    icon: ShoppingCart,
    children: [
      { label: 'Purchase Order', href: '/purchasing/purchase-orders', permission: 'purchase-orders.manage' },
    ],
  },
  {
    label: 'Gudang',
    icon: Warehouse,
    children: [
      { label: 'Terima Barang', href: '/gudang/purchase-orders', permission: 'stock.manage' },
      { label: 'Stok Barang', href: '/gudang/stock', permission: 'stock.manage' },
    ],
  },
  {
    label: 'Finance',
    icon: Receipt,
    children: [
      { label: 'Invoice', href: '/finance/invoices', permission: 'invoices.manage' },
    ],
  },
  {
    label: 'Admin',
    icon: ShieldCheck,
    children: [
      { label: 'Role & Permission', href: '/admin/roles', permission: 'roles.manage' },
      { label: 'Pengguna', href: '/admin/users', permission: 'users.manage' },
    ],
  },
];
