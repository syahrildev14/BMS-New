import { Head, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Combobox } from '@/shared/components/ui/combobox';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { errorAlert } from '@/shared/lib/alerts';
import { formatQty } from '@/shared/lib/format';
import type { Armada, Dapur, DeliveryDocumentType, DocumentTemplateOption, Driver, SuratJalan } from '@/shared/types/prd.type';

interface Filters {
  dapur_id?: number | string;
  document_type: DeliveryDocumentType;
  date: string;
}

interface PendingGroup {
  supplier_id: number;
  supplier_name: string;
  items: {
    id: number;
    item_name: string;
    satuan: string | null;
    qty_ordered: number;
    order_number: string;
    delivery_date: string;
  }[];
}

type DocumentRow = SuratJalan & { order_items_count: number; created_at: string };

const DOC_LABEL: Record<DeliveryDocumentType, string> = {
  SURAT_JALAN: 'Surat Jalan (Kering)',
  TANDA_TERIMA: 'Tanda Terima (Basah)',
};

const columns: TableColumn<DocumentRow>[] = [
  { key: 'sj_number', accessor: (doc) => doc.sj_number },
  { key: 'document_type', accessor: (doc) => doc.document_type },
  { key: 'dapur', accessor: (doc) => doc.dapur?.name },
  { key: 'supplier', accessor: (doc) => doc.supplier?.name },
  { key: 'shipment_date', accessor: (doc) => doc.shipment_date },
];

