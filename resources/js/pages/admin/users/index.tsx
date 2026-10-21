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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { confirmDelete } from '@/shared/lib/alerts';
import type { AppUser, Dapur, Role, Supplier } from '@/shared/types/prd.type';

interface FormState {
  name: string;
  email: string;
  password: string;
  role: string;
  dapur_id: string;
  supplier_id: string;
  is_active: boolean;
  [key: string]: string | boolean;
}

const emptyForm: FormState = { name: '', email: '', password: '', role: '', dapur_id: '', supplier_id: '', is_active: true };

const columns: TableColumn<AppUser>[] = [
  { key: 'name', accessor: (user) => user.name },
  { key: 'email', accessor: (user) => user.email },
  { key: 'role', accessor: (user) => user.roles?.map((r) => r.name).join(' ') },
  { key: 'scope', accessor: (user) => user.dapur?.name ?? user.supplier?.name },
  { key: 'status', accessor: (user) => (user.is_active ? 'Aktif' : 'Nonaktif') },
];

export default function AdminUsersIndex({
  users,
  roles,
  dapurs,
  suppliers,
}: {
  users: AppUser[];
  roles: Role[];
  dapurs: Dapur[];
  suppliers: Supplier[];
}) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm<FormState>(emptyForm);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(users, columns);

  const openCreate = () => {
    setEditingId(null);
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const openEdit = (user: AppUser) => {
    setEditingId(user.id);
    form.clearErrors();
    form.setData({
      name: user.name,
      email: user.email,
      password: '',
      role: user.roles?.[0]?.name ?? '',
      dapur_id: user.dapur_id ? String(user.dapur_id) : '',
      supplier_id: user.supplier_id ? String(user.supplier_id) : '',
      is_active: user.is_active,
    });
    setDialogOpen(true);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    const onSuccess = () => setDialogOpen(false);

    if (editingId) {
      form.put(route('admin.users.update', editingId), { onSuccess });
    } else {
      form.post(route('admin.users.store'), { onSuccess });
    }
  };

  const destroy = async (user: AppUser) => {
    const ok = await confirmDelete({ title: `Hapus user "${user.name}"?` });
    if (ok) form.delete(route('admin.users.destroy', user.id));
  };

  return (
    <>
      <Head title="Pengguna" />

      <PageHeader
        title="Manajemen Pengguna"
        description="Buat akun untuk Dapur, Supplier, Ops, Purchasing, Finance, atau Admin — data scoping otomatis mengikuti dapur_id / supplier_id."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Pengguna' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Tambah Pengguna
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari nama, email, role, atau scope..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nama" columnKey="name" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Email" columnKey="email" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Role" columnKey="role" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Scope" columnKey="scope" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Status" columnKey="status" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="h-32 text-center text-gray-400">
                {users.length === 0 ? 'Belum ada pengguna.' : 'Tidak ada pengguna yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((user) => (
              <TableRow key={user.id}>
                <TableCell className="font-medium">{user.name}</TableCell>
                <TableCell>{user.email}</TableCell>
                <TableCell>
                  {user.roles && user.roles.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                      {user.roles.map((r) => (
                        <Badge key={r.id} variant="info">
                          {r.name}
                        </Badge>
                      ))}
                    </div>
                  ) : (
                    '-'
                  )}
                </TableCell>
                <TableCell>{user.dapur?.name ?? user.supplier?.name ?? '-'}</TableCell>
                <TableCell>
                  <Badge variant={user.is_active ? 'success' : 'default'}>{user.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(user)}>
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(user)}>
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
            <DialogTitle>{editingId ? 'Edit Pengguna' : 'Buat Pengguna Baru'}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="name">Nama</Label>
              <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <FieldError message={form.errors.name} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
              <FieldError message={form.errors.email} />
            </div>

            {!editingId && (
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="password">Password</Label>
                <Input id="password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                <FieldError message={form.errors.password} />
              </div>
            )}

            <div className="flex flex-col gap-1.5">
              <Label>Role</Label>
              <Select value={form.data.role} onValueChange={(v) => form.setData('role', v)}>
                <SelectTrigger>
                  <SelectValue placeholder="Pilih role..." />
                </SelectTrigger>
                <SelectContent>
                  {roles.map((role) => (
                    <SelectItem key={role.id} value={role.name}>
                      {role.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError message={form.errors.role} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label>Scope Dapur (khusus akun Dapur)</Label>
              <Select value={form.data.dapur_id || '_none'} onValueChange={(v) => form.setData('dapur_id', v === '_none' ? '' : v)}>
                <SelectTrigger>
                  <SelectValue placeholder="Tidak ada" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="_none">Tidak ada</SelectItem>
                  {dapurs.map((dapur) => (
                    <SelectItem key={dapur.id} value={String(dapur.id)}>
                      {dapur.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError message={form.errors.dapur_id} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label>Scope Supplier (khusus akun Supplier)</Label>
              <Select value={form.data.supplier_id || '_none'} onValueChange={(v) => form.setData('supplier_id', v === '_none' ? '' : v)}>
                <SelectTrigger>
                  <SelectValue placeholder="Tidak ada" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="_none">Tidak ada</SelectItem>
                  {suppliers.map((supplier) => (
                    <SelectItem key={supplier.id} value={String(supplier.id)}>
                      {supplier.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError message={form.errors.supplier_id} />
            </div>

            <label className="flex items-center gap-2 text-sm text-gray-600">
              <Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked === true)} />
              Aktif
            </label>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Batal
              </Button>
              <Button
                type="submit"
                disabled={form.processing || !form.data.name || !form.data.email || !form.data.role || (!editingId && !form.data.password)}
              >
                {form.processing ? 'Menyimpan...' : 'Simpan'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

AdminUsersIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
