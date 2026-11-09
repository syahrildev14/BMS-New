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
import type { BankAccount, DocumentTemplateOption, Order } from '@/shared/types/prd.type';

type BillableOrder = Order & { invoices?: { id: number; document_template_id: number | null; bank_account: string | null }[] };

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

export default function FinanceInvoicesIndex({
  groups,
  billableOrders,
  documentTemplates,
  bankAccounts,
}: {
  groups: InvoiceDateGroup[];
  billableOrders: BillableOrder[];
  documentTemplates: DocumentTemplateOption[];
  bankAccounts: Pick<BankAccount, 'id' | 'bank_name' | 'account_number' | 'account_holder'>[];
}) {
  const [orderId, setOrderId] = useState('');
  const [templateId, setTemplateId] = useState('');
  const [bankAccountId, setBankAccountId] = useState('');
  const [processing, setProcessing] = useState(false);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(groups, columns);

  const selectedTemplate = documentTemplates.find((template) => String(template.id) === templateId);
  const needsBank = selectedTemplate?.placeholders.some((name) => name.startsWith('bank_')) ?? false;
  const canGenerate = !!orderId && (documentTemplates.length === 0 || !!selectedTemplate) && (!needsBank || !!bankAccountId);

  // Regenerating keeps the previous choice, when it is still selectable.
  const selectOrder = (value: string) => {
    setOrderId(value);

    const previous = billableOrders.find((order) => String(order.id) === value)?.invoices?.findLast((invoice) => invoice.document_template_id !== null);
    const template = documentTemplates.find((option) => option.id === previous?.document_template_id);
    const bank = bankAccounts.find((account) => account.account_number === previous?.bank_account);

    setTemplateId(template ? String(template.id) : '');
    setBankAccountId(bank ? String(bank.id) : '');
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    if (!canGenerate) return;

    setProcessing(true);
    router.post(
      route('finance.invoices.store'),
      {
        order_id: Number(orderId),
        document_template_id: selectedTemplate?.id ?? null,
        bank_account_id: needsBank ? Number(bankAccountId) : null,
      },
      {
        onFinish: () => setProcessing(false),
        onSuccess: () => selectOrder(''),
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

      <form onSubmit={submit} className="mb-6 flex flex-wrap items-end gap-2 rounded-lg border border-gray-200 p-4">
        <div className="flex min-w-64 flex-1 flex-col gap-1.5">
          <label className="text-sm font-medium text-gray-700">Order yang siap ditagih</label>
          <Combobox
            value={orderId}
            onValueChange={selectOrder}
            placeholder="Ketik untuk cari order..."
            emptyText="Order tidak ditemukan."
            options={billableOrders.map((order) => ({
              value: String(order.id),
              label: `${order.order_number} — ${order.dapur?.name} (${order.delivery_date})`,
            }))}
          />
        </div>
        {documentTemplates.length > 0 && (
          <div className="flex min-w-48 flex-col gap-1.5">
            <label className="text-sm font-medium text-gray-700">Template Invoice</label>
            <Combobox
              value={templateId}
              onValueChange={setTemplateId}
              placeholder="Pilih template..."
              emptyText="Template tidak ditemukan."
              options={documentTemplates.map((template) => ({ value: String(template.id), label: template.name }))}
            />
          </div>
        )}
        {needsBank && (
          <div className="flex min-w-48 flex-col gap-1.5">
            <label className="text-sm font-medium text-gray-700">Rekening Bank</label>
            <Combobox
              value={bankAccountId}
              onValueChange={setBankAccountId}
              placeholder="Pilih rekening..."
              emptyText="Rekening tidak ditemukan."
              options={bankAccounts.map((account) => ({ value: String(account.id), label: `${account.bank_name} ${account.account_number}` }))}
            />
          </div>
        )}
        <Button type="submit" disabled={!canGenerate || processing}>
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
