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
import { errorAlert, listAlert } from '@/shared/lib/alerts';
import { formatRupiah } from '@/shared/lib/format';
import type { Order } from '@/shared/types/prd.type';

interface InvoiceDateGroup {
  date: string;
  invoice_count: number;
  total_amount: number;
}

const columns: TableColumn<InvoiceDateGroup>[] = [
  { key: 'date', accessor: (group) => group.date },
  { key: 'invoice_count', accessor: (group) => group.invoice_count },
  { key: 'total_amount', accessor: (group) => Number(group.total_amount) },
];

export default function FinanceInvoicesIndex({ groups, billableOrders }: { groups: InvoiceDateGroup[]; billableOrders: Order[] }) {
  const [orderId, setOrderId] = useState('');
  const [processing, setProcessing] = useState(false);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(groups, columns);

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    if (!orderId) return;

    setProcessing(true);
    router.post(
      route('finance.invoices.store'),
      { order_id: Number(orderId) },
      {
        onFinish: () => setProcessing(false),
        onSuccess: () => setOrderId(''),
        onError: (errors) => {
          if (errors.unpriced_items) {
            listAlert(
              errors.order_id ?? 'Sebagian item belum punya harga.',
              errors.unpriced_items.split(', '),
              'Harga Harian Belum Diinput',
            );
            return;
          }

          errorAlert(Object.values(errors)[0] ?? 'Gagal menerbitkan invoice.');
        },
      },
    );
  };

  return (
    <>
      <Head title="Invoice" />

      <PageHeader
        title="Reconciled Split Billing"
        description="Rekonsiliasi seluruh Surat Jalan (Utama + Susulan) per Order, dipecah tagihannya per Supplier berdasarkan qty yang lolos QC. Dikelompokkan per tanggal barang diterima (delivery_date Order), bukan tanggal invoice dibuat."
        breadcrumbs={[{ label: 'Finance' }, { label: 'Invoice' }]}
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
          {processing ? 'Memproses...' : 'Generate Split Invoice'}
        </Button>
      </form>

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari tanggal..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Tanggal Diterima" columnKey="date" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jumlah Invoice" columnKey="invoice_count" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Total Gabungan" columnKey="total_amount" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                {groups.length === 0 ? 'Belum ada invoice diterbitkan.' : 'Tidak ada tanggal yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((group) => (
              <TableRow key={group.date}>
                <TableCell className="font-medium">{group.date}</TableCell>
                <TableCell>{group.invoice_count}</TableCell>
                <TableCell>{formatRupiah(group.total_amount)}</TableCell>
                <TableCell className="text-right">
                  <Link
                    href={route('finance.invoices.by-date', group.date)}
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

FinanceInvoicesIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
