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
import type { Driver } from '@/shared/types/prd.type';

type FormState = {
  name: string;
  is_active: boolean;
};

const emptyForm: FormState = { name: '', is_active: true };

const columns: TableColumn<Driver>[] = [{ key: 'name', accessor: (driver) => driver.name }];

export default function AdminDriversIndex({ drivers }: { drivers: Driver[] }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm<FormState>(emptyForm);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(drivers, columns);

  const openCreate = () => {
    setEditingId(null);
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const openEdit = (driver: Driver) => {
    setEditingId(driver.id);
    form.clearErrors();
    form.setData({ name: driver.name, is_active: driver.is_active });
    setDialogOpen(true);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    const onSuccess = () => setDialogOpen(false);

    if (editingId) {
      form.put(route('admin.drivers.update', editingId), { onSuccess });
    } else {
      form.post(route('admin.drivers.store'), { onSuccess });
    }
  };

  const destroy = async (driver: Driver) => {
    const ok = await confirmDelete({ title: `Hapus driver "${driver.name}"?` });
    if (ok) form.delete(route('admin.drivers.destroy', driver.id));
  };

  return (
    <>
      <Head title="Driver" />

      <PageHeader
        title="Master Data Driver"
        description="Driver yang dipilih saat membuat Surat Jalan. Mengubah data di sini tidak mengubah dokumen yang sudah terbit."
        breadcrumbs={[{ label: 'Master' }, { label: 'Driver' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Tambah Driver
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari nama driver..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nama" columnKey="name" sort={sort} onSort={toggleSort} />
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={3} className="h-32 text-center text-gray-400">
                {drivers.length === 0 ? 'Belum ada driver.' : 'Tidak ada driver yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((driver) => (
              <TableRow key={driver.id}>
                <TableCell className="font-medium">{driver.name}</TableCell>
                <TableCell>{driver.is_active ? 'Aktif' : 'Nonaktif'}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(driver)}>
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(driver)}>
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
            <DialogTitle>{editingId ? 'Edit Driver' : 'Tambah Driver Baru'}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="name">Nama Driver</Label>
              <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <FieldError message={form.errors.name} />
            </div>

            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
              Aktif
            </label>

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

AdminDriversIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
