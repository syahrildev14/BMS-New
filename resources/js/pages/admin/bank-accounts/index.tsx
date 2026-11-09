import { Head, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { Plus } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { FieldError } from '@/shared/components/ui/field-error';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { confirmDelete } from '@/shared/lib/alerts';
import type { BankAccount } from '@/shared/types/prd.type';

type FormState = {
  bank_name: string;
  account_number: string;
  account_holder: string;
  is_active: boolean;
};

const emptyForm: FormState = { bank_name: '', account_number: '', account_holder: '', is_active: true };

const columns: TableColumn<BankAccount>[] = [
  { key: 'bank_name', accessor: (account) => account.bank_name },
  { key: 'account_number', accessor: (account) => account.account_number },
  { key: 'account_holder', accessor: (account) => account.account_holder },
];

export default function AdminBankAccountsIndex({ bankAccounts }: { bankAccounts: BankAccount[] }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm<FormState>(emptyForm);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(bankAccounts, columns);

  const openCreate = () => {
    setEditingId(null);
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const openEdit = (account: BankAccount) => {
    setEditingId(account.id);
    form.clearErrors();
    form.setData({
      bank_name: account.bank_name,
      account_number: account.account_number,
      account_holder: account.account_holder,
      is_active: account.is_active,
    });
    setDialogOpen(true);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    const onSuccess = () => setDialogOpen(false);

    if (editingId) {
      form.put(route('admin.bank-accounts.update', editingId), { onSuccess });
    } else {
      form.post(route('admin.bank-accounts.store'), { onSuccess });
    }
  };

  const destroy = async (account: BankAccount) => {
    const ok = await confirmDelete({ title: `Hapus rekening ${account.bank_name} ${account.account_number}?` });
    if (ok) form.delete(route('admin.bank-accounts.destroy', account.id));
  };

  return (
    <>
      <Head title="Rekening Bank" />

      <PageHeader
        title="Master Data Rekening Bank"
        description="Rekening yang dipilih saat membuat invoice. Mengubah data di sini tidak mengubah invoice yang sudah dibuat."
        breadcrumbs={[{ label: 'Master' }, { label: 'Rekening Bank' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Tambah Rekening
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari bank, nomor, atau nama..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Bank" columnKey="bank_name" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="No. Rekening" columnKey="account_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Atas Nama" columnKey="account_holder" sort={sort} onSort={toggleSort} />
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="h-32 text-center text-gray-400">
                {bankAccounts.length === 0 ? 'Belum ada rekening.' : 'Tidak ada rekening yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((account) => (
              <TableRow key={account.id}>
                <TableCell className="font-medium">{account.bank_name}</TableCell>
                <TableCell>{account.account_number}</TableCell>
                <TableCell>{account.account_holder}</TableCell>
                <TableCell>{account.is_active ? 'Aktif' : 'Nonaktif'}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => openEdit(account)}>
                      Edit
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(account)}>
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

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingId ? 'Edit Rekening' : 'Tambah Rekening Baru'}</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="bank_name">Nama Bank</Label>
              <Input id="bank_name" value={form.data.bank_name} onChange={(e) => form.setData('bank_name', e.target.value)} />
              <FieldError message={form.errors.bank_name} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="account_number">No. Rekening</Label>
              <Input id="account_number" value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} />
              <FieldError message={form.errors.account_number} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="account_holder">Atas Nama</Label>
              <Input id="account_holder" value={form.data.account_holder} onChange={(e) => form.setData('account_holder', e.target.value)} />
              <FieldError message={form.errors.account_holder} />
            </div>

            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
              Aktif
            </label>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Batal
              </Button>
              <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Menyimpan...' : 'Simpan'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

AdminBankAccountsIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
