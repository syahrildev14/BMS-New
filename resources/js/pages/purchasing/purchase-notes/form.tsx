import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { Trash2 } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Combobox } from '@/shared/components/ui/combobox';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { formatQty, formatRupiah } from '@/shared/lib/format';

type Mode = 'DRY_GOODS' | 'WET_GOODS';

interface NoteItem {
  id: number;
  item_id: number;
  order_item_id: number | null;
  qty: string;
  unit_price: string;
}

interface Note {
  id: number;
  note_number: string;
  supplier_id: number;
  note_date: string;
  reference_number: string | null;
  notes: string | null;
  items: NoteItem[];
}

interface TandaTerima {
  id: number;
  sj_number: string;
  shipment_date: string;
  dapur_name: string;
  supplier_name: string;
  lines: { order_item_id: number; item_name: string; satuan: string | null; order_number: string; qty_ordered: number; last_cost_price: number }[];
}

interface DryItemOption {
  id: number;
  name: string;
  base_cost_price: string;
  satuan?: { name: string };
}

interface LineForm {
  key: number;
  item_id: string;
  order_item_id: number | null;
  qty: string;
  unit_price: string;
}

let nextKey = 1;

export default function PurchaseNoteForm({
  note,
  mode,
  editBlockReason,
  today,
  tandaTerima,
  suppliers,
  items,
}: {
  note: Note | null;
  mode: Mode;
  editBlockReason: string | null;
  today: string;
  tandaTerima: TandaTerima | null;
  suppliers: { id: number; name: string }[];
  items: DryItemOption[];
}) {
  const isWet = mode === 'WET_GOODS';
  const readOnly = editBlockReason !== null;

  const [lines, setLines] = useState<LineForm[]>(() => {
    if (isWet && tandaTerima) {
      return tandaTerima.lines.map((line) => {
        const existing = note?.items.find((i) => i.order_item_id === line.order_item_id);
        return {
          key: nextKey++,
          item_id: '',
          order_item_id: line.order_item_id,
          qty: existing ? String(Number(existing.qty)) : String(line.qty_ordered),
          unit_price: existing ? String(Number(existing.unit_price)) : '',
        };
      });
    }
    if (note) {
      return note.items.map((i) => ({ key: nextKey++, item_id: String(i.item_id), order_item_id: null, qty: String(Number(i.qty)), unit_price: String(Number(i.unit_price)) }));
    }
    return [{ key: nextKey++, item_id: '', order_item_id: null, qty: '', unit_price: '' }];
  });

  const form = useForm({
    supplier_id: note ? String(note.supplier_id) : '',
    note_date: note?.note_date ?? today,
    reference_number: note?.reference_number ?? '',
    notes: note?.notes ?? '',
    items: [] as { item_id?: number; order_item_id?: number | null; qty: string; unit_price: string }[],
  });
  const errors = form.errors as Record<string, string | undefined>;

  const updateLine = (key: number, patch: Partial<LineForm>) => setLines((prev) => prev.map((l) => (l.key === key ? { ...l, ...patch } : l)));

  const total = lines.reduce((sum, l) => sum + Number(l.qty || 0) * Number(l.unit_price || 0), 0);

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    // Sent as typed: a blank field must fail validation, not become 0.
    const payloadItems = isWet
      ? lines.map((l) => ({ order_item_id: l.order_item_id, qty: l.qty, unit_price: l.unit_price }))
      : lines.filter((l) => l.item_id).map((l) => ({ item_id: Number(l.item_id), qty: l.qty, unit_price: l.unit_price }));

    form.transform((data) => ({ ...data, items: payloadItems }));

    if (note) {
      form.put(route('purchasing.purchase-notes.update', note.id));
    } else if (isWet && tandaTerima) {
      form.post(route('purchasing.purchase-notes.store-wet', tandaTerima.id));
    } else {
      form.post(route('purchasing.purchase-notes.store'));
    }
  };

  const title = note ? `Nota ${note.note_number}` : isWet ? 'Input Nota Basah' : 'Nota Kering Baru';

  return (
    <>
      <Head title={title} />

      <PageHeader
        title={title}
        description={
          isWet
            ? `Tanda Terima ${tandaTerima?.sj_number} — ${tandaTerima?.dapur_name} — ${tandaTerima?.supplier_name} — kirim ${tandaTerima?.shipment_date}. Harga di nota ini menjadi harga pokok barang dapur ini.`
            : 'Barang di nota ini langsung masuk stok gudang dengan harga nota.'
        }
        breadcrumbs={[{ label: 'Purchasing' }, { label: 'Nota Pembelian' }, { label: note ? 'Koreksi' : 'Baru' }]}
      />

      {readOnly && (
        <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">{editBlockReason}</div>
      )}
      {errors.note && <div className="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">{errors.note}</div>}
      {errors.surat_jalan_id && (
        <div className="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">{errors.surat_jalan_id}</div>
      )}

      <form onSubmit={submit} className="flex flex-col gap-6">
        <fieldset disabled={readOnly} className="grid gap-4 sm:grid-cols-2">
          {!isWet && (
            <>
              <div className="flex flex-col gap-1.5">
                <Label>Supplier / Toko</Label>
                <Combobox
                  value={form.data.supplier_id}
                  onValueChange={(v) => form.setData('supplier_id', v)}
                  placeholder="Pilih supplier/toko..."
                  emptyText="Belum terdaftar — tambahkan di Master → Supplier."
                  options={suppliers.map((s) => ({ value: String(s.id), label: s.name }))}
                />
                {errors.supplier_id && <p className="text-xs text-red-500">{errors.supplier_id}</p>}
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="note_date">Tanggal Nota</Label>
                <Input id="note_date" type="date" value={form.data.note_date} onChange={(e) => form.setData('note_date', e.target.value)} />
                {errors.note_date && <p className="text-xs text-red-500">{errors.note_date}</p>}
              </div>
            </>
          )}
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="reference_number">Nomor Nota Fisik (opsional)</Label>
            <Input id="reference_number" value={form.data.reference_number} onChange={(e) => form.setData('reference_number', e.target.value)} />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="notes">Catatan (opsional)</Label>
            <Input id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
          </div>
        </fieldset>

        <fieldset disabled={readOnly} className="rounded-lg border border-gray-200 p-4">
          {isWet && tandaTerima ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Barang</TableHead>
                  <TableHead>Order</TableHead>
                  <TableHead className="text-right">Qty Pesan</TableHead>
                  <TableHead>Qty di Nota</TableHead>
                  <TableHead>Harga Satuan</TableHead>
                  <TableHead className="text-right">Subtotal</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {tandaTerima.lines.map((ttLine, index) => {
                  const line = lines[index];
                  const differs = Number(line.qty || 0) !== ttLine.qty_ordered;
                  return (
                    <TableRow key={ttLine.order_item_id} className={differs ? 'bg-amber-50' : ''}>
                      <TableCell className="font-medium">{ttLine.item_name}</TableCell>
                      <TableCell className="text-xs">{ttLine.order_number}</TableCell>
                      <TableCell className="text-right">
                        {formatQty(ttLine.qty_ordered)} {ttLine.satuan}
                      </TableCell>
                      <TableCell>
                        <Input type="number" min="0" step="0.01" value={line.qty} onChange={(e) => updateLine(line.key, { qty: e.target.value })} />
                        {differs && <p className="text-xs text-amber-700">Selisih {formatQty(Number(line.qty || 0) - ttLine.qty_ordered)}</p>}
                        {errors[`items.${index}.qty`] && <p className="text-xs text-red-500">{errors[`items.${index}.qty`]}</p>}
                      </TableCell>
                      <TableCell>
                        <Input
                          type="number"
                          min="0"
                          step="0.01"
                          value={line.unit_price}
                          placeholder={`terakhir ${ttLine.last_cost_price}`}
                          onChange={(e) => updateLine(line.key, { unit_price: e.target.value })}
                        />
                        {errors[`items.${index}.unit_price`] && <p className="text-xs text-red-500">{errors[`items.${index}.unit_price`]}</p>}
                      </TableCell>
                      <TableCell className="text-right">{formatRupiah(Number(line.qty || 0) * Number(line.unit_price || 0))}</TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          ) : (
            <div className="flex flex-col gap-3">
              {lines.map((line) => {
                // Server error keys index only the rows that were submitted.
                const index = lines.filter((l) => l.item_id).indexOf(line);

                return (
                <div key={line.key} className="grid grid-cols-12 items-start gap-2">
                  <div className="col-span-5 flex flex-col gap-1">
                    <Label>Barang</Label>
                    <Combobox
                      value={line.item_id}
                      onValueChange={(v) => updateLine(line.key, { item_id: v })}
                      placeholder="Cari barang kering..."
                      emptyText="Barang tidak ditemukan."
                      options={items.map((i) => ({ value: String(i.id), label: `${i.name} (${i.satuan?.name ?? '-'})` }))}
                    />
                    {errors[`items.${index}.item_id`] && <p className="text-xs text-red-500">{errors[`items.${index}.item_id`]}</p>}
                  </div>
                  <div className="col-span-2 flex flex-col gap-1">
                    <Label>Qty</Label>
                    <Input type="number" min="0.01" step="0.01" value={line.qty} onChange={(e) => updateLine(line.key, { qty: e.target.value })} />
                    {errors[`items.${index}.qty`] && <p className="text-xs text-red-500">{errors[`items.${index}.qty`]}</p>}
                  </div>
                  <div className="col-span-2 flex flex-col gap-1">
                    <Label>Harga Satuan</Label>
                    <Input type="number" min="0.01" step="0.01" value={line.unit_price} onChange={(e) => updateLine(line.key, { unit_price: e.target.value })} />
                    {errors[`items.${index}.unit_price`] && <p className="text-xs text-red-500">{errors[`items.${index}.unit_price`]}</p>}
                  </div>
                  <div className="col-span-2 flex flex-col gap-1">
                    <Label>Subtotal</Label>
                    <p className="py-2 text-sm">{formatRupiah(Number(line.qty || 0) * Number(line.unit_price || 0))}</p>
                  </div>
                  <div className="col-span-1 flex justify-end pt-6">
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      disabled={lines.length === 1}
                      onClick={() => setLines((prev) => prev.filter((l) => l.key !== line.key))}
                    >
                      <Trash2 className="h-4 w-4 text-red-500" />
                    </Button>
                  </div>
                </div>
                );
              })}
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                onClick={() => setLines((prev) => [...prev, { key: nextKey++, item_id: '', order_item_id: null, qty: '', unit_price: '' }])}
              >
                + Tambah Barang
              </Button>
            </div>
          )}
          {errors.items && <p className="mt-2 text-xs text-red-500">{errors.items}</p>}
          <p className="mt-4 text-right text-sm">
            Total nota: <span className="font-semibold">{formatRupiah(total)}</span>
          </p>
        </fieldset>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" asChild>
            <Link href={route('purchasing.purchase-notes.index')}>Kembali</Link>
          </Button>
          {!readOnly && (
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Menyimpan...' : note ? 'Simpan Koreksi' : 'Simpan Nota'}
            </Button>
          )}
        </div>
      </form>
    </>
  );
}

PurchaseNoteForm.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
