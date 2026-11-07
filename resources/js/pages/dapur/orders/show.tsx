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
import type { Item, Order } from '@/shared/types/prd.type';

interface SttLine {
  id: number;
  qty_received: string;
  qty_rejected: string;
  rejection_reason: string;
}

// Dapur only chooses item + qty — supplier and price are filled in by the
// server from the item master.
interface NewItemLine {
  key: number;
  item_id: string;
  qty_ordered: string;
}

let nextNewItemKey = 1;

function emptyNewItemLine(): NewItemLine {
  return { key: nextNewItemKey++, item_id: '', qty_ordered: '' };
}

export default function DapurOrdersShow({ order, items }: { order: Order; items: Item[] }) {
  // Lines on a document the koperasi already verified are locked (enforced
  // server-side too; this is the matching UI state).
  const verifiedDocumentIds = new Set((order.surat_jalans ?? []).filter((doc) => doc.verification_status === 'VERIFIED').map((doc) => doc.id));
  const sttEligible = (order.order_items ?? []).filter((oi) => oi.surat_jalan_id !== null && !verifiedDocumentIds.has(oi.surat_jalan_id));

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

  const addNewLine = () => setNewLines((prev) => [...prev, emptyNewItemLine()]);
  const removeNewLine = (key: number) => setNewLines((prev) => prev.filter((line) => line.key !== key));

  const submitAddItems = () => {
    const payload = newLines
      .filter((line) => line.item_id && line.qty_ordered)
      .map((line) => ({
        item_id: Number(line.item_id),
        qty_ordered: Number(line.qty_ordered),
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

  const [uploadingDocId, setUploadingDocId] = useState<number | null>(null);

  const uploadPhotos = (documentId: number, files: FileList | null) => {
    if (!files || files.length === 0) return;

    // Matches the server limit; a larger request is refused by nginx/PHP
    // before Laravel can answer with a readable message.
    const totalBytes = Array.from(files).reduce((sum, file) => sum + file.size, 0);

    if (totalBytes > 20 * 1024 * 1024) {
      errorAlert('Total foto dalam sekali unggah maksimal 20 MB. Unggah sebagian dulu.');
      return;
    }

    setUploadingDocId(documentId);
    router.post(
      route('dapur.documents.photos.store', documentId),
      { photos: Array.from(files) },
      {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onFinish: () => setUploadingDocId(null),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal mengunggah foto.'),
      },
    );
  };

  const deletePhoto = async (documentId: number, photoId: number) => {
    const ok = await confirmDelete({ title: 'Hapus foto ini?' });
    if (!ok) return;

    router.delete(route('dapur.documents.photos.destroy', [documentId, photoId]), {
      preserveScroll: true,
      preserveState: true,
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menghapus foto.'),
    });
  };

  const updateLine = (id: number, patch: Partial<SttLine>) => {
    setLines((prev) => prev.map((line) => (line.id === id ? { ...line, ...patch } : line)));
  };

  const submitStt = () => {
    // Only lines typed in: another document on this Order may not have arrived yet.
    const filled = lines.filter((l) => l.qty_received !== '');

    if (filled.length === 0) {
      errorAlert('Isi qty diterima untuk barang yang sudah datang.');
      return;
    }

    setSaving(true);
    router.post(
      route('dapur.orders.stt', order.id),
      {
        items: filled.map((l) => ({
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
            <p className="text-sm text-gray-400">Belum ada dokumen pengiriman untuk order ini.</p>
          ) : (
            <div className="flex flex-col divide-y divide-gray-100 rounded-md border border-gray-200 dark:border-gray-800">
              {(order.surat_jalans ?? []).map((doc) => (
                <div key={doc.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                  <div className="flex flex-col gap-1">
                    <span className="font-medium">{doc.sj_number}</span>
                    <span className="text-xs text-gray-500">
                      {doc.document_type === 'TANDA_TERIMA' ? 'Tanda Terima bahan basah' : 'Surat Jalan bahan kering'}
                      {doc.supplier ? ` — ${doc.supplier.name}` : ''}
                      {doc.shipment_date ? ` — kirim ${doc.shipment_date}` : ''}
                    </span>
                    {doc.verification_status === 'VERIFIED' && <span className="text-xs font-medium text-green-700">Terverifikasi koperasi</span>}
                    {doc.verification_status === 'DISCREPANCY' && (
                      <span className="text-xs font-medium text-amber-700">Ada selisih: {doc.verification_notes}</span>
                    )}
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                      {(doc.photos ?? []).map((photo) => (
                        <span key={photo.id} className="flex items-center gap-1">
                          <a href={route('dapur.documents.photos.show', [doc.id, photo.id])} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">
                            {photo.original_name}
                          </a>
                          {doc.verification_status !== 'VERIFIED' && (
                            <button type="button" aria-label={`Hapus foto ${photo.original_name}`} onClick={() => deletePhoto(doc.id, photo.id)}>
                              <Trash2 className="h-3 w-3 text-red-500" />
                            </button>
                          )}
                        </span>
                      ))}
                      {doc.verification_status !== 'VERIFIED' && (
                        <label className="cursor-pointer font-medium text-blue-600 hover:underline">
                          {uploadingDocId === doc.id ? 'Mengunggah...' : '+ Unggah Foto'}
                          <input
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp"
                            className="hidden"
                            disabled={uploadingDocId !== null}
                            onChange={(e) => {
                              uploadPhotos(doc.id, e.target.files);
                              e.target.value = '';
                            }}
                          />
                        </label>
                      )}
                    </div>
                  </div>
                  <div className="flex gap-3">
                    <a href={route('dapur.documents.download', doc.id)} className="text-xs font-medium text-blue-600 hover:underline">
                      Unduh Dokumen
                    </a>
                    {doc.tanda_terima_pdf_url && (
                      <a
                        href={route('dapur.documents.tanda-terima.download', doc.id)}
                        className="text-xs font-medium text-emerald-600 hover:underline"
                      >
                        Tanda Terima (hasil STT)
                      </a>
                    )}
                  </div>
                </div>
              ))}
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
                  <div className="col-span-7 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Item</label>
                    <Combobox
                      value={line.item_id}
                      onValueChange={(v) => updateNewLine(line.key, { item_id: v })}
                      placeholder="Ketik untuk cari item..."
                      emptyText="Item tidak ditemukan."
                      options={items.map((item) => ({ value: String(item.id), label: `${item.name} (${item.satuan?.name})` }))}
                    />
                  </div>

                  <div className="col-span-4 flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-700">Qty {selectedItem ? `(${selectedItem.satuan?.name})` : ''}</label>
                    <Input
                      type="number"
                      min="0.01"
                      step="0.01"
                      value={line.qty_ordered}
                      onChange={(e) => updateNewLine(line.key, { qty_ordered: e.target.value })}
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
