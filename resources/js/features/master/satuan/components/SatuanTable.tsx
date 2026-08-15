import { useMemo } from 'react';
import { DataTable } from '@/shared/components/data-table/DataTable';
import { useSatuanList, useDeleteSatuan } from '../hooks/useSatuan';
import { getSatuanColumns } from '../constants/satuan.columns';
import { confirmDelete } from '@/shared/lib/alerts';
import type { Satuan } from '../types/satuan.type';

interface SatuanTableProps {
  page: number;
  onPageChange: (page: number) => void;

  search: string;          // hasil debounce
  searchInput: string;     // nilai input

  onSearchChange: (value: string) => void;
  onEdit: (satuan: Satuan) => void;
}

export function SatuanTable({
  page,
  onPageChange,
  search,
  searchInput,
  onSearchChange,
  onEdit,
}: SatuanTableProps) {
  const { data, isLoading, isFetching } = useSatuanList({ page, search, per_page: 10 });
  const deleteMutation = useDeleteSatuan();

  async function handleDelete(satuan: Satuan) {
    const confirmed = await confirmDelete({
      title: `Hapus "${satuan.nama_satuan}"?`,
      text: 'Satuan yang sudah dihapus tidak dapat dikembalikan.',
    });
    if (confirmed) deleteMutation.mutate(satuan.id);
  }

  // useMemo wajib di sini supaya referensi columns stabil antar render (mencegah re-render tabel percuma)
  const columns = useMemo(() => getSatuanColumns(onEdit, handleDelete), [onEdit]);

  return (
    <DataTable
      columns={columns}
      response={data}
      isLoading={isLoading}
      isFetching={isFetching}
      page={page}
      onPageChange={onPageChange}
      search={searchInput}
      onSearchChange={onSearchChange}
      emptyMessage="Belum ada data satuan"
    />
  );
}
