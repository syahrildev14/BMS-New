import { Head, router, useForm } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { Plus } from 'lucide-react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { FieldError } from '@/shared/components/ui/field-error';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { confirmDelete } from '@/shared/lib/alerts';
import type { DocumentTemplate, DocumentTemplateType } from '@/shared/types/prd.type';

type FormState = {
  name: string;
  document_type: DocumentTemplateType;
  file: File | null;
};

const TYPE_LABEL: Record<DocumentTemplateType, string> = {
  SURAT_JALAN: 'Surat Jalan',
  TANDA_TERIMA: 'Tanda Terima',
  INVOICE: 'Invoice',
};

const columns: TableColumn<DocumentTemplate>[] = [
  { key: 'name', accessor: (template) => template.name },
  { key: 'document_type', accessor: (template) => TYPE_LABEL[template.document_type] },
];

export default function AdminDocumentTemplatesIndex({ templates }: { templates: DocumentTemplate[] }) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const form = useForm<FormState>({ name: '', document_type: 'SURAT_JALAN', file: null });
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(templates, columns);

  const openCreate = () => {
    form.reset();
    form.clearErrors();
    setDialogOpen(true);
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    form.post(route('admin.document-templates.store'), { forceFormData: true, onSuccess: () => setDialogOpen(false) });
  };

  const toggleActive = (template: DocumentTemplate) => {
    router.put(route('admin.document-templates.update', template.id), { name: template.name, is_active: !template.is_active }, { preserveScroll: true });
  };

  const destroy = async (template: DocumentTemplate) => {
    const ok = await confirmDelete({ title: `Hapus template "${template.name}"?` });
    if (ok) router.delete(route('admin.document-templates.destroy', template.id), { preserveScroll: true });
  };

  return (
    <>
      <Head title="Template Dokumen" />

      <PageHeader
        title="Template Dokumen"
        description="Format Word (.docx) untuk Surat Jalan, Tanda Terima, dan Invoice. Untuk mengganti format, unggah template baru lalu nonaktifkan yang lama."
        breadcrumbs={[{ label: 'Master' }, { label: 'Template Dokumen' }]}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            Unggah Template
          </Button>
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari nama atau jenis..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nama" columnKey="name" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jenis" columnKey="document_type" sort={sort} onSort={toggleSort} />
            <TableHead>File</TableHead>
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="h-32 text-center text-gray-400">
                {templates.length === 0 ? 'Belum ada template. Selama kosong, sistem memakai format PDF bawaan.' : 'Tidak ada template yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((template) => (
              <TableRow key={template.id}>
                <TableCell className="font-medium">{template.name}</TableCell>
                <TableCell>{TYPE_LABEL[template.document_type]}</TableCell>
                <TableCell>{template.original_filename}</TableCell>
                <TableCell>{template.is_active ? 'Aktif' : 'Nonaktif'}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" onClick={() => toggleActive(template)}>
                      {template.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => destroy(template)}>
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
            <DialogTitle>Unggah Template</DialogTitle>
          </DialogHeader>

          <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="name">Nama Template</Label>
              <Input id="name" placeholder="Surat Jalan Koperasi" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
              <FieldError message={form.errors.name} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label>Jenis Dokumen</Label>
              <Select value={form.data.document_type} onValueChange={(value) => form.setData('document_type', value as DocumentTemplateType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {(Object.keys(TYPE_LABEL) as DocumentTemplateType[]).map((type) => (
                    <SelectItem key={type} value={type}>
                      {TYPE_LABEL[type]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError message={form.errors.document_type} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="file">File Word (.docx, maksimal 5 MB)</Label>
              <Input id="file" type="file" accept=".docx" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
              <FieldError message={form.errors.file} />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                Batal
              </Button>
              <Button type="submit" disabled={form.processing || !form.data.file}>
                {form.processing ? 'Mengunggah...' : 'Unggah'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

AdminDocumentTemplatesIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
