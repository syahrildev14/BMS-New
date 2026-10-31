import { Head, router } from '@inertiajs/react';
import { Database } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { confirmDelete, errorAlert } from '@/shared/lib/alerts';
import { formatBytes } from '@/shared/lib/format';
import type { Backup } from '@/shared/types/prd.type';

const columns: TableColumn<Backup>[] = [
  { key: 'created_at', accessor: (backup) => backup.created_at },
  { key: 'size_bytes', accessor: (backup) => backup.size_bytes },
  { key: 'creator', accessor: (backup) => backup.creator?.name },
];

export default function AdminBackupsIndex({ backups }: { backups: Backup[] }) {
  const [creating, setCreating] = useState(false);
  const { sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(backups, columns);

  const createBackup = () => {
    setCreating(true);
    router.post(route('admin.backups.store'), {}, {
      onFinish: () => setCreating(false),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal membuat backup.'),
    });
  };

  const destroy = async (backup: Backup) => {
    const ok = await confirmDelete({ title: `Hapus backup "${backup.filename}"?` });
    if (ok) router.delete(route('admin.backups.destroy', backup.id));
  };

  return (
    <>
      <Head title="Backup Data" />

      <PageHeader
        title="Backup Data"
        description="Backup database on-demand. Tidak ada backup otomatis terjadwal — klik tombol di bawah setiap kali butuh snapshot baru."
        breadcrumbs={[{ label: 'Admin' }, { label: 'Backup Data' }]}
        actions={
          <Button onClick={createBackup} disabled={creating}>
            <Database className="h-4 w-4" />
            {creating ? 'Membuat Backup...' : 'Backup Sekarang'}
          </Button>
        }
      />

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Dibuat" columnKey="created_at" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Ukuran" columnKey="size_bytes" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dibuat Oleh" columnKey="creator" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={4} className="h-32 text-center text-gray-400">
                Belum ada backup dibuat.
              </TableCell>
            </TableRow>
          ) : (
            rows.map((backup) => (
              <TableRow key={backup.id}>
                <TableCell className="font-medium">{backup.created_at}</TableCell>
                <TableCell>{formatBytes(backup.size_bytes)}</TableCell>
                <TableCell>{backup.creator?.name ?? '-'}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <a
                      href={route('admin.backups.download', backup.id)}
                      className="text-sm font-medium text-blue-600 hover:underline"
                    >
                      Download
                    </a>
                    <Button variant="ghost" size="sm" onClick={() => destroy(backup)}>
                      Hapus
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

AdminBackupsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
