import { Head, Link, router, usePage } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge, type BadgeProps } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { errorAlert, successAlert } from '@/shared/lib/alerts';
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
  const [payingInvoice, setPayingInvoice] = useState<Invoice | null>(null);
  const [paymentMethod, setPaymentMethod] = useState('');
  const [paymentReference, setPaymentReference] = useState('');
  const [paidAt, setPaidAt] = useState(() => new Date().toISOString().slice(0, 10));
  const [paying, setPaying] = useState(false);

  const openPayDialog = (invoice: Invoice) => {
    setPayingInvoice(invoice);
    setPaymentMethod('');
    setPaymentReference('');
    setPaidAt(new Date().toISOString().slice(0, 10));
  };

  const submitPayment: FormEventHandler = (e) => {
    e.preventDefault();
    if (!payingInvoice) return;

    setPaying(true);
    router.post(
      route('finance.invoices.pay', payingInvoice.id),
      { payment_method: paymentMethod, payment_reference: paymentReference || null, paid_at: paidAt },
      {
        onSuccess: () => {
          successAlert('Invoice ditandai lunas.');
          setPayingInvoice(null);
        },
        onFinish: () => setPaying(false),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menandai lunas.'),
      },
    );
  };

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
            <TableHead>Jatuh Tempo</TableHead>
            <TableHead>PDF</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {invoices.length === 0 ? (
            <TableRow>
              <TableCell colSpan={9} className="h-32 text-center text-gray-400">
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
                  {invoice.status === 'PAID' ? (
                    <span className="text-xs text-gray-400">Lunas {invoice.paid_at?.slice(0, 10)}</span>
                  ) : (
                    (invoice.due_date ?? '—')
                  )}
                </TableCell>
                <TableCell>
                  {invoice.pdf_url ? (
                    <a
                      href={route('finance.invoices.download', invoice.id)}
                      className="text-sm font-medium text-primary-text hover:underline"
                    >
                      Cetak PDF
                    </a>
                  ) : (
                    <span className="text-sm text-gray-400">—</span>
                  )}
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-1">
                    {canApprove && invoice.status === 'DRAFT' && (
                      <>
                        <Button type="button" size="sm" onClick={() => approve(invoice)} disabled={processingId === invoice.id}>
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
                      </>
                    )}
                    {(invoice.status === 'ISSUED' || invoice.status === 'OVERDUE') && (
                      <Button type="button" size="sm" onClick={() => openPayDialog(invoice)}>
                        Tandai Lunas
                      </Button>
                    )}
                    {!canApprove && invoice.status === 'DRAFT' && <span className="text-sm text-gray-400">—</span>}
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <Link href={route('finance.invoices.index')} className="mt-4 inline-block text-sm text-primary-text hover:underline">
        &larr; Kembali ke daftar tanggal
      </Link>

      <Dialog open={payingInvoice !== null} onOpenChange={(open) => !open && setPayingInvoice(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Tandai Lunas — {payingInvoice?.invoice_number}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submitPayment} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="payment_method">Metode Pembayaran</Label>
              <Input
                id="payment_method"
                required
                placeholder="Transfer Bank BCA, Tunai, dll."
                value={paymentMethod}
                onChange={(e) => setPaymentMethod(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="payment_reference">No. Referensi (opsional)</Label>
              <Input
                id="payment_reference"
                placeholder="No. transaksi/bukti transfer"
                value={paymentReference}
                onChange={(e) => setPaymentReference(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="paid_at">Tanggal Dibayar</Label>
              <Input id="paid_at" type="date" required value={paidAt} onChange={(e) => setPaidAt(e.target.value)} />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setPayingInvoice(null)}>
                Batal
              </Button>
              <Button type="submit" disabled={paying || !paymentMethod}>
                {paying ? 'Menyimpan...' : 'Tandai Lunas'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

FinanceInvoicesShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
