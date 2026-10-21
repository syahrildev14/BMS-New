import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { Trash2 } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select';
import type { Item, Supplier } from '@/shared/types/prd.type';

interface OrderLineForm {
  key: number;
  item_id: string;
  supplier_id: string;
  qty_ordered: string;
  estimated_price: string;
}

let nextKey = 1;

function emptyLine(): OrderLineForm {
  return { key: nextKey++, item_id: '', supplier_id: '', qty_ordered: '', estimated_price: '' };
}

export default function DapurOrdersCreate({ items, suppliers }: { items: Item[]; suppliers: Supplier[] }) {
  const [lines, setLines] = useState<OrderLineForm[]>([emptyLine()]);

  const { data, setData, post, processing, errors } = useForm({
    delivery_date: '',
    notes: '',
    items: [] as { item_id: number; supplier_id: number; qty_ordered: number; estimated_price: number }[],
  });

  const updateLine = (key: number, patch: Partial<OrderLineForm>) => {
    setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  };

  const onPickItem = (key: number, itemId: string) => {
    const item = items.find((i) => String(i.id) === itemId);
    updateLine(key, {
      item_id: itemId,
      supplier_id: item?.default_supplier_id ? String(item.default_supplier_id) : '',
      estimated_price: item?.base_selling_price ?? '',
    });
  };

  const addLine = () => setLines((prev) => [...prev, emptyLine()]);
  const removeLine = (key: number) => setLines((prev) => prev.filter((line) => line.key !== key));

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    const payload = lines
      .filter((line) => line.item_id && line.supplier_id && line.qty_ordered)
      .map((line) => ({
        item_id: Number(line.item_id),
        supplier_id: Number(line.supplier_id),
        qty_ordered: Number(line.qty_ordered),
        estimated_price: Number(line.estimated_price || 0),
      }));

    setData('items', payload);

    post(route('dapur.orders.store'), {
      onSuccess: () => setLines([emptyLine()]),
    });
  };

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

            return (
              <div key={line.key} className="grid grid-cols-12 items-end gap-2 border-t border-gray-100 pt-3 first:border-t-0 first:pt-0">
                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Item</Label>
                  <Select value={line.item_id} onValueChange={(v) => onPickItem(line.key, v)}>
                    <SelectTrigger>
                      <SelectValue placeholder="Pilih item..." />
                    </SelectTrigger>
                    <SelectContent>
                      {items.map((item) => (
                        <SelectItem key={item.id} value={String(item.id)}>
                          {item.name} ({item.satuan?.name})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="col-span-3 flex flex-col gap-1">
                  <Label>Supplier</Label>
                  <Select value={line.supplier_id} onValueChange={(v) => updateLine(line.key, { supplier_id: v })}>
                    <SelectTrigger>
                      <SelectValue placeholder="Pilih supplier..." />
                    </SelectTrigger>
                    <SelectContent>
                      {suppliers.map((supplier) => (
                        <SelectItem key={supplier.id} value={String(supplier.id)}>
                          {supplier.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="col-span-2 flex flex-col gap-1">
                  <Label>Qty {selectedItem ? `(${selectedItem.satuan?.name})` : ''}</Label>
                  <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={line.qty_ordered}
                    onChange={(e) => updateLine(line.key, { qty_ordered: e.target.value })}
                  />
                </div>

                <div className="col-span-2 flex flex-col gap-1">
                  <Label>Estimasi Harga</Label>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={line.estimated_price}
                    onChange={(e) => updateLine(line.key, { estimated_price: e.target.value })}
                  />
                </div>

                <div className="col-span-1 flex justify-end">
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
