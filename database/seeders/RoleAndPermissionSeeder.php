<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Catalog of roles/permissions from PRD §MODULE 0 (Dynamic User & Role
 * Management): Dapur, Ops, Purchasing, Finance, Gudang, Admin. `gudang` now
 * has a real module (2026-10-21: stock-on-hand + PO receiving flow) — it is
 * no longer a zero-permission placeholder.
 */
class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'orders.manage',
            'delivery-notes.manage',
            'delivery-verifications.manage',
            'revisions.approve',
            'invoices.manage',
            'invoices.approve',
            'supplier-bills.manage',
            'supplier-bills.approve',
            'pricing.manage',
            'roles.manage',
            'users.manage',
            'items.manage',
            'suppliers.manage',
            'dapurs.manage',
            'satuans.manage',
            'purchase-orders.manage',
            'purchase-notes.manage',
            'stock.manage',
            'system.backup.manage',
            'system.audit-log.view',
            'document-templates.manage',
            'fleet.manage',
            'bank-accounts.manage',
            'appearance.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $rolePermissions = [
            'dapur' => ['orders.manage'],
            'ops' => ['delivery-notes.manage', 'delivery-verifications.manage', 'revisions.approve', 'fleet.manage'],
            'purchasing' => ['pricing.manage', 'items.manage', 'suppliers.manage', 'satuans.manage', 'purchase-orders.manage', 'purchase-notes.manage'],
            'finance' => ['invoices.manage', 'supplier-bills.manage', 'bank-accounts.manage'],
            'gudang' => ['stock.manage'],
            'admin' => $permissions,
        ];

        foreach ($rolePermissions as $roleName => $rolePermissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissionNames);
        }
    }
}
