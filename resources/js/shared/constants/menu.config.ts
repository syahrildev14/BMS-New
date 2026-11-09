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
      { label: 'Template Dokumen', href: '/admin/document-templates', permission: 'document-templates.manage' },
      { label: 'Armada', href: '/admin/armadas', permission: 'fleet.manage' },
      { label: 'Driver', href: '/admin/drivers', permission: 'fleet.manage' },
      { label: 'Rekening Bank', href: '/admin/bank-accounts', permission: 'bank-accounts.manage' },
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
      { label: 'Pengiriman (SJ & TT)', href: '/ops/delivery-notes', permission: 'delivery-notes.manage' },
      { label: 'Crosscheck H+1', href: '/ops/verifications', permission: 'delivery-verifications.manage' },
      { label: 'Approval Revisi', href: '/ops/revisions', permission: 'revisions.approve' },
    ],
  },
  {
    label: 'Purchasing',
    icon: ShoppingCart,
    children: [
      { label: 'Daftar Belanja Kering', href: '/purchasing/shopping-list', permission: 'purchase-notes.manage' },
      { label: 'Nota Pembelian', href: '/purchasing/purchase-notes', permission: 'purchase-notes.manage' },
      // Purchase Order & Gudang "Terima Barang" menus are retired (2026-11-05):
      // dry goods are bought directly with a nota. Their pages/routes remain
      // reachable for historical POs.
    ],
  },
  {
    label: 'Gudang',
    icon: Warehouse,
    children: [
      { label: 'Stok Barang', href: '/gudang/stock', permission: 'stock.manage' },
    ],
  },
  {
    label: 'Finance',
    icon: Receipt,
    children: [
      { label: 'Invoice', href: '/finance/invoices', permission: 'invoices.manage' },
      { label: 'Utang Supplier', href: '/finance/supplier-bills', permission: 'supplier-bills.manage' },
    ],
  },
  {
    label: 'Admin',
    icon: ShieldCheck,
    children: [
      { label: 'Role & Permission', href: '/admin/roles', permission: 'roles.manage' },
      { label: 'Pengguna', href: '/admin/users', permission: 'users.manage' },
      { label: 'Log Aktivitas', href: '/admin/audit-logs', permission: 'system.audit-log.view' },
    ],
  },
];
