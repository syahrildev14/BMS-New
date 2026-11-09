import { Head, Link } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';

interface PurchaseOrderDateGroup {
  date: string;
  po_count: number;
}

const columns: TableColumn<PurchaseOrderDateGroup>[] = [
  { key: 'date', accessor: (group) => group.date },
  { key: 'po_count', accessor: (group) => group.po_count },
];

export default function GudangPurchaseOrdersIndex({ groups }: { groups: PurchaseOrderDateGroup[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(groups, columns);

  return (
    <>
      <Head title="Terima Barang" />

      <PageHeader
        title="Terima Barang dari Supplier"
        description="PO yang sudah dikirim Purchasing dan menunggu barang fisik diterima ke Gudang, dikelompokkan per tanggal kirim."
        breadcrumbs={[{ label: 'Gudang' }, { label: 'Terima Barang' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari tanggal..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Tanggal Kirim" columnKey="date" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jumlah PO" columnKey="po_count" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={3} className="h-32 text-center text-gray-400">
                {groups.length === 0 ? 'Tidak ada PO yang menunggu penerimaan.' : 'Tidak ada tanggal yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((group) => (
              <TableRow key={group.date}>
                <TableCell className="font-medium">{group.date}</TableCell>
                <TableCell>{group.po_count}</TableCell>
                <TableCell className="text-right">
                  <Link
                    href={route('gudang.purchase-orders.by-date', group.date)}
                    className="text-sm font-medium text-primary-text hover:underline"
                  >
                    Lihat Detail
                  </Link>
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

GudangPurchaseOrdersIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
