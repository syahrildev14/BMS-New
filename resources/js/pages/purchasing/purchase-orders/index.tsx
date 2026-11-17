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

export default function PurchasingPurchaseOrdersIndex({ groups }: { groups: PurchaseOrderDateGroup[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(groups, columns);

  return (
    <>
      <Head title="Purchase Order" />

      <PageHeader
        title="Purchase Order"
        description="PO yang otomatis terbit saat stok Gudang kurang untuk barang Kering — review, sesuaikan qty/harga bila perlu, lalu kirim ke Supplier."
        breadcrumbs={[{ label: 'Purchasing' }, { label: 'Purchase Order' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari tanggal..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-12">No.</TableHead>
            <SortableTableHead label="Tanggal Dibuat" columnKey="date" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jumlah PO" columnKey="po_count" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                {groups.length === 0 ? 'Belum ada Purchase Order.' : 'Tidak ada tanggal yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((group, index) => (
              <TableRow key={group.date}>
                <TableCell className="text-gray-500">{(page - 1) * pageSize + index + 1}</TableCell>
                <TableCell className="font-medium">{group.date}</TableCell>
                <TableCell>{group.po_count}</TableCell>
                <TableCell className="text-right">
                  <Link
                    href={route('purchasing.purchase-orders.by-date', group.date)}
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

PurchasingPurchaseOrdersIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
