import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { Trash2 } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Combobox } from '@/shared/components/ui/combobox';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import type { Item } from '@/shared/types/prd.type';

// Dapur only chooses item + qty — supplier and price are filled in by the
// server from the item master (koperasi decides those, not Dapur).
interface OrderLineForm {
  key: number;
  item_id: string;
  qty_ordered: string;
}

let nextKey = 1;

function emptyLine(): OrderLineForm {
  return { key: nextKey++, item_id: '', qty_ordered: '' };
}

const isFilled = (line: OrderLineForm) => Boolean(line.item_id && line.qty_ordered);

export default function DapurOrdersCreate({ items }: { items: Item[] }) {
  const [lines, setLines] = useState<OrderLineForm[]>([emptyLine()]);

  const { data, setData, transform, post, processing, errors } = useForm({
    delivery_date: '',
    notes: '',
    items: [] as { item_id: number; qty_ordered: number }[],
  });

  const lineErrors = errors as Record<string, string | undefined>;

  const updateLine = (key: number, patch: Partial<OrderLineForm>) => {
    setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  };

  const addLine = () => setLines((prev) => [...prev, emptyLine()]);
  const removeLine = (key: number) => setLines((prev) => prev.filter((line) => line.key !== key));

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    const payload = lines.filter(isFilled).map((line) => ({
      item_id: Number(line.item_id),
      qty_ordered: Number(line.qty_ordered),
    }));

    transform((formData) => ({ ...formData, items: payload }));

    post(route('dapur.orders.store'), {
      onSuccess: () => setLines([emptyLine()]),
    });
  };

  // Server error keys (items.N.*) index into the submitted (filled) lines only.
  const filledLines = lines.filter(isFilled);

  return (
    <>
      <Head title="Buat Order" />

      <PageHeader title="Buat Order Baru" breadcrumbs={[{ label: 'Dapur' }, { label: 'Order Saya' }, { label: 'Buat' }]} />

      <form onSubmit={submit} className="flex flex-col gap-6">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="delivery_date">Tanggal Kirim</Label>
            <Input
              id="delivery_date"
              type="date"
              value={data.delivery_date}
              onChange={(e) => setData('delivery_date', e.target.value)}
            />
            {errors.delivery_date && <p className="text-xs text-red-500">{errors.delivery_date}</p>}
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="notes">Catatan (opsional)</Label>
            <Input id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
          </div>
        </div>

        <div className="flex flex-col gap-3 rounded-lg border border-gray-200 p-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-700">Daftar Item Pesanan</h2>
            <Button type="button" variant="outline" size="sm" onClick={addLine}>
              + Tambah Item
            </Button>
          </div>

          {lines.map((line) => {
            const selectedItem = items.find((i) => String(i.id) === line.item_id);
            const submittedIndex = filledLines.indexOf(line);
            const itemError = submittedIndex >= 0 ? lineErrors[`items.${submittedIndex}.item_id`] : undefined;
            const qtyError = submittedIndex >= 0 ? lineErrors[`items.${submittedIndex}.qty_ordered`] : undefined;

            return (
              <div key={line.key} className="grid grid-cols-12 items-start gap-2 border-t border-gray-100 pt-3 first:border-t-0 first:pt-0">
                <div className="col-span-7 flex flex-col gap-1">
                  <Label>Item</Label>
                  <Combobox
                    value={line.item_id}
                    onValueChange={(v) => updateLine(line.key, { item_id: v })}
                    placeholder="Ketik untuk cari item..."
                    emptyText="Item tidak ditemukan."
                    options={items.map((item) => ({ value: String(item.id), label: `${item.name} (${item.satuan?.name})` }))}
                  />
                  {itemError && <p className="text-xs text-red-500">{itemError}</p>}
                </div>

                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Qty {selectedItem ? `(${selectedItem.satuan?.name})` : ''}</Label>
                  <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={line.qty_ordered}
                    onChange={(e) => updateLine(line.key, { qty_ordered: e.target.value })}
                  />
                  {qtyError && <p className="text-xs text-red-500">{qtyError}</p>}
                </div>

                <div className="col-span-1 flex justify-end pt-6">
                  <Button type="button" variant="ghost" size="icon" onClick={() => removeLine(line.key)} disabled={lines.length === 1}>
                    <Trash2 className="h-4 w-4 text-red-500" />
                  </Button>
                </div>
              </div>
            );
          })}

          {errors.items && <p className="text-xs text-red-500">{errors.items}</p>}
        </div>

        <div className="flex justify-end gap-2">
          <Button type="submit" disabled={processing}>
            {processing ? 'Mengirim...' : 'Kirim Order'}
          </Button>
        </div>
      </form>
    </>
  );
}

DapurOrdersCreate.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
