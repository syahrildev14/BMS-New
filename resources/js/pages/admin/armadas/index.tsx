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
import type { Armada } from '@/shared/types/prd.type';

type FormState = {
  name: string;
  plate_number: string;
  is_active: boolean;
};

const emptyForm: FormState = { name: '', plate_number: '', is_active: true };

const columns: TableColumn<Armada>[] = [
  { key: 'name', accessor: (armada) => armada.name },
  { key: 'plate_number', accessor: (armada) => armada.plate_number },
];

export default function AdminArmadasIndex({ armadas }: { armadas: Armada[] }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm<FormState>(emptyForm);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(armadas, columns);

  const openCreate = () => {
    setEditingId(null);
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const openEdit = (armada: Armada) => {
    setEditingId(armada.id);
    form.clearErrors();
    form.setData({ name: armada.name, plate_number: armada.plate_number, is_active: armada.is_active });
    setDialogOpen(true);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    const onSuccess = () => setDialogOpen(false);

    if (editingId) {
      form.put(route('admin.armadas.update', editingId), { onSuccess });
    } else {
      form.post(route('admin.armadas.store'), { onSuccess });
    }
  };

  const destroy = async (armada: Armada) => {
    const ok = await confirmDelete({ title: `Hapus armada "${armada.name}"?` });
    if (ok) form.delete(route('admin.armadas.destroy', armada.id));
  };

  return (
    <>
      <Head title="Armada" />

      <PageHeader
        title="Master Data Armada"
        description="Kendaraan yang dipilih saat membuat Surat Jalan. Mengubah data di sini tidak mengubah dokumen yang sudah terbit."
        breadcrumbs={[{ label: 'Master' }, { label: 'Armada' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Tambah Armada
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari nama atau nomor polisi..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nama" columnKey="name" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="No. Polisi" columnKey="plate_number" sort={sort} onSort={toggleSort} />
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                {armadas.length === 0 ? 'Belum ada armada.' : 'Tidak ada armada yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((armada) => (
              <TableRow key={armada.id}>
                <TableCell className="font-medium">{armada.name}</TableCell>
                <TableCell>{armada.plate_number}</TableCell>
                <TableCell>{armada.is_active ? 'Aktif' : 'Nonaktif'}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(armada)}>
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(armada)}>
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
            <DialogTitle>{editingId ? 'Edit Armada' : 'Tambah Armada Baru'}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="name">Nama Armada</Label>
              <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <FieldError message={form.errors.name} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="plate_number">No. Polisi</Label>
              <Input id="plate_number" value={form.data.plate_number} onChange={(e) => form.setData('plate_number', e.target.value)} />
              <FieldError message={form.errors.plate_number} />
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

AdminArmadasIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
