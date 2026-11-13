import { Head, Link } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge, type BadgeProps } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { formatQty } from '@/shared/lib/format';
import type { Satuan, StockMovementRow } from '@/shared/types/prd.type';

const TYPE_LABEL: Record<StockMovementRow['type'], { label: string; variant: BadgeProps['variant'] }> = {
  PURCHASE_IN: { label: 'Masuk', variant: 'success' },
  ORDER_OUT: { label: 'Keluar', variant: 'destructive' },
  OPNAME_ADJUST: { label: 'Opname', variant: 'warning' },
};

const columns: TableColumn<StockMovementRow>[] = [
  { key: 'date', accessor: (row) => row.date },
  { key: 'document', accessor: (row) => `${row.document} ${row.party ?? ''}` },
];

interface Props {
  item: { id: number; sku: string; name: string; satuan: Satuan | null };
  movements: StockMovementRow[];
}

export default function GudangStockHistory({ item, movements }: Props) {
  // Tanpa sort: urutan dari server (terbaru di atas) harus dipertahankan agar saldo berjalan terbaca benar.
  const { search, setSearch, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(movements, columns);
  const satuan = item.satuan ? ` ${item.satuan.name}` : '';
  const totalIn = movements.reduce((sum, m) => sum + m.qty_in, 0);
  const totalOut = movements.reduce((sum, m) => sum + m.qty_out, 0);

  return (
    <>
      <Head title={`Riwayat Stok ${item.name}`} />

      <PageHeader
        title={`Riwayat Stok — ${item.name}`}
        description={`${item.sku} · Total masuk ${formatQty(totalIn)}${satuan}, keluar ${formatQty(totalOut)}${satuan}, saldo ${formatQty(totalIn - totalOut)}${satuan}.`}
        breadcrumbs={[{ label: 'Gudang' }, { label: 'Stok Barang' }, { label: item.name }]}
        actions={
          <Button variant="outline" asChild>
            <Link href={route('gudang.stock.index')}>Kembali</Link>
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari tanggal, dokumen, supplier atau dapur..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Tanggal</TableHead>
            <TableHead>Jenis</TableHead>
            <TableHead>Dokumen</TableHead>
            <TableHead>Supplier / Dapur</TableHead>
            <TableHead className="text-right">Masuk</TableHead>
            <TableHead className="text-right">Keluar</TableHead>
            <TableHead className="text-right">Saldo</TableHead>
            <TableHead>Oleh</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="h-32 text-center text-gray-400">
                {movements.length === 0 ? 'Belum ada pergerakan stok untuk barang ini.' : 'Tidak ada data yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((row) => (
              <TableRow key={row.id}>
                <TableCell className="whitespace-nowrap">{row.date}</TableCell>
                <TableCell>
                  <Badge variant={TYPE_LABEL[row.type].variant}>{TYPE_LABEL[row.type].label}</Badge>
                </TableCell>
                <TableCell className="font-medium">{row.document}</TableCell>
                <TableCell>{row.party ?? '-'}</TableCell>
                <TableCell className="text-right text-green-600">{row.qty_in > 0 ? `+${formatQty(row.qty_in)}` : '-'}</TableCell>
                <TableCell className="text-right text-red-600">{row.qty_out > 0 ? `−${formatQty(row.qty_out)}` : '-'}</TableCell>
                <TableCell className="text-right font-medium">{formatQty(row.balance)}</TableCell>
                <TableCell className="text-gray-500">{row.created_by ?? '-'}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />
    </>
  );
}

GudangStockHistory.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
