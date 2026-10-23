import { Head, Link, router, usePage } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge, type BadgeProps } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { errorAlert } from '@/shared/lib/alerts';
import { formatRupiah } from '@/shared/lib/format';
import type { Invoice } from '@/shared/types/prd.type';
import type { SharedData } from '@/types';

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
  DRAFT: 'default',
  ISSUED: 'info',
  PAID: 'success',
  OVERDUE: 'destructive',
  VOID: 'outline',
};

export default function FinanceInvoicesShow({ date, invoices }: { date: string; invoices: Invoice[] }) {
  const { auth } = usePage<SharedData>().props;
  const canApprove = auth.permissions.includes('invoices.approve');
  const [processingId, setProcessingId] = useState<number | null>(null);

  const approve = (invoice: Invoice) => {
    setProcessingId(invoice.id);
    router.post(route('finance.invoices.approve', invoice.id), undefined, {
      onFinish: () => setProcessingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyetujui invoice.'),
    });
  };

  const reject = (invoice: Invoice) => {
    setProcessingId(invoice.id);
    router.post(route('finance.invoices.reject', invoice.id), undefined, {
      onFinish: () => setProcessingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menolak invoice.'),
    });
  };

  return (
    <>
      <Head title={`Invoice ${date}`} />

      <PageHeader
        title={`Invoice — Diterima ${date}`}
        description={`${invoices.length} invoice untuk Order dengan tanggal terima (delivery_date) ini.`}
        breadcrumbs={[{ label: 'Finance' }, { label: 'Invoice', href: route('finance.invoices.index') }, { label: date }]}
      />

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>No. Invoice</TableHead>
            <TableHead>Order</TableHead>
            <TableHead>Dapur</TableHead>
            <TableHead>Supplier</TableHead>
            <TableHead>Total</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>PDF</TableHead>
            {canApprove && <TableHead className="text-right">Aksi</TableHead>}
          </TableRow>
        </TableHeader>
        <TableBody>
          {invoices.length === 0 ? (
            <TableRow>
              <TableCell colSpan={canApprove ? 8 : 7} className="h-32 text-center text-gray-400">
                Tidak ada invoice pada tanggal ini.
              </TableCell>
            </TableRow>
          ) : (
            invoices.map((invoice) => (
              <TableRow key={invoice.id}>
                <TableCell className="font-medium">{invoice.invoice_number}</TableCell>
                <TableCell>{invoice.order?.order_number}</TableCell>
                <TableCell>{invoice.dapur?.name}</TableCell>
                <TableCell>{invoice.supplier?.name}</TableCell>
                <TableCell>{formatRupiah(invoice.total_amount)}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[invoice.status]}>{invoice.status}</Badge>
                </TableCell>
                <TableCell>
                  {invoice.pdf_url ? (
                    <a
                      href={route('finance.invoices.download', invoice.id)}
                      className="text-sm font-medium text-blue-600 hover:underline"
                    >
                      Cetak PDF
                    </a>
                  ) : (
                    <span className="text-sm text-gray-400">—</span>
                  )}
                </TableCell>
                {canApprove && (
                  <TableCell className="text-right">
                    {invoice.status === 'DRAFT' ? (
                      <div className="flex justify-end gap-1">
                        <Button
                          type="button"
                          size="sm"
                          onClick={() => approve(invoice)}
                          disabled={processingId === invoice.id}
                        >
                          Setujui
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => reject(invoice)}
                          disabled={processingId === invoice.id}
                        >
                          Tolak
                        </Button>
                      </div>
                    ) : (
                      <span className="text-sm text-gray-400">—</span>
                    )}
                  </TableCell>
                )}
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <Link href={route('finance.invoices.index')} className="mt-4 inline-block text-sm text-blue-600 hover:underline">
        &larr; Kembali ke daftar tanggal
      </Link>
    </>
  );
}

FinanceInvoicesShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
