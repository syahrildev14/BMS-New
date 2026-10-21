import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { Plus } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
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
import type { Supplier } from '@/shared/types/prd.type';

interface FormState {
  code: string;
  name: string;
  address: string;
  pic_name: string;
  pic_phone: string;
  [key: string]: string;
}

const emptyForm: FormState = { code: '', name: '', address: '', pic_name: '', pic_phone: '' };

const columns: TableColumn<Supplier>[] = [
  { key: 'code', accessor: (supplier) => supplier.code },
  { key: 'name', accessor: (supplier) => supplier.name },
  { key: 'pic_name', accessor: (supplier) => supplier.pic_name },
  { key: 'pic_phone', accessor: (supplier) => supplier.pic_phone },
];

export default function AdminSuppliersIndex({ suppliers }: { suppliers: Supplier[] }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm<FormState>(emptyForm);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(suppliers, columns);

  const openCreate = () => {
    setEditingId(null);
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const openEdit = (supplier: Supplier) => {
    setEditingId(supplier.id);
    form.clearErrors();
    form.setData({
      code: supplier.code,
      name: supplier.name,
      address: supplier.address ?? '',
      pic_name: supplier.pic_name ?? '',
      pic_phone: supplier.pic_phone ?? '',
    });
    setDialogOpen(true);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    const onSuccess = () => setDialogOpen(false);

    if (editingId) {
      form.put(route('admin.suppliers.update', editingId), { onSuccess });
    } else {
      form.post(route('admin.suppliers.store'), { onSuccess });
    }
  };

  const destroy = async (supplier: Supplier) => {
    const ok = await confirmDelete({ title: `Hapus supplier "${supplier.name}"?` });
    if (ok) form.delete(route('admin.suppliers.destroy', supplier.id));
  };

  return (
    <>
      <Head title="Supplier" />

      <PageHeader
        title="Master Data Supplier"
        description="Daftar vendor/supplier yang memasok barang ke Dapur SPPG."
        breadcrumbs={[{ label: 'Master' }, { label: 'Supplier' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Tambah Supplier
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari kode, nama, atau PIC supplier..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Kode" columnKey="code" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Nama" columnKey="name" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="PIC" columnKey="pic_name" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="No. HP" columnKey="pic_phone" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="h-32 text-center text-gray-400">
                {suppliers.length === 0 ? 'Belum ada supplier.' : 'Tidak ada supplier yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((supplier) => (
              <TableRow key={supplier.id}>
                <TableCell className="font-medium">{supplier.code}</TableCell>
                <TableCell>{supplier.name}</TableCell>
                <TableCell>{supplier.pic_name ?? '-'}</TableCell>
                <TableCell>{supplier.pic_phone ?? '-'}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(supplier)}>
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(supplier)}>
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
            <DialogTitle>{editingId ? 'Edit Supplier' : 'Tambah Supplier Baru'}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="code">Kode</Label>
              <Input id="code" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
              <FieldError message={form.errors.code} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="name">Nama Supplier</Label>
              <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <FieldError message={form.errors.name} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="address">Alamat</Label>
              <Input id="address" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
              <FieldError message={form.errors.address} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="pic_name">Nama PIC</Label>
              <Input id="pic_name" value={form.data.pic_name} onChange={(e) => form.setData('pic_name', e.target.value)} />
              <FieldError message={form.errors.pic_name} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="pic_phone">No. HP PIC</Label>
              <Input id="pic_phone" value={form.data.pic_phone} onChange={(e) => form.setData('pic_phone', e.target.value)} />
              <FieldError message={form.errors.pic_phone} />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Batal
              </Button>
              <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Menyimpan...' : 'Simpan'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

AdminSuppliersIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
