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
import type { Satuan } from '@/shared/types/prd.type';

interface FormState {
  code: string;
  name: string;
  [key: string]: string;
}

const emptyForm: FormState = { code: '', name: '' };

const columns: TableColumn<Satuan>[] = [
  { key: 'code', accessor: (satuan) => satuan.code },
  { key: 'name', accessor: (satuan) => satuan.name },
];

export default function AdminSatuansIndex({ satuans }: { satuans: Satuan[] }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm<FormState>(emptyForm);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(satuans, columns);

  const openCreate = () => {
    setEditingId(null);
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const openEdit = (satuan: Satuan) => {
    setEditingId(satuan.id);
    form.clearErrors();
    form.setData({ code: satuan.code, name: satuan.name });
    setDialogOpen(true);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    const onSuccess = () => setDialogOpen(false);

    if (editingId) {
      form.put(route('admin.satuans.update', editingId), { onSuccess });
    } else {
      form.post(route('admin.satuans.store'), { onSuccess });
    }
  };

  const destroy = async (satuan: Satuan) => {
    const ok = await confirmDelete({ title: `Hapus satuan "${satuan.name}"?` });
    if (ok) form.delete(route('admin.satuans.destroy', satuan.id));
  };

  return (
    <>
      <Head title="Satuan" />

      <PageHeader
        title="Master Data Satuan"
        description="Satuan baku untuk Barang (kg, liter, pcs, dll) — menghindari typo karena dipilih dari daftar, bukan diketik bebas."
        breadcrumbs={[{ label: 'Master' }, { label: 'Satuan' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Tambah Satuan
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari kode atau nama satuan..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Kode" columnKey="code" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Nama" columnKey="name" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={3} className="h-32 text-center text-gray-400">
                {satuans.length === 0 ? 'Belum ada satuan.' : 'Tidak ada satuan yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((satuan) => (
              <TableRow key={satuan.id}>
                <TableCell className="font-medium">{satuan.code}</TableCell>
                <TableCell>{satuan.name}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(satuan)}>
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(satuan)}>
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
            <DialogTitle>{editingId ? 'Edit Satuan' : 'Tambah Satuan Baru'}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="code">Kode</Label>
              <Input id="code" placeholder="KG" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
              <FieldError message={form.errors.code} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="name">Nama Satuan</Label>
              <Input id="name" placeholder="Kilogram" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <FieldError message={form.errors.name} />
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

AdminSatuansIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
