import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { Plus } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Checkbox } from '@/shared/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { FieldError } from '@/shared/components/ui/field-error';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { confirmDelete } from '@/shared/lib/alerts';
import type { Permission, Role } from '@/shared/types/prd.type';

interface FormState {
  name: string;
  permissions: string[];
  [key: string]: string | string[];
}

const emptyForm: FormState = { name: '', permissions: [] };

const columns: TableColumn<Role>[] = [
  { key: 'name', accessor: (role) => role.name },
  { key: 'permissions', accessor: (role) => role.permissions?.map((p) => p.name).join(' '), sortable: false },
];

export default function AdminRolesIndex({ roles, permissions }: { roles: Role[]; permissions: Permission[] }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm<FormState>(emptyForm);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(roles, columns);

  const openCreate = () => {
    setEditingId(null);
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const openEdit = (role: Role) => {
    setEditingId(role.id);
    form.clearErrors();
    form.setData({ name: role.name, permissions: role.permissions?.map((p) => p.name) ?? [] });
    setDialogOpen(true);
  };

  const togglePermission = (permissionName: string) => {
    const current = form.data.permissions;
    form.setData(
      'permissions',
      current.includes(permissionName) ? current.filter((p) => p !== permissionName) : [...current, permissionName],
    );
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    const onSuccess = () => setDialogOpen(false);

    if (editingId) {
      form.put(route('admin.roles.update', editingId), { onSuccess });
    } else {
      form.post(route('admin.roles.store'), { onSuccess });
    }
  };

  const destroy = async (role: Role) => {
    const ok = await confirmDelete({ title: `Hapus role "${role.name}"?` });
    if (ok) form.delete(route('admin.roles.destroy', role.id));
  };

  return (
    <>
      <Head title="Role & Permission" />

      <PageHeader
        title="Role & Permission"
        description="Dynamic RBAC — buat role custom dan assign permission granular."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Role & Permission' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Tambah Role
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari role atau permission..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Role" columnKey="name" sort={sort} onSort={toggleSort} />
            <TableHead>Permissions</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={3} className="h-32 text-center text-gray-400">
                {roles.length === 0 ? 'Belum ada role.' : 'Tidak ada role yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((role) => (
              <TableRow key={role.id}>
                <TableCell className="font-medium">{role.name}</TableCell>
                <TableCell>
                  <div className="flex flex-wrap gap-1">
                    {role.permissions && role.permissions.length > 0 ? (
                      role.permissions.map((p) => (
                        <Badge key={p.id} variant="outline">
                          {p.name}
                        </Badge>
                      ))
                    ) : (
                      <span className="text-xs text-gray-400">-</span>
                    )}
                  </div>
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(role)}>
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(role)}>
                      Hapus
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingId ? 'Edit Role' : 'Buat Role Baru'}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="role_name">Nama Role</Label>
              <Input id="role_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Purchasing" />
              <FieldError message={form.errors.name} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label>Permissions</Label>
              <div className="flex max-h-64 flex-col gap-2 overflow-y-auto rounded-md border border-gray-100 p-2">
                {permissions.map((permission) => (
                  <label key={permission.id} className="flex items-center gap-2 text-sm text-gray-600">
                    <Checkbox
                      checked={form.data.permissions.includes(permission.name)}
                      onCheckedChange={() => togglePermission(permission.name)}
                    />
                    {permission.name}
                  </label>
                ))}
                {permissions.length === 0 && <p className="text-xs text-gray-400">Belum ada permission terdaftar.</p>}
              </div>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Batal
              </Button>
              <Button type="submit" disabled={form.processing || !form.data.name}>
                {form.processing ? 'Menyimpan...' : 'Simpan'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

AdminRolesIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