export default function OpsDeliveryNotesIndex({
  filters,
  dapurs,
  pendingGroups,
  documentTemplates,
  armadas,
  drivers,
  documents,
}: {
  filters: Filters;
  dapurs: Pick<Dapur, 'id' | 'code' | 'name'>[];
  pendingGroups: PendingGroup[];
  documentTemplates: DocumentTemplateOption[];
  armadas: Pick<Armada, 'id' | 'name' | 'plate_number'>[];
  drivers: Pick<Driver, 'id' | 'name'>[];
  documents: DocumentRow[];
}) {
  const [dapurId, setDapurId] = useState(filters.dapur_id ? String(filters.dapur_id) : '');
  const [documentType, setDocumentType] = useState<DeliveryDocumentType>(filters.document_type);
  const [date, setDate] = useState(filters.date);
  // 'all' = Generate semua; a number = one supplier group.
  const [processing, setProcessing] = useState<number | 'all' | null>(null);
  const [templateId, setTemplateId] = useState('');
  const [armadaId, setArmadaId] = useState('');
  const [driverId, setDriverId] = useState('');

  const templateOptions = documentTemplates.filter((template) => template.document_type === documentType);
  const selectedTemplate = templateOptions.find((template) => String(template.id) === templateId);
  const needsArmada = selectedTemplate?.placeholders.some((name) => name === 'armada_name' || name === 'no_pol') ?? false;
  const needsDriver = selectedTemplate?.placeholders.includes('driver_name') ?? false;
  const canGenerate = (templateOptions.length === 0 || !!selectedTemplate) && (!needsArmada || !!armadaId) && (!needsDriver || !!driverId);

  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(documents, columns);

  const isDry = documentType === 'SURAT_JALAN';
  const selectionLoaded =
    String(filters.dapur_id ?? '') === dapurId && filters.document_type === documentType && filters.date === date && dapurId !== '';

  const show = () => {
    if (!dapurId) return errorAlert('Pilih Dapur terlebih dahulu.');
    router.get(route('ops.delivery-notes.index'), { dapur_id: dapurId, document_type: documentType, date }, { preserveState: true });
  };

  const generate = (supplierId: number | null) => {
    setProcessing(supplierId ?? 'all');
    router.post(
      route('ops.delivery-notes.store'),
      {
        dapur_id: Number(dapurId),
        document_type: documentType,
        date,
        supplier_id: supplierId,
        document_template_id: selectedTemplate?.id ?? null,
        armada_id: needsArmada ? Number(armadaId) : null,
        driver_id: needsDriver ? Number(driverId) : null,
      },
      {
        preserveScroll: true,
        onFinish: () => setProcessing(null),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menerbitkan dokumen.'),
      },
    );
  };

  return (
    <>
      <Head title="Pengiriman" />

      <PageHeader
        title="Pengiriman — Surat Jalan & Tanda Terima"
        description="Surat Jalan untuk bahan kering dari koperasi (boleh gabung beberapa order), Tanda Terima harian untuk bahan basah yang dikirim langsung supplier. Satu dokumen per Dapur per supplier."
        breadcrumbs={[{ label: 'Ops' }, { label: 'Pengiriman' }]}
      />


      <div className="mb-4 grid gap-4 rounded-lg border border-gray-200 p-4 sm:grid-cols-4">
        <div className="flex flex-col gap-1.5 sm:col-span-2">
          <Label>Dapur</Label>
          <Combobox
            value={dapurId}
            onValueChange={setDapurId}
            placeholder="Pilih dapur..."
            emptyText="Dapur tidak ditemukan."
            options={dapurs.map((d) => ({ value: String(d.id), label: `${d.name} (${d.code})` }))}
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label>Jenis</Label>
          <Select
            value={documentType}
            onValueChange={(v) => {
              setDocumentType(v as DeliveryDocumentType);
              setTemplateId('');
            }}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="SURAT_JALAN">{DOC_LABEL.SURAT_JALAN}</SelectItem>
              <SelectItem value="TANDA_TERIMA">{DOC_LABEL.TANDA_TERIMA}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="date">{isDry ? 'Kirim semua order s.d. tanggal' : 'Tanggal kirim'}</Label>
          <Input id="date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </div>
        <div className="sm:col-span-4 flex justify-end">
          <Button type="button" variant="outline" onClick={show}>
            Tampilkan Item Siap Kirim
          </Button>
        </div>
      </div>

      {selectionLoaded && (
        <div className="mb-8 flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-gray-700">
              Item siap kirim — {DOC_LABEL[documentType]} ({pendingGroups.length} supplier)
            </h2>
            {pendingGroups.length > 1 && (
              <Button size="sm" onClick={() => generate(null)} disabled={processing !== null || !canGenerate}>
                {processing === 'all' ? 'Memproses...' : 'Generate Semua'}
              </Button>
            )}
          </div>

          {templateOptions.length > 0 && pendingGroups.length > 0 && (
            <div className="grid gap-4 rounded-md border border-gray-200 p-4 sm:grid-cols-3">
              <div className="flex flex-col gap-1.5">
                <Label>Template Dokumen</Label>
                <Select value={templateId} onValueChange={setTemplateId}>
                  <SelectTrigger>
                    <SelectValue placeholder="Pilih template..." />
                  </SelectTrigger>
                  <SelectContent>
                    {templateOptions.map((template) => (
                      <SelectItem key={template.id} value={String(template.id)}>
                        {template.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              {needsArmada && (
                <div className="flex flex-col gap-1.5">
                  <Label>Armada</Label>
                  <Combobox
                    value={armadaId}
                    onValueChange={setArmadaId}
                    placeholder="Pilih armada..."
                    emptyText="Armada tidak ditemukan."
                    options={armadas.map((armada) => ({ value: String(armada.id), label: `${armada.name} (${armada.plate_number})` }))}
                  />
                </div>
              )}
              {needsDriver && (
                <div className="flex flex-col gap-1.5">
                  <Label>Driver</Label>
                  <Combobox
                    value={driverId}
                    onValueChange={setDriverId}
                    placeholder="Pilih driver..."
                    emptyText="Driver tidak ditemukan."
                    options={drivers.map((driver) => ({ value: String(driver.id), label: driver.name }))}
                  />
                </div>
              )}
            </div>
          )}

          {pendingGroups.length === 0 ? (
            <p className="text-sm text-gray-400">Tidak ada item yang perlu dibuatkan dokumen untuk pilihan ini.</p>
          ) : (
            pendingGroups.map((group) => (
              <div key={group.supplier_id} className="rounded-md border border-gray-200">
                <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2">
                  <span className="font-medium">{group.supplier_name}</span>
                  <Button size="sm" onClick={() => generate(group.supplier_id)} disabled={processing !== null || !canGenerate}>
                    {processing === group.supplier_id ? 'Memproses...' : `Generate ${isDry ? 'Surat Jalan' : 'Tanda Terima'}`}
                  </Button>
                </div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Item</TableHead>
                      <TableHead>Qty</TableHead>
                      <TableHead>No. Order</TableHead>
                      <TableHead>Untuk Tanggal</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {group.items.map((line) => (
                      <TableRow key={line.id}>
                        <TableCell>{line.item_name}</TableCell>
                        <TableCell>
                          {formatQty(line.qty_ordered)} {line.satuan}
                        </TableCell>
                        <TableCell>{line.order_number}</TableCell>
                        <TableCell>{line.delivery_date}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ))
          )}
          {isDry && pendingGroups.length > 0 && (
            <p className="text-xs text-gray-400">
              Item kering yang stok gudangnya kurang akan ditahan otomatis dan muncul di Daftar Belanja Purchasing; kirim menyusul setelah nota belanja diinput.
            </p>
          )}
        </div>
      )}

      <h2 className="mb-2 text-sm font-semibold text-gray-700">Dokumen yang sudah terbit</h2>
      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari nomor, dapur, atau supplier..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="Nomor" columnKey="sj_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Jenis" columnKey="document_type" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dapur" columnKey="dapur" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Supplier" columnKey="supplier" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Tanggal Kirim" columnKey="shipment_date" sort={sort} onSort={toggleSort} />
            <TableHead>Item</TableHead>
            <TableHead className="text-right">Unduh</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7} className="h-32 text-center text-gray-400">
                {documents.length === 0 ? 'Belum ada dokumen pengiriman.' : 'Tidak ada dokumen yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((doc) => (
              <TableRow key={doc.id}>
                <TableCell className="font-medium">{doc.sj_number}</TableCell>
                <TableCell>{DOC_LABEL[doc.document_type]}</TableCell>
                <TableCell>{doc.dapur?.name ?? '—'}</TableCell>
                <TableCell>{doc.supplier?.name ?? 'Campuran (lama)'}</TableCell>
                <TableCell>{doc.shipment_date ?? '—'}</TableCell>
                <TableCell>{doc.order_items_count}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-3">
                    <a href={route('ops.delivery-notes.download', doc.id)} className="text-xs font-medium text-blue-600 hover:underline">
                      Dokumen
                    </a>
                    {doc.tanda_terima_pdf_url && (
                      <a
                        href={route('ops.delivery-notes.tanda-terima.download', doc.id)}
                        className="text-xs font-medium text-emerald-600 hover:underline"
                      >
                        TT hasil STT
                      </a>
                    )}
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

OpsDeliveryNotesIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
