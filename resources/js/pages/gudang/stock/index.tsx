import { Head, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { errorAlert } from '@/shared/lib/alerts';
import { formatQty } from '@/shared/lib/format';
import type { Gudang, StockItemRow } from '@/shared/types/prd.type';

const columns: TableColumn<StockItemRow>[] = [
  { key: 'sku', accessor: (row) => row.sku },
  { key: 'name', accessor: (row) => row.name },
  { key: 'qty_on_hand', accessor: (row) => row.qty_on_hand },
];

export default function GudangStockIndex({ gudang, items }: { gudang: Gudang | null; items: StockItemRow[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(items, columns);

  const [opnameItem, setOpnameItem] = useState<StockItemRow | null>(null);
  const [physicalQty, setPhysicalQty] = useState('');
  const [saving, setSaving] = useState(false);

  const openOpname = (row: StockItemRow) => {
    setOpnameItem(row);
    setPhysicalQty(String(row.qty_on_hand));
  };

  const submitOpname = () => {
    if (!opnameItem) return;
    setSaving(true);
    router.post(
      route('gudang.stock.opname', opnameItem.id),
      { physical_qty: physicalQty },
      {
        onFinish: () => setSaving(false),
        onSuccess: () => setOpnameItem(null),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyimpan Stock Opname.'),
      },
    );
  };

  return (
    <>
      <Head title="Stok Barang" />

      <PageHeader
        title="Stok Barang Gudang"
        description={gudang ? `Saldo stok DRY_GOODS saat ini di ${gudang.name}, dihitung dari lot pembelian yang belum habis terpakai (FIFO).` : 'Belum ada Gudang terdaftar.'}
        breadcrumbs={[{ label: 'Gudang' }, { label: 'Stok Barang' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari SKU atau nama barang..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="SKU" columnKey="sku" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Nama Barang" columnKey="name" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Stok Tersedia" columnKey="qty_on_hand" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                {items.length === 0 ? 'Belum ada barang DRY_GOODS.' : 'Tidak ada barang yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((row) => (
              <TableRow key={row.id}>
                <TableCell className="font-medium">{row.sku}</TableCell>
                <TableCell>
                  {row.name} {row.satuan && <span className="text-gray-400">({row.satuan.name})</span>}
                </TableCell>
                <TableCell>
                  {row.qty_on_hand <= 0 ? <Badge variant="destructive">Kosong</Badge> : <Badge variant="success">{formatQty(row.qty_on_hand)}</Badge>}
                </TableCell>
                <TableCell className="text-right">
                  <Button variant="outline" size="sm" onClick={() => openOpname(row)}>
                    Stock Opname
                  </Button>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />

      <Dialog open={!!opnameItem} onOpenChange={(open) => !open && setOpnameItem(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Stock Opname — {opnameItem?.name}</DialogTitle>
          </DialogHeader>

          <div className="flex flex-col gap-3">
            <p className="text-sm text-gray-500">
              Stok sistem saat ini: <span className="font-medium text-gray-700">{formatQty(opnameItem?.qty_on_hand)}</span>
              {opnameItem?.satuan && ` ${opnameItem.satuan.name}`}. Masukkan hasil hitung fisik di gudang.
            </p>
            <div className="flex flex-col gap-1">
              <Label>Qty Fisik Sebenarnya</Label>
              <Input type="number" min="0" step="0.01" value={physicalQty} onChange={(e) => setPhysicalQty(e.target.value)} />
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpnameItem(null)}>
              Batal
            </Button>
            <Button type="button" onClick={submitOpname} disabled={saving}>
              {saving ? 'Menyimpan...' : 'Simpan Penyesuaian'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

GudangStockIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
