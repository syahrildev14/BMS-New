import { Head, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { errorAlert } from '@/shared/lib/alerts';
import type { Order } from '@/shared/types/prd.type';

type OrderWithCounts = Order & { pending_items_count: number; surat_jalans_count: number };

const columns: TableColumn<OrderWithCounts>[] = [
  { key: 'order_number', accessor: (order) => order.order_number },
  { key: 'dapur', accessor: (order) => order.dapur?.name },
  { key: 'delivery_date', accessor: (order) => order.delivery_date },
  { key: 'pending_items', accessor: (order) => order.pending_items_count },
  { key: 'sj_count', accessor: (order) => order.surat_jalans_count },
];

export default function OpsDeliveryNotesIndex({ orders }: { orders: OrderWithCounts[] }) {
  const [processingId, setProcessingId] = useState<number | null>(null);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(orders, columns);

  const generate = (orderId: number) => {
    setProcessingId(orderId);
    router.post(route('ops.delivery-notes.store'), { order_id: orderId }, {
      onFinish: () => setProcessingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menerbitkan Surat Jalan.'),
    });
  };

  return (
    <>
      <Head title="Surat Jalan" />

      <PageHeader
        title="Generate Surat Jalan"
        description="Order dengan item yang belum memiliki Surat Jalan (PRIMARY pertama, selanjutnya otomatis jadi ADDON)."
        breadcrumbs={[{ label: 'Ops' }, { label: 'Surat Jalan' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari no. order atau dapur..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="No. Order" columnKey="order_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dapur" columnKey="dapur" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Tanggal Kirim" columnKey="delivery_date" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Item Belum Ada SJ" columnKey="pending_items" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="SJ Sudah Terbit" columnKey="sj_count" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="h-32 text-center text-gray-400">
                {orders.length === 0 ? 'Tidak ada order yang menunggu Surat Jalan.' : 'Tidak ada order yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((order) => (
              <TableRow key={order.id}>
                <TableCell className="font-medium">{order.order_number}</TableCell>
                <TableCell>{order.dapur?.name}</TableCell>
                <TableCell>{order.delivery_date}</TableCell>
                <TableCell>{order.pending_items_count}</TableCell>
                <TableCell>{order.surat_jalans_count}</TableCell>
                <TableCell className="text-right">
                  <Button size="sm" onClick={() => generate(order.id)} disabled={processingId === order.id}>
                    {processingId === order.id
                      ? 'Memproses...'
                      : order.surat_jalans_count === 0
                        ? 'Generate SJ Utama'
                        : 'Generate SJ Susulan'}
                  </Button>
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

OpsDeliveryNotesIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
