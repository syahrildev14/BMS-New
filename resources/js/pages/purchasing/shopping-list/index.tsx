import { Head, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { formatQty, formatRupiah } from '@/shared/lib/format';

interface ShoppingRow {
  item_id: number;
  sku: string;
  item_name: string;
  satuan: string | null;
  needed: number;
  stock: number;
  to_buy: number;
  supplier_name: string | null;
  last_cost_price: number;
}

export default function PurchasingShoppingList({ until, rows }: { until: string; rows: ShoppingRow[] }) {
  const [date, setDate] = useState(until);
  const toBuy = rows.filter((row) => row.to_buy > 0);
  const estimatedTotal = toBuy.reduce((sum, row) => sum + row.to_buy * row.last_cost_price, 0);

  return (
    <>
      <Head title="Daftar Belanja Kering" />

      <PageHeader
        title="Daftar Belanja Kering"
        description="Kebutuhan bahan kering semua dapur yang belum dikirim, dikurangi stok gudang. Barang yang dibeli dicatat lewat Nota Pembelian kering."
        breadcrumbs={[{ label: 'Purchasing' }, { label: 'Daftar Belanja Kering' }]}
      />

      <div className="mb-4 flex flex-wrap items-end gap-3 print:hidden">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="until">Kebutuhan order s.d. tanggal kirim</Label>
          <Input id="until" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </div>
        <Button variant="outline" onClick={() => router.get(route('purchasing.shopping-list.index'), { until: date }, { preserveState: true })}>
          Tampilkan
        </Button>
        <Button onClick={() => window.print()} disabled={toBuy.length === 0}>
          Cetak
        </Button>
      </div>

      <p className="mb-2 hidden text-sm print:block">Daftar belanja kering — kebutuhan order s.d. {until}</p>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Barang</TableHead>
            <TableHead className="text-right">Kebutuhan</TableHead>
            <TableHead className="text-right">Stok Gudang</TableHead>
            <TableHead className="text-right">Perlu Dibeli</TableHead>
            <TableHead>Supplier Saran</TableHead>
            <TableHead className="text-right">Harga Terakhir</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="h-32 text-center text-gray-400">
                Tidak ada kebutuhan bahan kering yang belum dikirim s.d. tanggal ini.
              </TableCell>
            </TableRow>
          ) : (
            rows.map((row) => (
              <TableRow key={row.item_id} className={row.to_buy > 0 ? '' : 'text-gray-400 print:hidden'}>
                <TableCell className="font-medium">
                  {row.item_name} <span className="text-xs text-gray-400">({row.sku})</span>
                </TableCell>
                <TableCell className="text-right">
                  {formatQty(row.needed)} {row.satuan}
                </TableCell>
                <TableCell className="text-right">{formatQty(row.stock)}</TableCell>
                <TableCell className="text-right font-semibold">{row.to_buy > 0 ? `${formatQty(row.to_buy)} ${row.satuan ?? ''}` : 'Cukup'}</TableCell>
                <TableCell>{row.supplier_name ?? '—'}</TableCell>
                <TableCell className="text-right">{formatRupiah(row.last_cost_price)}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {toBuy.length > 0 && (
        <p className="mt-3 text-right text-sm text-gray-600">
          Perkiraan biaya ({toBuy.length} barang, harga terakhir): <span className="font-semibold">{formatRupiah(estimatedTotal)}</span>
        </p>
      )}
    </>
  );
}

PurchasingShoppingList.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
