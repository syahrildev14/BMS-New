import { Head, Link } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { formatRupiah } from '@/shared/lib/format';

interface NoteRow {
  id: number;
  note_number: string;
  reference_number: string | null;
  category: 'DRY_GOODS' | 'WET_GOODS';
  note_date: string;
  total_amount: string;
  has_discrepancy: boolean;
  supplier?: { id: number; name: string };
  dapur?: { id: number; name: string } | null;
  surat_jalan?: { id: number; sj_number: string } | null;
}

interface PendingTandaTerima {
  id: number;
  sj_number: string;
  shipment_date: string;
  order_items_count: number;
  dapur?: { name: string };
  supplier: { id: number; name: string };
}

const columns: TableColumn<NoteRow>[] = [
  { key: 'note_number', accessor: (n) => n.note_number },
  { key: 'note_date', accessor: (n) => n.note_date },
  { key: 'category', accessor: (n) => n.category },
  { key: 'supplier', accessor: (n) => n.supplier?.name },
  { key: 'dapur', accessor: (n) => n.dapur?.name },
  { key: 'total', accessor: (n) => Number(n.total_amount) },
];

export default function PurchaseNotesIndex({ notes, pendingTandaTerima }: { notes: NoteRow[]; pendingTandaTerima: PendingTandaTerima[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(notes, columns);

  return (
    <>
      <Head title="Nota Pembelian" />

      <PageHeader
        title="Nota Pembelian"
        description="Nota kering menambah stok gudang. Nota basah diinput per Tanda Terima — harganya jadi harga pokok baris pesanan dapur tersebut."
        breadcrumbs={[{ label: 'Purchasing' }, { label: 'Nota Pembelian' }]}
      />

      <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
        <h2 className="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-200">
          Tanda Terima basah yang belum ada notanya ({pendingTandaTerima.length})
        </h2>
        {pendingTandaTerima.length === 0 ? (
          <p className="text-sm text-amber-700">Semua Tanda Terima basah sudah punya nota.</p>
        ) : (
          <div className="flex flex-col divide-y divide-amber-100">
            {pendingTandaTerima.map((tt) => (
              <div key={`${tt.id}-${tt.supplier.id}`} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <span>
                  <span className="font-medium">{tt.sj_number}</span> — {tt.dapur?.name} — {tt.supplier.name} — kirim {tt.shipment_date} (
                  {tt.order_items_count} barang)
                </span>
                <Button size="sm" asChild>
                  <Link href={route('purchasing.purchase-notes.create-wet', [tt.id, tt.supplier.id])}>Input Nota</Link>
                </Button>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari nomor nota, supplier, dapur..." />
        <Button asChild>
          <Link href={route('purchasing.purchase-notes.create')}>+ Nota Kering Baru</Link>
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="No. Nota" columnKey="note_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Tanggal" columnKey="note_date" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jenis" columnKey="category" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Supplier" columnKey="supplier" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dapur" columnKey="dapur" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Total" columnKey="total" sort={sort} onSort={toggleSort} />
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="h-32 text-center text-gray-400">
                {notes.length === 0 ? 'Belum ada nota.' : 'Tidak ada nota yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((note) => (
              <TableRow key={note.id}>
                <TableCell className="font-medium">
                  {note.note_number}
                  {note.reference_number && <div className="text-xs text-gray-400">Nota fisik: {note.reference_number}</div>}
                </TableCell>
                <TableCell>{note.note_date}</TableCell>
                <TableCell>{note.category === 'DRY_GOODS' ? 'Kering' : `Basah (${note.surat_jalan?.sj_number ?? '-'})`}</TableCell>
                <TableCell>{note.supplier?.name}</TableCell>
                <TableCell>{note.dapur?.name ?? '—'}</TableCell>
                <TableCell>{formatRupiah(note.total_amount)}</TableCell>
                <TableCell>
                  {note.category === 'WET_GOODS' &&
                    (note.has_discrepancy ? <Badge variant="warning">Ada Selisih</Badge> : <Badge variant="success">Sesuai</Badge>)}
                </TableCell>
                <TableCell className="text-right">
                  <Link href={route('purchasing.purchase-notes.edit', note.id)} className="text-sm font-medium text-primary-text hover:underline">
                    Lihat / Koreksi
                  </Link>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />
    </>
  );
}

PurchaseNotesIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
