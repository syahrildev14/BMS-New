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
import type { Item, Satuan, Supplier } from '@/shared/types/prd.type';

const columns: TableColumn<Item>[] = [
  { key: 'sku', accessor: (item) => item.sku },
  { key: 'name', accessor: (item) => item.name },
  { key: 'category', accessor: (item) => item.category },
  { key: 'satuan', accessor: (item) => item.satuan?.name },
  { key: 'supplier', accessor: (item) => item.default_supplier?.name },
  { key: 'status', accessor: (item) => (item.is_active ? 'Aktif' : 'Nonaktif') },
];

interface FormState {
  sku: string;
  name: string;
  category: 'DRY_GOODS' | 'WET_GOODS';
  satuan_id: string;
  default_supplier_id: string;
  margin_type: 'PERCENTAGE' | 'FIXED_IDR';
  margin_value: string;
  is_active: boolean;
  [key: string]: string | boolean;
}

const emptyForm: FormState = {
  sku: '',
  name: '',
  category: 'DRY_GOODS',
  satuan_id: '',
  default_supplier_id: '',
  margin_type: 'PERCENTAGE',
  margin_value: '',
  is_active: true,
};

export default function AdminItemsIndex({ items, suppliers, satuans }: { items: Item[]; suppliers: Supplier[]; satuans: Satuan[] }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm<FormState>(emptyForm);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(items, columns);

  const openCreate = () => {
    setEditingId(null);
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const openEdit = (item: Item) => {
    setEditingId(item.id);
    form.clearErrors();
    form.setData({
      sku: item.sku,
      name: item.name,
      category: item.category,
      satuan_id: String(item.satuan_id),
      default_supplier_id: item.default_supplier_id ? String(item.default_supplier_id) : '',
      margin_type: item.margin_type,
      margin_value: item.margin_value,
      is_active: item.is_active,
    });
    setDialogOpen(true);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    const onSuccess = () => setDialogOpen(false);

    if (editingId) {
      form.put(route('admin.items.update', editingId), { onSuccess });
    } else {
      form.post(route('admin.items.store'), { onSuccess });
    }
  };

  const destroy = async (item: Item) => {
    const ok = await confirmDelete({ title: `Hapus barang "${item.name}"?` });
    if (ok) form.delete(route('admin.items.destroy', item.id));
  };

  return (
    <>
      <Head title="Barang" />

      <PageHeader
        title="Master Data Barang"
        description="Katalog item yang dipakai Dapur saat membuat Order dan Purchasing saat input harga harian."
        breadcrumbs={[{ label: 'Master' }, { label: 'Barang' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Tambah Barang
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari SKU, nama, kategori, satuan, supplier..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="SKU" columnKey="sku" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Nama" columnKey="name" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Kategori" columnKey="category" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Satuan" columnKey="satuan" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Supplier Default" columnKey="supplier" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Status" columnKey="status" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7} className="h-32 text-center text-gray-400">
                {items.length === 0 ? 'Belum ada barang.' : 'Tidak ada barang yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((item) => (
              <TableRow key={item.id}>
                <TableCell className="font-medium">{item.sku}</TableCell>
                <TableCell>{item.name}</TableCell>
                <TableCell>
                  <Badge variant="outline">{item.category === 'DRY_GOODS' ? 'Dry Goods' : 'Wet Goods'}</Badge>
                </TableCell>
                <TableCell>{item.satuan?.name ?? '-'}</TableCell>
                <TableCell>{item.default_supplier?.name ?? '-'}</TableCell>
                <TableCell>
                  <Badge variant={item.is_active ? 'success' : 'default'}>{item.is_active ? 'Aktif' : 'Nonaktif'}</Badge>
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(item)}>
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(item)}>
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
            <DialogTitle>{editingId ? 'Edit Barang' : 'Tambah Barang Baru'}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="sku">SKU</Label>
                <Input id="sku" value={form.data.sku} onChange={(e) => form.setData('sku', e.target.value)} />
                <FieldError message={form.errors.sku} />
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="name">Nama Barang</Label>
                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                <FieldError message={form.errors.name} />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-1.5">
                <Label>Kategori</Label>
                <Select value={form.data.category} onValueChange={(v) => form.setData('category', v as FormState['category'])}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="DRY_GOODS">Dry Goods</SelectItem>
                    <SelectItem value="WET_GOODS">Wet Goods</SelectItem>
                  </SelectContent>
                </Select>
                <FieldError message={form.errors.category} />
              </div>

              <div className="flex flex-col gap-1.5">
                <Label>Satuan</Label>
                <Select value={form.data.satuan_id} onValueChange={(v) => form.setData('satuan_id', v)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih satuan..." />
                  </SelectTrigger>
                  <SelectContent>
                    {satuans.map((satuan) => (
                      <SelectItem key={satuan.id} value={String(satuan.id)}>
                        {satuan.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FieldError message={form.errors.satuan_id} />
              </div>
            </div>

            <div className="flex flex-col gap-1.5">
              <Label>Supplier Default</Label>
              <Select
                value={form.data.default_supplier_id || '_none'}
                onValueChange={(v) => form.setData('default_supplier_id', v === '_none' ? '' : v)}
              >
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
              <FieldError message={form.errors.default_supplier_id} />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-1.5">
                <Label>Tipe Margin</Label>
                <Select value={form.data.margin_type} onValueChange={(v) => form.setData('margin_type', v as FormState['margin_type'])}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="PERCENTAGE">Persentase (%)</SelectItem>
                    <SelectItem value="FIXED_IDR">Nominal Tetap (Rp)</SelectItem>
                  </SelectContent>
                </Select>
                <FieldError message={form.errors.margin_type} />
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="margin_value">Nilai Margin</Label>
                <Input
                  id="margin_value"
                  type="number"
                  step="0.01"
                  value={form.data.margin_value}
                  onChange={(e) => form.setData('margin_value', e.target.value)}
                />
                <FieldError message={form.errors.margin_value} />
              </div>
            </div>

            <label className="flex items-center gap-2 text-sm text-gray-600">
              <Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked === true)} />
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

AdminItemsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
