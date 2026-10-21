import { Head, router } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge, type BadgeProps } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { errorAlert } from '@/shared/lib/alerts';
import type { Invoice, Order } from '@/shared/types/prd.type';

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
  DRAFT: 'default',
  ISSUED: 'info',
  PAID: 'success',
  OVERDUE: 'destructive',
  VOID: 'outline',
};

const columns: TableColumn<Invoice>[] = [
  { key: 'invoice_number', accessor: (invoice) => invoice.invoice_number },
  { key: 'order', accessor: (invoice) => invoice.order?.order_number },
  { key: 'dapur', accessor: (invoice) => invoice.dapur?.name },
  { key: 'supplier', accessor: (invoice) => invoice.supplier?.name },
  { key: 'total', accessor: (invoice) => Number(invoice.total_amount) },
  { key: 'status', accessor: (invoice) => invoice.status },
];

export default function FinanceInvoicesIndex({ invoices, billableOrders }: { invoices: Invoice[]; billableOrders: Order[] }) {
  const [orderId, setOrderId] = useState('');
  const [processing, setProcessing] = useState(false);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(invoices, columns);

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
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menerbitkan invoice.'),
      },
    );
  };

  return (
    <>
      <Head title="Invoice" />

      <PageHeader
        title="Reconciled Split Billing"
        description="Rekonsiliasi seluruh Surat Jalan (Utama + Susulan) per Order, dipecah tagihannya per Supplier berdasarkan qty yang lolos QC."
        breadcrumbs={[{ label: 'Finance' }, { label: 'Invoice' }]}
      />

      <form onSubmit={submit} className="mb-6 flex items-end gap-2 rounded-lg border border-gray-200 p-4">
        <div className="flex flex-1 flex-col gap-1.5">
          <label className="text-sm font-medium text-gray-700">Order yang siap ditagih</label>
          <Select value={orderId} onValueChange={setOrderId}>
            <SelectTrigger>
              <SelectValue placeholder="Pilih order..." />
            </SelectTrigger>
            <SelectContent>
              {billableOrders.map((order) => (
                <SelectItem key={order.id} value={String(order.id)}>
                  {order.order_number} — {order.dapur?.name} ({order.delivery_date})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <Button type="submit" disabled={!orderId || processing}>
          {processing ? 'Memproses...' : 'Generate Split Invoice'}
        </Button>
      </form>

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari no. invoice, order, dapur, atau supplier..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="No. Invoice" columnKey="invoice_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Order" columnKey="order" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dapur" columnKey="dapur" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Supplier" columnKey="supplier" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Total" columnKey="total" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Status" columnKey="status" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">PDF</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7} className="h-32 text-center text-gray-400">
                {invoices.length === 0 ? 'Belum ada invoice diterbitkan.' : 'Tidak ada invoice yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((invoice) => (
              <TableRow key={invoice.id}>
                <TableCell className="font-medium">{invoice.invoice_number}</TableCell>
                <TableCell>{invoice.order?.order_number}</TableCell>
                <TableCell>{invoice.dapur?.name}</TableCell>
                <TableCell>{invoice.supplier?.name}</TableCell>
                <TableCell>{invoice.total_amount}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[invoice.status]}>{invoice.status}</Badge>
                </TableCell>
                <TableCell className="text-right">
                  {invoice.pdf_url ? (
                    <a
                      href={route('finance.invoices.download', invoice.id)}
                      className="text-sm font-medium text-blue-600 hover:underline"
                    >
                      Download
                    </a>
                  ) : (
                    <span className="text-sm text-gray-400">—</span>
                  )}
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
