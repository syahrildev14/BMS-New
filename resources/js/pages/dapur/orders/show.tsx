import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { confirmDelete, errorAlert } from '@/shared/lib/alerts';
import { formatQty, formatRupiah } from '@/shared/lib/format';
import type { Order } from '@/shared/types/prd.type';

interface SttLine {
  id: number;
  qty_received: string;
  qty_rejected: string;
  rejection_reason: string;
}

export default function DapurOrdersShow({ order }: { order: Order }) {
  const sttEligible = (order.order_items ?? []).filter((oi) => oi.surat_jalan_id !== null);

  const [lines, setLines] = useState<SttLine[]>(
    sttEligible.map((oi) => ({
      id: oi.id,
      qty_received: oi.qty_received ?? '',
      qty_rejected: oi.qty_rejected ?? '0',
      rejection_reason: oi.rejection_reason ?? '',
    })),
  );
  const [saving, setSaving] = useState(false);

  // Qty can still be corrected while no Surat Jalan has been issued for the
  // line — once a driver is carrying it, the quantity is locked (enforced
  // server-side too, this is just the matching UI state).
  const [qtyDrafts, setQtyDrafts] = useState<Record<number, string>>(
    Object.fromEntries((order.order_items ?? []).filter((oi) => oi.surat_jalan_id === null).map((oi) => [oi.id, oi.qty_ordered])),
  );
  const [savingQtyId, setSavingQtyId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const updateLine = (id: number, patch: Partial<SttLine>) => {
    setLines((prev) => prev.map((line) => (line.id === id ? { ...line, ...patch } : line)));
  };

  const submitStt = () => {
    setSaving(true);
    router.post(
      route('dapur.orders.stt', order.id),
      {
        items: lines.map((l) => ({
          id: l.id,
          qty_received: l.qty_received,
          qty_rejected: l.qty_rejected,
          rejection_reason: l.rejection_reason,
        })),
      },
      {
        onFinish: () => setSaving(false),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyimpan data penerimaan.'),
      },
    );
  };

  const saveQty = (orderItemId: number) => {
    setSavingQtyId(orderItemId);
    router.patch(
      route('dapur.orders.items.update', [order.id, orderItemId]),
      { qty_ordered: qtyDrafts[orderItemId] },
      {
        onFinish: () => setSavingQtyId(null),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyimpan qty.'),
      },
    );
  };

  const deleteItem = async (orderItemId: number) => {
    const ok = await confirmDelete({ title: 'Hapus item ini dari order?' });
    if (!ok) return;

    setDeletingId(orderItemId);
    router.delete(route('dapur.orders.items.destroy', [order.id, orderItemId]), {
      onFinish: () => setDeletingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menghapus item.'),
    });
  };

  return (
    <>
      <Head title={order.order_number} />

      <PageHeader
        title={order.order_number}
        description={`Tanggal kirim: ${order.delivery_date} • Status: ${order.status}`}
        breadcrumbs={[{ label: 'Dapur' }, { label: 'Order Saya', href: route('dapur.orders.index') }, { label: order.order_number }]}
      />

      <div className="flex flex-col gap-8">
        <div>
          <h2 className="mb-2 text-sm font-semibold text-gray-700">Item Dipesan</h2>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Item</TableHead>
                <TableHead>Qty Pesan</TableHead>
                <TableHead>Qty Terima</TableHead>
                <TableHead>Qty Reject</TableHead>
                <TableHead>Alasan Reject</TableHead>
                <TableHead>Harga Estimasi</TableHead>
                <TableHead>Add-on</TableHead>
                <TableHead className="text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(order.order_items ?? []).map((orderItem) => {
                const line = lines.find((l) => l.id === orderItem.id);
                const editable = orderItem.surat_jalan_id !== null && line;
                const noSjYet = orderItem.surat_jalan_id === null;

                return (
                  <TableRow key={orderItem.id}>
                    <TableCell className="font-medium">{orderItem.item?.name}</TableCell>
                    <TableCell>
                      {noSjYet ? (
                        <Input
                          type="number"
                          min="0.01"
                          step="0.01"
                          className="w-24"
                          value={qtyDrafts[orderItem.id] ?? orderItem.qty_ordered}
                          onChange={(e) => setQtyDrafts((prev) => ({ ...prev, [orderItem.id]: e.target.value }))}
                        />
                      ) : (
                        formatQty(orderItem.qty_ordered)
                      )}
                    </TableCell>
                    <TableCell>
                      {editable ? (
                        <Input
                          type="number"
                          min="0"
                          step="0.01"
                          className="w-24"
                          value={line.qty_received}
                          onChange={(e) => updateLine(orderItem.id, { qty_received: e.target.value })}
                        />
                      ) : (
                        formatQty(orderItem.qty_received)
                      )}
                    </TableCell>
                    <TableCell>
                      {editable ? (
                        <Input
                          type="number"
                          min="0"
                          step="0.01"
                          className="w-24"
                          value={line.qty_rejected}
                          onChange={(e) => updateLine(orderItem.id, { qty_rejected: e.target.value })}
                        />
                      ) : (
                        formatQty(orderItem.qty_rejected)
                      )}
                    </TableCell>
                    <TableCell>
                      {editable ? (
                        <Input
                          className="w-40"
                          placeholder="opsional"
                          value={line.rejection_reason}
                          onChange={(e) => updateLine(orderItem.id, { rejection_reason: e.target.value })}
                        />
                      ) : (
                        orderItem.rejection_reason ?? '-'
                      )}
                    </TableCell>
                    <TableCell>{formatRupiah(orderItem.estimated_price)}</TableCell>
                    <TableCell>{orderItem.is_addon ? 'Ya' : 'Tidak'}</TableCell>
                    <TableCell className="text-right">
                      {noSjYet && (
                        <div className="flex justify-end gap-1">
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => saveQty(orderItem.id)}
                            disabled={savingQtyId === orderItem.id}
                          >
                            {savingQtyId === orderItem.id ? 'Menyimpan...' : 'Simpan'}
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => deleteItem(orderItem.id)}
                            disabled={deletingId === orderItem.id}
                          >
                            <Trash2 className="h-4 w-4 text-red-500" />
                          </Button>
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>

          <p className="mt-2 text-xs text-gray-400">
            Qty hanya bisa diedit atau dihapus selama item belum punya Surat Jalan. Setelah SJ terbit, qty terkunci.
          </p>

          {sttEligible.length > 0 && (
            <div className="mt-3 flex items-center justify-between gap-4">
              <p className="text-xs text-gray-400">
                Isi qty yang diterima &amp; ditolak sesuai STT fisik untuk item yang sudah ada Surat Jalan-nya, lalu simpan.
              </p>
              <Button type="button" onClick={submitStt} disabled={saving}>
                {saving ? 'Menyimpan...' : 'Simpan Penerimaan (STT)'}
              </Button>
            </div>
          )}
        </div>

        <div>
          <h2 className="mb-2 text-sm font-semibold text-gray-700">Surat Jalan &amp; Tanda Terima</h2>
          {(order.surat_jalans ?? []).length === 0 ? (
            <p className="text-sm text-gray-400">Belum ada Surat Jalan diterbitkan untuk order ini.</p>
          ) : (
            <div className="flex flex-col gap-3 rounded-md border border-gray-200 p-4 dark:border-gray-800">
              <p className="text-sm text-gray-600 dark:text-gray-300">
                {(order.surat_jalans ?? []).length} pengiriman: {(order.surat_jalans ?? []).map((sj) => sj.sj_number).join(', ')}
              </p>
              <p className="text-xs text-gray-400">
                Dokumen berikut digabung otomatis untuk seluruh pengiriman pada order ini — tidak perlu download satu-satu per SJ.
              </p>
              <div className="flex gap-4">
                {order.pdf_url ? (
                  <a href={route('dapur.orders.surat-jalan.download', order.id)} className="text-sm font-medium text-blue-600 hover:underline">
                    Download Surat Jalan
                  </a>
                ) : (
                  <span className="text-sm text-gray-400">Surat Jalan belum tersedia</span>
                )}
                {order.tanda_terima_pdf_url ? (
                  <a
                    href={route('dapur.orders.tanda-terima.download', order.id)}
                    className="text-sm font-medium text-emerald-600 hover:underline"
                  >
                    Download Tanda Terima
                  </a>
                ) : (
                  <span className="text-sm text-gray-400">Tanda Terima belum tersedia</span>
                )}
              </div>
            </div>
          )}
        </div>

        <Link href={route('dapur.orders.index')} className="text-sm text-blue-600 hover:underline">
          &larr; Kembali ke daftar order
        </Link>
      </div>
    </>
  );
}

DapurOrdersShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
