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
import { formatQty } from '@/shared/lib/format';
import type { Order, SuratJalan } from '@/shared/types/prd.type';

type OrderWithCounts = Order & {
  pending_items_count: number;
  surat_jalans_count: number;
  surat_jalans?: Pick<SuratJalan, 'id' | 'sj_number' | 'type' | 'pdf_url' | 'tanda_terima_pdf_url'>[];
};

interface StuckItem {
  id: number;
  item_name: string;
  order_number: string;
  qty_ordered: number;
}

const columns: TableColumn<OrderWithCounts>[] = [
  { key: 'order_number', accessor: (order) => order.order_number },
  { key: 'dapur', accessor: (order) => order.dapur?.name },
  { key: 'delivery_date', accessor: (order) => order.delivery_date },
  { key: 'pending_items', accessor: (order) => order.pending_items_count },
  { key: 'sj_count', accessor: (order) => order.surat_jalans_count },
];

export default function OpsDeliveryNotesIndex({ orders, stuckItems }: { orders: OrderWithCounts[]; stuckItems: StuckItem[] }) {
  const [processingId, setProcessingId] = useState<number | null>(null);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(orders, columns);

  const generate = (orderId: number) => {
    setProcessingId(orderId);
    router.post(route('ops.delivery-notes.store'), { order_id: orderId }, {
      onFinish: () => setProcessingId(null),
      onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menerbitkan Surat Jalan.'),
    });
  };

  return (
    <>
      <Head title="Surat Jalan" />

      <PageHeader
        title="Surat Jalan"
        description="Generate SJ untuk Order yang masih punya item tertahan, dan cari/cetak PDF SJ yang sudah pernah diterbitkan — semuanya di satu tempat."
        breadcrumbs={[{ label: 'Ops' }, { label: 'Surat Jalan' }]}
      />

      {stuckItems.length > 0 && (
        <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
          <p className="font-medium">PERHATIAN: {stuckItems.length} item kurang stok tanpa default_supplier_id</p>
          <p className="text-amber-700">
            Item ini tidak akan pernah dapat draft PO otomatis sampai <code>default_supplier_id</code>-nya diisi. Cara memperbaiki:
          </p>
          <ol className="mb-2 list-inside list-decimal text-amber-700">
            <li>
              Minta Admin/Purchasing membuka <span className="font-medium">Admin → Barang</span>, cari SKU item di bawah, lalu isi{' '}
              <span className="font-medium">Supplier Default</span>-nya.
            </li>
            <li>
              Kembali ke halaman ini dan klik lagi <span className="font-medium">Generate SJ Susulan</span> pada Order terkait di tabel
              bawah — item akan otomatis diproses ulang begitu supplier-nya terisi.
            </li>
          </ol>
          <ul className="list-inside list-disc">
            {stuckItems.map((row) => (
              <li key={row.id}>
                {row.item_name} ({formatQty(row.qty_ordered)}) — Order {row.order_number}
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari no. order atau dapur..." />
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <SortableTableHead label="No. Order" columnKey="order_number" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Dapur" columnKey="dapur" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Tanggal Kirim" columnKey="delivery_date" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="Item Belum Ada SJ" columnKey="pending_items" sort={sort} onSort={toggleSort} />
            <SortableTableHead label="SJ Sudah Terbit" columnKey="sj_count" sort={sort} onSort={toggleSort} />
            <TableHead className="text-right">Aksi</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="h-32 text-center text-gray-400">
                {orders.length === 0 ? 'Belum ada Order dengan aktivitas Surat Jalan.' : 'Tidak ada order yang cocok dengan pencarian.'}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((order) => (
              <TableRow key={order.id}>
                <TableCell className="font-medium">{order.order_number}</TableCell>
                <TableCell>{order.dapur?.name}</TableCell>
                <TableCell>{order.delivery_date}</TableCell>
                <TableCell>{order.pending_items_count}</TableCell>
                <TableCell>
                  {order.surat_jalans_count === 0 ? (
                    <span className="text-gray-400">—</span>
                  ) : (
                    <div className="flex flex-col gap-0.5">
                      {(order.surat_jalans ?? []).map((sj) => (
                        <div key={sj.id} className="flex items-center gap-2">
                          <span>{sj.sj_number}</span>
                          {sj.pdf_url && (
                            <a
                              href={route('ops.delivery-notes.download', sj.id)}
                              className="text-xs font-medium text-blue-600 hover:underline"
                            >
                              SJ
                            </a>
                          )}
                          {sj.tanda_terima_pdf_url && (
                            <a
                              href={route('ops.delivery-notes.tanda-terima.download', sj.id)}
                              className="text-xs font-medium text-emerald-600 hover:underline"
                            >
                              Tanda Terima
                            </a>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </TableCell>
                <TableCell className="text-right">
                  {order.pending_items_count > 0 ? (
                    <Button size="sm" onClick={() => generate(order.id)} disabled={processingId === order.id}>
                      {processingId === order.id
                        ? 'Memproses...'
                        : order.surat_jalans_count === 0
                          ? 'Generate SJ Utama'
                          : 'Generate SJ Susulan'}
                    </Button>
                  ) : (
                    <span className="text-sm text-gray-400">Selesai</span>
                  )}
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
