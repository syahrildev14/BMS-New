import { Head, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { errorAlert } from '@/shared/lib/alerts';
import type { AuditLog } from '@/shared/types/prd.type';

const columns: TableColumn<AuditLog>[] = [
  { key: 'created_at', accessor: (log) => log.created_at },
  { key: 'order_id', accessor: (log) => log.new_data?.order_id },
  { key: 'item_count', accessor: (log) => log.new_data?.items.length ?? 0 },
];

export default function OpsRevisionsIndex({ pendingApprovals }: { pendingApprovals: AuditLog[] }) {
  const [processingId, setProcessingId] = useState<number | null>(null);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(
    pendingApprovals,
    columns,
  );

  const approve = (revisionId: number) => {
    setProcessingId(revisionId);
    router.post(route('ops.revisions.approve', revisionId), {}, {
      onFinish: () => setProcessingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyetujui revisi.'),
    });
  };

  const reject = (revisionId: number) => {
    setProcessingId(revisionId);
    router.post(route('ops.revisions.reject', revisionId), {}, {
      onFinish: () => setProcessingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menolak revisi.'),
    });
  };

  return (
    <>
      <Head title="Approval Revisi" />

      <PageHeader
        title="Approval Revisi Malam Hari"
        description="Revisi/add-on yang diajukan Dapur di luar cut-off normal (17:01-22:00 WIB) menunggu Approve/Reject Ops."
        breadcrumbs={[{ label: 'Ops' }, { label: 'Approval Revisi' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari order ID..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Waktu Pengajuan" columnKey="created_at" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Order ID" columnKey="order_id" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jumlah Item" columnKey="item_count" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                {pendingApprovals.length === 0 ? 'Tidak ada revisi yang menunggu approval.' : 'Tidak ada revisi yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((log) => (
              <TableRow key={log.id}>
                <TableCell>{log.created_at}</TableCell>
                <TableCell className="font-medium">{log.new_data?.order_id}</TableCell>
                <TableCell>{log.new_data?.items.length ?? 0}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button size="sm" variant="outline" onClick={() => reject(log.id)} disabled={processingId === log.id}>
                      Reject
                    </Button>
                    <Button size="sm" onClick={() => approve(log.id)} disabled={processingId === log.id}>
                      Approve
                    </Button>
                  </div>
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

OpsRevisionsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
