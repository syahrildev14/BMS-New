import { Head, Link } from '@inertiajs/react';
import type { ReactElement } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import type { Order } from '@/shared/types/prd.type';

export default function DapurOrdersShow({ order }: { order: Order }) {
  return (
    <>
      <Head title={order.order_number} />

      <PageHeader
        title={order.order_number}
        description={`Tanggal kirim: ${order.delivery_date} • Status: ${order.status}`}
        breadcrumbs={[{ label: 'Dapur' }, { label: 'Order Saya', href: route('dapur.orders.index') }, { label: order.order_number }]}
      />

      <div className="flex flex-col gap-8">
        <div>
          <h2 className="mb-2 text-sm font-semibold text-gray-700">Item Dipesan</h2>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Item</TableHead>
                <TableHead>Qty Pesan</TableHead>
                <TableHead>Qty Terima</TableHead>
                <TableHead>Qty Reject</TableHead>
                <TableHead>Harga Estimasi</TableHead>
                <TableHead>Add-on</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(order.order_items ?? []).map((orderItem) => (
                <TableRow key={orderItem.id}>
                  <TableCell className="font-medium">{orderItem.item?.name}</TableCell>
                  <TableCell>{orderItem.qty_ordered}</TableCell>
                  <TableCell>{orderItem.qty_received ?? '-'}</TableCell>
                  <TableCell>{orderItem.qty_rejected ?? '-'}</TableCell>
                  <TableCell>{orderItem.estimated_price}</TableCell>
                  <TableCell>{orderItem.is_addon ? 'Ya' : 'Tidak'}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        <div>
          <h2 className="mb-2 text-sm font-semibold text-gray-700">Surat Jalan</h2>
          {(order.surat_jalans ?? []).length === 0 ? (
            <p className="text-sm text-gray-400">Belum ada Surat Jalan diterbitkan untuk order ini.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>No. SJ</TableHead>
                  <TableHead>Tipe</TableHead>
                  <TableHead>Urutan</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {(order.surat_jalans ?? []).map((sj) => (
                  <TableRow key={sj.id}>
                    <TableCell className="font-medium">{sj.sj_number}</TableCell>
                    <TableCell>{sj.type}</TableCell>
                    <TableCell>{sj.sequence_number}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </div>

        <Link href={route('dapur.orders.index')} className="text-sm text-blue-600 hover:underline">
          &larr; Kembali ke daftar order
        </Link>
      </div>
    </>
  );
}

DapurOrdersShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
