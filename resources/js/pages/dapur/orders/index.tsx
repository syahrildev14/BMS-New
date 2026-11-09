import { Head, Link } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge, type BadgeProps } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import type { Order } from '@/shared/types/prd.type';

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
  DRAFT: 'default',
  SUBMITTED: 'info',
  LOCKED: 'warning',
  PROCESSING: 'info',
  DELIVERED: 'info',
  COMPLETED: 'success',
  CANCELLED: 'destructive',
};

const columns: TableColumn<Order>[] = [
  { key: 'order_number', accessor: (order) => order.order_number },
  { key: 'delivery_date', accessor: (order) => order.delivery_date },
  { key: 'item_count', accessor: (order) => order.order_items?.length ?? 0 },
  { key: 'status', accessor: (order) => order.status },
];

export default function DapurOrdersIndex({ orders }: { orders: Order[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(orders, columns);

  return (
    <>
      <Head title="Order Saya" />

      <PageHeader
        title="Order Saya"
        description="Daftar pre-order harian yang sudah Anda kirim ke Ops."
        breadcrumbs={[{ label: 'Dapur' }, { label: 'Order Saya' }]}
        actions={
          <Button asChild>
            <Link href={route('dapur.orders.create')}>+ Buat Order</Link>
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari no. order, tanggal, atau status..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="No. Order" columnKey="order_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Tanggal Kirim" columnKey="delivery_date" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jumlah Item" columnKey="item_count" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Status" columnKey="status" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="h-32 text-center text-gray-400">
                {orders.length === 0 ? 'Belum ada order.' : 'Tidak ada order yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((order) => (
              <TableRow key={order.id}>
                <TableCell className="font-medium">{order.order_number}</TableCell>
                <TableCell>{order.delivery_date}</TableCell>
                <TableCell>{order.order_items?.length ?? 0}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[order.status]}>{order.status}</Badge>
                </TableCell>
                <TableCell className="text-right">
                  <Link href={route('dapur.orders.show', order.id)} className="text-sm text-primary-text hover:underline">
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

DapurOrdersIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
