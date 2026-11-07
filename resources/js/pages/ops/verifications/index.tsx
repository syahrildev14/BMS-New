import { Head, Link } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge } from '@/shared/components/ui/badge';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import type { DeliveryDocumentType, DeliveryVerificationStatus } from '@/shared/types/prd.type';

interface DocumentRow {
  id: number;
  sj_number: string;
  document_type: DeliveryDocumentType;
  shipment_date: string;
  verification_status: DeliveryVerificationStatus;
  order_items_count: number;
  stt_filled_count: number;
  photos_count: number;
  dapur?: { name: string };
  supplier?: { name: string };
}

const columns: TableColumn<DocumentRow>[] = [
  { key: 'sj_number', accessor: (doc) => doc.sj_number },
  { key: 'dapur', accessor: (doc) => doc.dapur?.name },
  { key: 'supplier', accessor: (doc) => doc.supplier?.name },
  { key: 'shipment_date', accessor: (doc) => doc.shipment_date },
];

export default function VerificationsIndex({ tab, today, documents }: { tab: 'open' | 'verified'; today: string; documents: DocumentRow[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(documents, columns);

  return (
    <>
      <Head title="Crosscheck H+1" />

      <PageHeader
        title="Crosscheck H+1"
        description="Cocokkan pesanan, STT Dapur, nota, dan foto per dokumen. Invoice baru bisa dibuat setelah dokumennya terverifikasi."
        breadcrumbs={[{ label: 'Ops' }, { label: 'Crosscheck H+1' }]}
      />

      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari nomor, dapur, supplier..." />
        <div className="flex gap-3 text-sm">
          <Link href={route('ops.verifications.index', { tab: 'open' })} className={tab === 'open' ? 'font-semibold' : 'text-blue-600 hover:underline'}>
            Belum terverifikasi
          </Link>
          <Link href={route('ops.verifications.index', { tab: 'verified' })} className={tab === 'verified' ? 'font-semibold' : 'text-blue-600 hover:underline'}>
            Terverifikasi
          </Link>
        </div>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nomor" columnKey="sj_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dapur" columnKey="dapur" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Supplier" columnKey="supplier" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Tanggal Kirim" columnKey="shipment_date" sort={sort} onSort={toggleSort} />
            <TableHead>STT</TableHead>
            <TableHead>Foto</TableHead>
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="h-32 text-center text-gray-400">
                {documents.length === 0 ? 'Tidak ada dokumen di daftar ini.' : 'Tidak ada dokumen yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((doc) => (
              <TableRow key={doc.id}>
                <TableCell className="font-medium">{doc.sj_number}</TableCell>
                <TableCell>{doc.dapur?.name}</TableCell>
                <TableCell>{doc.supplier?.name}</TableCell>
                <TableCell>
                  {doc.shipment_date}
                  {tab === 'open' && doc.shipment_date < today && <div className="text-xs font-medium text-amber-700">Sudah lewat</div>}
                </TableCell>
                <TableCell>
                  {doc.stt_filled_count}/{doc.order_items_count}
                </TableCell>
                <TableCell>{doc.photos_count}</TableCell>
                <TableCell>
                  {doc.verification_status === 'VERIFIED' && <Badge variant="success">Terverifikasi</Badge>}
                  {doc.verification_status === 'DISCREPANCY' && <Badge variant="warning">Ada Selisih</Badge>}
                  {doc.verification_status === 'PENDING' && <Badge>Menunggu</Badge>}
                </TableCell>
                <TableCell className="text-right">
                  <Link href={route('ops.verifications.show', doc.id)} className="text-sm font-medium text-blue-600 hover:underline">
                    Periksa
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

VerificationsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
