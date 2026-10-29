import { Head, Link, router } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Combobox } from '@/shared/components/ui/combobox';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { errorAlert } from '@/shared/lib/alerts';
import { formatRupiah } from '@/shared/lib/format';
import type { Order } from '@/shared/types/prd.type';

interface BillDateGroup {
  date: string;
  bill_count: number;
  total_amount: number;
}

const columns: TableColumn<BillDateGroup>[] = [
  { key: 'date', accessor: (group) => group.date },
  { key: 'bill_count', accessor: (group) => group.bill_count },
  { key: 'total_amount', accessor: (group) => Number(group.total_amount) },
];

/**
 * Accounts Payable counterpart of finance/invoices/index — same shape,
 * billing the Supplier instead of the Dapur. "Order yang siap ditagih" reuses
 * the exact same whereNotNull('qty_received') pool as invoices, since a
 * Supplier bill and a Dapur invoice reconcile the same underlying receipt.
 */
export default function FinanceSupplierBillsIndex({ groups, billableOrders }: { groups: BillDateGroup[]; billableOrders: Order[] }) {
  const [orderId, setOrderId] = useState('');
  const [processing, setProcessing] = useState(false);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(groups, columns);

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    if (!orderId) return;

    setProcessing(true);
    router.post(
      route('finance.supplier-bills.store'),
      { order_id: Number(orderId) },
      {
        onFinish: () => setProcessing(false),
        onSuccess: () => setOrderId(''),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menerbitkan bill.'),
      },
    );
  };

  return (
    <>
      <Head title="Utang Supplier" />

      <PageHeader
        title="Accounts Payable — Utang Supplier"
        description="Rekonsiliasi yang sama dengan invoice Dapur, tapi di sisi cost_price: berapa yang harus dibayar BMS ke Supplier untuk qty yang lolos QC. Dikelompokkan per tanggal barang diterima (delivery_date Order)."
        breadcrumbs={[{ label: 'Finance' }, { label: 'Utang Supplier' }]}
      />

      <form onSubmit={submit} className="mb-6 flex items-end gap-2 rounded-lg border border-gray-200 p-4">
        <div className="flex flex-1 flex-col gap-1.5">
          <label className="text-sm font-medium text-gray-700">Order yang siap ditagih</label>
          <Combobox
            value={orderId}
            onValueChange={setOrderId}
            placeholder="Ketik untuk cari order..."
            emptyText="Order tidak ditemukan."
            options={billableOrders.map((order) => ({
              value: String(order.id),
              label: `${order.order_number} — ${order.dapur?.name} (${order.delivery_date})`,
            }))}
          />
        </div>
        <Button type="submit" disabled={!orderId || processing}>
          {processing ? 'Memproses...' : 'Generate Bill'}
        </Button>
      </form>

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari tanggal..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Tanggal Diterima" columnKey="date" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jumlah Bill" columnKey="bill_count" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Total Gabungan" columnKey="total_amount" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                {groups.length === 0 ? 'Belum ada bill diterbitkan.' : 'Tidak ada tanggal yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((group) => (
              <TableRow key={group.date}>
                <TableCell className="font-medium">{group.date}</TableCell>
                <TableCell>{group.bill_count}</TableCell>
                <TableCell>{formatRupiah(group.total_amount)}</TableCell>
                <TableCell className="text-right">
                  <Link
                    href={route('finance.supplier-bills.by-date', group.date)}
                    className="text-sm font-medium text-blue-600 hover:underline"
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

FinanceSupplierBillsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
