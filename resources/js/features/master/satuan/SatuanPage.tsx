import { useState } from 'react';
import { Plus } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { useDebounce } from '@/shared/hooks/useDebounce';
import { SatuanTable } from './components/SatuanTable';
import { SatuanDialog } from './components/SatuanDialog';
import type { Satuan } from './types/satuan.type';

/**
 * SatuanPage adalah satu-satunya tempat yang mengelola state UI (page, search, dialog).
 * Table & Dialog murni "dumb" terhadap state ini — menerima via props.
 * Pola ini direplikasi dari modul Barang.
 */
export function SatuanPage() {
  const [page, setPage] = useState(1);
  const [searchInput, setSearchInput] = useState('');
  const search = useDebounce(searchInput, 400);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [selectedSatuan, setSelectedSatuan] = useState<Satuan | null>(null);

  function handleAdd() {
    setSelectedSatuan(null);
    setDialogOpen(true);
  }

  function handleEdit(satuan: Satuan) {
    setSelectedSatuan(satuan);
    setDialogOpen(true);
  }

  function handleSearchChange(value: string) {
    setSearchInput(value);
    setPage(1); // reset ke halaman 1 setiap kali search berubah
  }

  return (
    <AppLayout>
      <PageHeader
        title="Satuan"
        description="Kelola master data satuan."
        actions={
          <Button onClick={handleAdd} className='bg-primary hover:bg-primary/70'>
            <Plus className="h-4 w-4" />
            Tambah Satuan
          </Button>
        }
      />

      <SatuanTable
        page={page}
        onPageChange={setPage}
        search={search}
        searchInput={searchInput}
        onSearchChange={handleSearchChange}
        onEdit={handleEdit}
      />

      <SatuanDialog open={dialogOpen} onOpenChange={setDialogOpen} satuan={selectedSatuan} />
    </AppLayout>
  );
}
