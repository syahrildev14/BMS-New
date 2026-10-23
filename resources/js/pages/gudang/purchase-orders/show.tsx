import { Head, Link, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge, type BadgeProps } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { errorAlert } from '@/shared/lib/alerts';
import { formatQty } from '@/shared/lib/format';
import type { PurchaseOrder } from '@/shared/types/prd.type';

interface ReceivingLine {
  id: number;
  name: string;
  satuan: string;
  qty_ordered: string;
  qty_received_so_far: string;
  qty_received: string;
  unit_cost: string;
}

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
  SENT: 'info',
  PARTIALLY_RECEIVED: 'warning',
};

export default function GudangPurchaseOrdersShow({ date, purchaseOrders }: { date: string; purchaseOrders: PurchaseOrder[] }) {
  const [receiving, setReceiving] = useState<PurchaseOrder | null>(null);
  const [lines, setLines] = useState<ReceivingLine[]>([]);
  const [saving, setSaving] = useState(false);

  const openReceive = (po: PurchaseOrder) => {
    setReceiving(po);
    setLines(
      (po.items ?? [])
        .filter((line) => Number(line.qty_received) < Number(line.qty_ordered))
        .map((line) => ({
          id: line.id,
          name: line.item?.name ?? '-',
          satuan: line.item?.satuan?.name ?? '',
          qty_ordered: line.qty_ordered,
          qty_received_so_far: line.qty_received,
          qty_received: String(Number(line.qty_ordered) - Number(line.qty_received)),
          unit_cost: line.unit_cost,
        })),
    );
  };

  const updateLine = (id: number, patch: Partial<ReceivingLine>) => {
    setLines((prev) => prev.map((line) => (line.id === id ? { ...line, ...patch } : line)));
  };

  const submitReceive = () => {
    if (!receiving) return;
    setSaving(true);
    router.post(
      route('gudang.purchase-orders.receive', receiving.id),
      { items: lines.map((l) => ({ id: l.id, qty_received: l.qty_received, unit_cost: l.unit_cost })) },
      {
        onFinish: () => setSaving(false),
        onSuccess: () => setReceiving(null),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyimpan penerimaan barang.'),
      },
    );
  };

  return (
    <>
      <Head title={`Terima Barang ${date}`} />

      <PageHeader
        title={`Terima Barang — ${date}`}
        description={`${purchaseOrders.length} PO dikirim pada tanggal ini.`}
        breadcrumbs={[{ label: 'Gudang' }, { label: 'Terima Barang', href: route('gudang.purchase-orders.index') }, { label: date }]}
      />

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>No. PO</TableHead>
            <TableHead>Supplier</TableHead>
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {purchaseOrders.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                Tidak ada PO pada tanggal ini.
              </TableCell>
            </TableRow>
          ) : (
            purchaseOrders.map((po) => (
              <TableRow key={po.id}>
                <TableCell className="font-medium">{po.po_number}</TableCell>
                <TableCell>{po.supplier?.name}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[po.status]}>{po.status}</Badge>
                </TableCell>
                <TableCell className="text-right">
                  <Button variant="outline" size="sm" onClick={() => openReceive(po)}>
                    Terima Barang
                  </Button>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <Link href={route('gudang.purchase-orders.index')} className="mt-4 inline-block text-sm text-blue-600 hover:underline">
        &larr; Kembali ke daftar tanggal
      </Link>

      <Dialog open={!!receiving} onOpenChange={(open) => !open && setReceiving(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              Terima Barang — {receiving?.po_number} ({receiving?.supplier?.name})
            </DialogTitle>
          </DialogHeader>

          <div className="flex flex-col gap-3">
            {lines.length === 0 && <p className="text-sm text-gray-400">Semua item di PO ini sudah diterima penuh.</p>}
            {lines.map((line) => (
              <div key={line.id} className="grid grid-cols-12 items-end gap-2 border-b border-gray-100 pb-3 last:border-0">
                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Barang</Label>
                  <p className="text-sm text-gray-700">
                    {line.name} ({line.satuan})
                  </p>
                  <p className="text-xs text-gray-400">Dipesan: {formatQty(line.qty_ordered)}, diterima: {formatQty(line.qty_received_so_far)}</p>
                </div>
                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Qty Diterima Sekarang</Label>
                  <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={line.qty_received}
                    onChange={(e) => updateLine(line.id, { qty_received: e.target.value })}
                  />
                </div>
                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Harga Satuan Aktual</Label>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={line.unit_cost}
                    onChange={(e) => updateLine(line.id, { unit_cost: e.target.value })}
                  />
                </div>
              </div>
            ))}
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setReceiving(null)}>
              Batal
            </Button>
            {lines.length > 0 && (
              <Button type="button" onClick={submitReceive} disabled={saving}>
                {saving ? 'Menyimpan...' : 'Simpan Penerimaan'}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

GudangPurchaseOrdersShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
