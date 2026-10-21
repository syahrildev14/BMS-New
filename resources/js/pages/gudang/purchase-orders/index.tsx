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

interface ReceivingLine {
  id: number;
  name: string;
  satuan: string;
  qty_ordered: string;
  qty_received_so_far: string;
  qty_received: string;
  unit_cost: string;
}

const STATUS_VARIANT: Record<string, BadgeProps['variant']> = {
  SENT: 'info',
  PARTIALLY_RECEIVED: 'warning',
};

const columns: TableColumn<PurchaseOrder>[] = [
  { key: 'po_number', accessor: (po) => po.po_number },
  { key: 'supplier', accessor: (po) => po.supplier?.name },
  { key: 'status', accessor: (po) => po.status },
  { key: 'sent_at', accessor: (po) => po.sent_at },
];

export default function GudangPurchaseOrdersIndex({ purchaseOrders }: { purchaseOrders: PurchaseOrder[] }) {
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(
    purchaseOrders,
    columns,
  );

  const [receiving, setReceiving] = useState<PurchaseOrder | null>(null);
  const [lines, setLines] = useState<ReceivingLine[]>([]);
  const [saving, setSaving] = useState(false);

  const openReceive = (po: PurchaseOrder) => {
    setReceiving(po);
    setLines(
      (po.items ?? [])
        .filter((line) => Number(line.qty_received) < Number(line.qty_ordered))
        .map((line) => ({
          id: line.id,
          name: line.item?.name ?? '-',
          satuan: line.item?.satuan?.name ?? '',
          qty_ordered: line.qty_ordered,
          qty_received_so_far: line.qty_received,
          qty_received: String(Number(line.qty_ordered) - Number(line.qty_received)),
          unit_cost: line.unit_cost,
        })),
    );
  };

  const updateLine = (id: number, patch: Partial<ReceivingLine>) => {
    setLines((prev) => prev.map((line) => (line.id === id ? { ...line, ...patch } : line)));
  };

  const submitReceive = () => {
    if (!receiving) return;
    setSaving(true);
    router.post(
      route('gudang.purchase-orders.receive', receiving.id),
      { items: lines.map((l) => ({ id: l.id, qty_received: l.qty_received, unit_cost: l.unit_cost })) },
      {
        onFinish: () => setSaving(false),
        onSuccess: () => setReceiving(null),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyimpan penerimaan barang.'),
      },
    );
  };

  return (
    <>
      <Head title="Terima Barang" />

      <PageHeader
        title="Terima Barang dari Supplier"
        description="PO yang sudah dikirim Purchasing dan menunggu barang fisik diterima ke Gudang."
        breadcrumbs={[{ label: 'Gudang' }, { label: 'Terima Barang' }]}
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari no. PO atau supplier..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="No. PO" columnKey="po_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Supplier" columnKey="supplier" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Status" columnKey="status" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Tanggal Kirim" columnKey="sent_at" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="h-32 text-center text-gray-400">
                {purchaseOrders.length === 0 ? 'Tidak ada PO yang menunggu penerimaan.' : 'Tidak ada PO yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((po) => (
              <TableRow key={po.id}>
                <TableCell className="font-medium">{po.po_number}</TableCell>
                <TableCell>{po.supplier?.name}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[po.status]}>{po.status}</Badge>
                </TableCell>
                <TableCell>{po.sent_at}</TableCell>
                <TableCell className="text-right">
                  <Button variant="outline" size="sm" onClick={() => openReceive(po)}>
                    Terima Barang
                  </Button>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />

      <Dialog open={!!receiving} onOpenChange={(open) => !open && setReceiving(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              Terima Barang — {receiving?.po_number} ({receiving?.supplier?.name})
            </DialogTitle>
          </DialogHeader>

          <div className="flex flex-col gap-3">
            {lines.length === 0 && <p className="text-sm text-gray-400">Semua item di PO ini sudah diterima penuh.</p>}
            {lines.map((line) => (
              <div key={line.id} className="grid grid-cols-12 items-end gap-2 border-b border-gray-100 pb-3 last:border-0">
                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Barang</Label>
                  <p className="text-sm text-gray-700">
                    {line.name} ({line.satuan})
                  </p>
                  <p className="text-xs text-gray-400">Dipesan: {line.qty_ordered}, diterima: {line.qty_received_so_far}</p>
                </div>
                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Qty Diterima Sekarang</Label>
                  <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={line.qty_received}
                    onChange={(e) => updateLine(line.id, { qty_received: e.target.value })}
                  />
                </div>
                <div className="col-span-4 flex flex-col gap-1">
                  <Label>Harga Satuan Aktual</Label>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={line.unit_cost}
                    onChange={(e) => updateLine(line.id, { unit_cost: e.target.value })}
                  />
                </div>
              </div>
            ))}
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setReceiving(null)}>
              Batal
            </Button>
            {lines.length > 0 && (
              <Button type="button" onClick={submitReceive} disabled={saving}>
                {saving ? 'Menyimpan...' : 'Simpan Penerimaan'}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

GudangPurchaseOrdersIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
