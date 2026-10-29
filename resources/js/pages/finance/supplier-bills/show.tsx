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
import type { SupplierBill } from '@/shared/types/prd.type';
import type { SharedData } from '@/types';

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
  DRAFT: 'default',
  ISSUED: 'info',
  PAID: 'success',
  OVERDUE: 'destructive',
  VOID: 'outline',
};

export default function FinanceSupplierBillsShow({ date, bills }: { date: string; bills: SupplierBill[] }) {
  const { auth } = usePage<SharedData>().props;
  const canApprove = auth.permissions.includes('supplier-bills.approve');
  const [processingId, setProcessingId] = useState<number | null>(null);
  const [payingBill, setPayingBill] = useState<SupplierBill | null>(null);
  const [paymentMethod, setPaymentMethod] = useState('');
  const [paymentReference, setPaymentReference] = useState('');
  const [paidAt, setPaidAt] = useState(() => new Date().toISOString().slice(0, 10));
  const [paying, setPaying] = useState(false);

  const openPayDialog = (bill: SupplierBill) => {
    setPayingBill(bill);
    setPaymentMethod('');
    setPaymentReference('');
    setPaidAt(new Date().toISOString().slice(0, 10));
  };

  const submitPayment: FormEventHandler = (e) => {
    e.preventDefault();
    if (!payingBill) return;

    setPaying(true);
    router.post(
      route('finance.supplier-bills.pay', payingBill.id),
      { payment_method: paymentMethod, payment_reference: paymentReference || null, paid_at: paidAt },
      {
        onSuccess: () => {
          successAlert('Bill ditandai lunas.');
          setPayingBill(null);
        },
        onFinish: () => setPaying(false),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menandai lunas.'),
      },
    );
  };

  const approve = (bill: SupplierBill) => {
    setProcessingId(bill.id);
    router.post(route('finance.supplier-bills.approve', bill.id), undefined, {
      onFinish: () => setProcessingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyetujui bill.'),
    });
  };

  const reject = (bill: SupplierBill) => {
    setProcessingId(bill.id);
    router.post(route('finance.supplier-bills.reject', bill.id), undefined, {
      onFinish: () => setProcessingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menolak bill.'),
    });
  };

  return (
    <>
      <Head title={`Utang Supplier ${date}`} />

      <PageHeader
        title={`Utang Supplier — Diterima ${date}`}
        description={`${bills.length} bill untuk Order dengan tanggal terima (delivery_date) ini.`}
        breadcrumbs={[{ label: 'Finance' }, { label: 'Utang Supplier', href: route('finance.supplier-bills.index') }, { label: date }]}
      />

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>No. Bill</TableHead>
            <TableHead>Order</TableHead>
            <TableHead>Dapur</TableHead>
            <TableHead>Supplier</TableHead>
            <TableHead>Total</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Jatuh Tempo</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {bills.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="h-32 text-center text-gray-400">
                Tidak ada bill pada tanggal ini.
              </TableCell>
            </TableRow>
          ) : (
            bills.map((bill) => (
              <TableRow key={bill.id}>
                <TableCell className="font-medium">{bill.bill_number}</TableCell>
                <TableCell>{bill.order?.order_number}</TableCell>
                <TableCell>{bill.dapur?.name}</TableCell>
                <TableCell>{bill.supplier?.name}</TableCell>
                <TableCell>{formatRupiah(bill.total_amount)}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[bill.status]}>{bill.status}</Badge>
                </TableCell>
                <TableCell>
                  {bill.status === 'PAID' ? (
                    <span className="text-xs text-gray-400">Lunas {bill.paid_at?.slice(0, 10)}</span>
                  ) : (
                    (bill.due_date ?? '—')
                  )}
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-1">
                    {canApprove && bill.status === 'DRAFT' && (
                      <>
                        <Button type="button" size="sm" onClick={() => approve(bill)} disabled={processingId === bill.id}>
                          Setujui
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => reject(bill)}
                          disabled={processingId === bill.id}
                        >
                          Tolak
                        </Button>
                      </>
                    )}
                    {(bill.status === 'ISSUED' || bill.status === 'OVERDUE') && (
                      <Button type="button" size="sm" onClick={() => openPayDialog(bill)}>
                        Tandai Lunas
                      </Button>
                    )}
                    {!canApprove && bill.status === 'DRAFT' && <span className="text-sm text-gray-400">Menunggu approval</span>}
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <Link href={route('finance.supplier-bills.index')} className="mt-4 inline-block text-sm text-blue-600 hover:underline">
        &larr; Kembali ke daftar tanggal
      </Link>

      <Dialog open={payingBill !== null} onOpenChange={(open) => !open && setPayingBill(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Tandai Lunas — {payingBill?.bill_number}</DialogTitle>
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
              <Button type="button" variant="outline" onClick={() => setPayingBill(null)}>
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

FinanceSupplierBillsShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
