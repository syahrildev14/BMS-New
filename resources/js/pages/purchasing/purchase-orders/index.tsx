import { Head, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge, type BadgeProps } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { errorAlert } from '@/shared/lib/alerts';
import type { PurchaseOrder } from '@/shared/types/prd.type';

interface EditableLine {
  id: number;
  name: string;
  satuan: string;
  qty_ordered: string;
  unit_cost: string;
}

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
  DRAFT: 'warning',
  SENT: 'info',
  PARTIALLY_RECEIVED: 'info',
  RECEIVED: 'success',
  CANCELLED: 'destructive',
};

const columns: TableColumn<PurchaseOrder>[] = [
  { key: 'po_number', accessor: (po) => po.po_number },
  { key: 'supplier', accessor: (po) => po.supplier?.name },
  { key: 'order', accessor: (po) => po.triggered_by_order?.order_number },
  { key: 'status', accessor: (po) => po.status },
  { key: 'created_at', accessor: (po) => po.created_at },
];

export default function PurchasingPurchaseOrdersIndex({ purchaseOrders }: { purchaseOrders: PurchaseOrder[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(
    purchaseOrders,
    columns,
  );

  const [reviewing, setReviewing] = useState<PurchaseOrder | null>(null);
  const [lines, setLines] = useState<EditableLine[]>([]);
  const [saving, setSaving] = useState(false);
  const [sending, setSending] = useState(false);

  const openReview = (po: PurchaseOrder) => {
    setReviewing(po);
    setLines(
      (po.items ?? []).map((line) => ({
        id: line.id,
        name: line.item?.name ?? '-',
        satuan: line.item?.satuan?.name ?? '',
        qty_ordered: line.qty_ordered,
        unit_cost: line.unit_cost,
      })),
    );
  };

  const updateLine = (id: number, patch: Partial<EditableLine>) => {
    setLines((prev) => prev.map((line) => (line.id === id ? { ...line, ...patch } : line)));
  };

  const saveChanges = () => {
    if (!reviewing) return;
    setSaving(true);
    router.patch(
      route('purchasing.purchase-orders.update', reviewing.id),
      { items: lines.map((l) => ({ id: l.id, qty_ordered: l.qty_ordered, unit_cost: l.unit_cost })) },
      {
        onFinish: () => setSaving(false),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyimpan perubahan.'),
      },
    );
  };

  const sendPO = () => {
    if (!reviewing) return;
    setSending(true);
    router.post(route('purchasing.purchase-orders.send', reviewing.id), undefined, {
      onFinish: () => setSending(false),
      onSuccess: () => setReviewing(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal mengirim PO.'),
    });
  };

  const totalLines = (items: { qty_ordered: string; unit_cost: string }[]) =>
    items.reduce((sum, l) => sum + Number(l.qty_ordered) * Number(l.unit_cost), 0);

  return (
    <>
      <Head title="Purchase Order" />

      <PageHeader
        title="Purchase Order"
        description="PO yang otomatis terbit saat stok Gudang kurang untuk item DRY_GOODS — review, sesuaikan qty/harga bila perlu, lalu kirim ke Supplier."
        breadcrumbs={[{ label: 'Purchasing' }, { label: 'Purchase Order' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari no. PO, supplier, atau order..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="No. PO" columnKey="po_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Supplier" columnKey="supplier" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dari Order" columnKey="order" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Status" columnKey="status" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dibuat" columnKey="created_at" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="h-32 text-center text-gray-400">
                {purchaseOrders.length === 0 ? 'Belum ada Purchase Order.' : 'Tidak ada PO yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((po) => (
              <TableRow key={po.id}>
                <TableCell className="font-medium">{po.po_number}</TableCell>
                <TableCell>{po.supplier?.name}</TableCell>
                <TableCell>{po.triggered_by_order?.order_number ?? '-'}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[po.status]}>{po.status}</Badge>
                </TableCell>
                <TableCell>{po.created_at}</TableCell>
                <TableCell className="text-right">
                  <Button variant="outline" size="sm" onClick={() => openReview(po)}>
                    {po.status === 'DRAFT' ? 'Review & Kirim' : 'Lihat Detail'}
                  </Button>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />

      <Dialog open={!!reviewing} onOpenChange={(open) => !open && setReviewing(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              {reviewing?.po_number} — {reviewing?.supplier?.name}
            </DialogTitle>
          </DialogHeader>

          <div className="flex flex-col gap-3">
            {lines.map((line) => (
              <div key={line.id} className="grid grid-cols-12 items-end gap-2 border-b border-gray-100 pb-3 last:border-0">
                <div className="col-span-5 flex flex-col gap-1">
                  <Label>Barang</Label>
                  <p className="text-sm text-gray-700">
                    {line.name} ({line.satuan})
                  </p>
                </div>
                <div className="col-span-3 flex flex-col gap-1">
                  <Label>Qty</Label>
                  <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={line.qty_ordered}
                    disabled={reviewing?.status !== 'DRAFT'}
                    onChange={(e) => updateLine(line.id, { qty_ordered: e.target.value })}
                  />
                </div>
                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Harga Satuan</Label>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={line.unit_cost}
                    disabled={reviewing?.status !== 'DRAFT'}
                    onChange={(e) => updateLine(line.id, { unit_cost: e.target.value })}
                  />
                </div>
              </div>
            ))}

            <div className="flex justify-end text-sm font-medium text-gray-700">Total: Rp{totalLines(lines).toLocaleString('id-ID')}</div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setReviewing(null)}>
              Tutup
            </Button>
            {reviewing?.status === 'DRAFT' && (
              <>
                <Button type="button" variant="outline" onClick={saveChanges} disabled={saving}>
                  {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                </Button>
                <Button type="button" onClick={sendPO} disabled={sending}>
                  {sending ? 'Mengirim...' : 'Kirim ke Supplier'}
                </Button>
              </>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

PurchasingPurchaseOrdersIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
