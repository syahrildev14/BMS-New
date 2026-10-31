import { Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Combobox } from '@/shared/components/ui/combobox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { confirmDelete, errorAlert } from '@/shared/lib/alerts';
import { formatQty, formatRupiah } from '@/shared/lib/format';
import type { Item, Order, Supplier } from '@/shared/types/prd.type';

interface SttLine {
  id: number;
  qty_received: string;
  qty_rejected: string;
  rejection_reason: string;
}

interface NewItemLine {
  key: number;
  item_id: string;
  supplier_id: string;
  qty_ordered: string;
  estimated_price: string;
}

let nextNewItemKey = 1;

function emptyNewItemLine(): NewItemLine {
  return { key: nextNewItemKey++, item_id: '', supplier_id: '', qty_ordered: '', estimated_price: '' };
}

export default function DapurOrdersShow({ order, items, suppliers }: { order: Order; items: Item[]; suppliers: Supplier[] }) {
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

  const [addItemOpen, setAddItemOpen] = useState(false);
  const [newLines, setNewLines] = useState<NewItemLine[]>([emptyNewItemLine()]);
  const [addingItems, setAddingItems] = useState(false);

  const openAddItem = () => {
    setNewLines([emptyNewItemLine()]);
    setAddItemOpen(true);
  };

  const updateNewLine = (key: number, patch: Partial<NewItemLine>) => {
    setNewLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  };

  const onPickNewItem = (key: number, itemId: string) => {
    const item = items.find((i) => String(i.id) === itemId);
    updateNewLine(key, {
      item_id: itemId,
      supplier_id: item?.default_supplier_id ? String(item.default_supplier_id) : '',
      estimated_price: item?.base_selling_price ?? '',
    });
  };

  const addNewLine = () => setNewLines((prev) => [...prev, emptyNewItemLine()]);
  const removeNewLine = (key: number) => setNewLines((prev) => prev.filter((line) => line.key !== key));

  const submitAddItems = () => {
    const payload = newLines
      .filter((line) => line.item_id && line.supplier_id && line.qty_ordered)
      .map((line) => ({
        item_id: Number(line.item_id),
        supplier_id: Number(line.supplier_id),
        qty_ordered: Number(line.qty_ordered),
        estimated_price: Number(line.estimated_price || 0),
      }));

    if (payload.length === 0) return;

    setAddingItems(true);
    router.post(
      route('dapur.orders.items.store', order.id),
      { items: payload },
      {
        onSuccess: () => setAddItemOpen(false),
        onFinish: () => setAddingItems(false),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menambah item.'),
      },
    );
  };

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
          <div className="mb-2 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-700">Item Dipesan</h2>
            {order.status !== 'CANCELLED' && order.status !== 'COMPLETED' && (
              <Button type="button" variant="outline" size="sm" onClick={openAddItem}>
                <Plus className="h-4 w-4" />
                Tambah Item
              </Button>
            )}
          </div>
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
            </div>
          )}
        </div>

        <Link href={route('dapur.orders.index')} className="text-sm text-blue-600 hover:underline">
          &larr; Kembali ke daftar order
        </Link>
      </div>

      <Dialog open={addItemOpen} onOpenChange={setAddItemOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Tambah Item ke Order</DialogTitle>
          </DialogHeader>

          <div className="flex flex-col gap-3">
            {newLines.map((line) => {
              const selectedItem = items.find((i) => String(i.id) === line.item_id);

              return (
                <div key={line.key} className="grid grid-cols-12 items-end gap-2 border-t border-gray-100 pt-3 first:border-t-0 first:pt-0">
                  <div className="col-span-4 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Item</label>
                    <Combobox
                      value={line.item_id}
                      onValueChange={(v) => onPickNewItem(line.key, v)}
                      placeholder="Ketik untuk cari item..."
                      emptyText="Item tidak ditemukan."
                      options={items.map((item) => ({ value: String(item.id), label: `${item.name} (${item.satuan?.name})` }))}
                    />
                  </div>

                  <div className="col-span-3 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Supplier</label>
                    <Combobox
                      value={line.supplier_id}
                      onValueChange={(v) => updateNewLine(line.key, { supplier_id: v })}
                      placeholder="Pilih supplier..."
                      emptyText="Supplier tidak ditemukan."
                      options={suppliers.map((supplier) => ({ value: String(supplier.id), label: supplier.name }))}
                    />
                  </div>

                  <div className="col-span-2 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Qty {selectedItem ? `(${selectedItem.satuan?.name})` : ''}</label>
                    <Input
                      type="number"
                      min="0.01"
                      step="0.01"
                      value={line.qty_ordered}
                      onChange={(e) => updateNewLine(line.key, { qty_ordered: e.target.value })}
                    />
                  </div>

                  <div className="col-span-2 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Estimasi Harga</label>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      value={line.estimated_price}
                      onChange={(e) => updateNewLine(line.key, { estimated_price: e.target.value })}
                    />
                  </div>

                  <div className="col-span-1 flex justify-end">
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => removeNewLine(line.key)}
                      disabled={newLines.length === 1}
                    >
                      <Trash2 className="h-4 w-4 text-red-500" />
                    </Button>
                  </div>
                </div>
              );
            })}

            <Button type="button" variant="outline" size="sm" onClick={addNewLine} className="self-start">
              <Plus className="h-4 w-4" />
              Tambah Baris
            </Button>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setAddItemOpen(false)}>
              Batal
            </Button>
            <Button type="button" onClick={submitAddItems} disabled={addingItems}>
              {addingItems ? 'Menyimpan...' : 'Simpan'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

DapurOrdersShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
