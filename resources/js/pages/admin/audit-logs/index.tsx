import { Head } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';

// Page-local type: intentionally NOT the shared `AuditLog` type from
// prd.type.ts, which narrowly types `new_data` for the Ops revisions page
// (`new_data.order_id`, `new_data.items.length`). This viewer shows ANY
// audit_logs row regardless of action, so old_data/new_data must stay
// loosely typed here instead.
interface AuditLogRow {
  id: number;
  action: string;
  description: string | null;
  old_data: Record<string, unknown> | null;
  new_data: Record<string, unknown> | null;
  created_at: string;
  user: { id: number; name: string } | null;
}

const columns: TableColumn<AuditLogRow>[] = [
  { key: 'created_at', accessor: (log) => log.created_at },
  { key: 'user', accessor: (log) => log.user?.name },
  { key: 'action', accessor: (log) => log.action },
  { key: 'description', accessor: (log) => log.description },
];

export default function AdminAuditLogsIndex({ auditLogs }: { auditLogs: AuditLogRow[] }) {
  const [detailLog, setDetailLog] = useState<AuditLogRow | null>(null);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(
    auditLogs,
    columns,
  );

  return (
    <>
      <Head title="Log Aktivitas" />

      <PageHeader
        title="Log Aktivitas"
        description="Riwayat aktivitas sistem (revisi order, approve/reject, dll) — read-only, tidak bisa diedit atau dihapus dari sini."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Log Aktivitas' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari action atau deskripsi..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Waktu" columnKey="created_at" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="User" columnKey="user" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Action" columnKey="action" sort={sort} onSort={toggleSort} />
            <TableHead>Deskripsi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                {auditLogs.length === 0 ? 'Belum ada aktivitas tercatat.' : 'Tidak ada yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((log) => (
              <TableRow key={log.id} className="cursor-pointer" onClick={() => setDetailLog(log)}>
                <TableCell className="font-medium">{log.created_at}</TableCell>
                <TableCell>{log.user?.name ?? '-'}</TableCell>
                <TableCell>{log.action}</TableCell>
                <TableCell>{log.description ?? '-'}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />

      <Dialog open={detailLog !== null} onOpenChange={(open) => !open && setDetailLog(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Detail Log #{detailLog?.id}</DialogTitle>
          </DialogHeader>

          <div className="flex flex-col gap-4">
            <div>
              <p className="mb-1 text-xs font-semibold text-gray-500">old_data</p>
              <pre className="max-h-48 overflow-auto rounded-md bg-gray-50 p-3 text-xs dark:bg-gray-800">
                {JSON.stringify(detailLog?.old_data ?? null, null, 2)}
              </pre>
            </div>
            <div>
              <p className="mb-1 text-xs font-semibold text-gray-500">new_data</p>
              <pre className="max-h-48 overflow-auto rounded-md bg-gray-50 p-3 text-xs dark:bg-gray-800">
                {JSON.stringify(detailLog?.new_data ?? null, null, 2)}
              </pre>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}

AdminAuditLogsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
