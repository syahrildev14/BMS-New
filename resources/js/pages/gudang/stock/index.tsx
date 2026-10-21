import { Head } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge } from '@/shared/components/ui/badge';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import type { Gudang, StockItemRow } from '@/shared/types/prd.type';

const columns: TableColumn<StockItemRow>[] = [
  { key: 'sku', accessor: (row) => row.sku },
  { key: 'name', accessor: (row) => row.name },
  { key: 'qty_on_hand', accessor: (row) => row.qty_on_hand },
];

export default function GudangStockIndex({ gudang, items }: { gudang: Gudang | null; items: StockItemRow[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(items, columns);

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
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={3} className="h-32 text-center text-gray-400">
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
                  {row.qty_on_hand <= 0 ? <Badge variant="destructive">Kosong</Badge> : <Badge variant="success">{row.qty_on_hand}</Badge>}
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />
    </>
  );
}

GudangStockIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
