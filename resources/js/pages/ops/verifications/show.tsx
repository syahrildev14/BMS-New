import { Head, Link, router } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Badge } from '@/shared/components/ui/badge';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { errorAlert } from '@/shared/lib/alerts';
import { formatQty } from '@/shared/lib/format';
import type { DeliveryDocumentType, DeliveryVerificationStatus } from '@/shared/types/prd.type';

interface Line {
  id: number;
  item_name: string;
  supplier_name: string | null;
  satuan: string | null;
  order_number: string;
  qty_ordered: number;
  qty_received: number | null;
  qty_rejected: number | null;
  rejection_reason: string | null;
  qty_nota: number | null;
}

interface VerificationDocument {
  id: number;
  sj_number: string;
  document_type: DeliveryDocumentType;
  shipment_date: string;
  dapur_name: string;
  supplier_name: string;
  verification_status: DeliveryVerificationStatus;
  verification_notes: string | null;
  verified_by_name: string | null;
  verified_at: string | null;
  nota_number: string | null;
  photos: { id: number; original_name: string; url: string }[];
  lines: Line[];
}

const lineDiffers = (line: Line) =>
  line.qty_received === null ||
  line.qty_received !== line.qty_ordered ||
  (line.qty_rejected ?? 0) > 0 ||
  (line.qty_nota !== null && line.qty_nota !== line.qty_ordered);

export default function VerificationShow({ document: doc }: { document: VerificationDocument }) {
  const [notes, setNotes] = useState(doc.verification_notes ?? '');
  const [processing, setProcessing] = useState(false);

  const isWet = doc.document_type === 'TANDA_TERIMA';
  const isVerified = doc.verification_status === 'VERIFIED';
  const sttComplete = doc.lines.every((line) => line.qty_received !== null);

  const submit = (routeName: 'ops.verifications.verify' | 'ops.verifications.discrepancy') => {
    setProcessing(true);
    router.post(
      route(routeName, doc.id),
      { notes },
      {
        preserveScroll: true,
        onFinish: () => setProcessing(false),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Gagal menyimpan status.'),
      },
    );
  };

  return (
    <>
      <Head title={`Crosscheck ${doc.sj_number}`} />

      <PageHeader
        title={doc.sj_number}
        description={`${isWet ? 'Tanda Terima bahan basah' : 'Surat Jalan bahan kering'} — ${doc.dapur_name} — ${doc.supplier_name} — kirim ${doc.shipment_date}`}
        breadcrumbs={[{ label: 'Ops' }, { label: 'Crosscheck H+1' }, { label: doc.sj_number }]}
      />

      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-center gap-3 text-sm">
          {isVerified && <Badge variant="success">Terverifikasi</Badge>}
          {doc.verification_status === 'DISCREPANCY' && <Badge variant="warning">Ada Selisih</Badge>}
          {doc.verification_status === 'PENDING' && <Badge>Menunggu</Badge>}
          {doc.verified_by_name && (
            <span className="text-gray-500">
              oleh {doc.verified_by_name}, {doc.verified_at}
            </span>
          )}
          {isWet && <span className="text-gray-500">{doc.nota_number ? `Nota: ${doc.nota_number}` : 'Nota belum diinput'}</span>}
        </div>

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Barang</TableHead>
              <TableHead>Supplier</TableHead>
              <TableHead>Order</TableHead>
              <TableHead className="text-right">Qty Pesan</TableHead>
              <TableHead className="text-right">Diterima</TableHead>
              <TableHead className="text-right">Ditolak</TableHead>
              {isWet && <TableHead className="text-right">Qty Nota</TableHead>}
              <TableHead>Alasan Tolak</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {doc.lines.map((line) => (
              <TableRow key={line.id} className={lineDiffers(line) ? 'bg-amber-50 dark:bg-amber-950' : ''}>
                <TableCell className="font-medium">{line.item_name}</TableCell>
                <TableCell>{line.supplier_name ?? '—'}</TableCell>
                <TableCell className="text-xs">{line.order_number}</TableCell>
                <TableCell className="text-right">
                  {formatQty(line.qty_ordered)} {line.satuan}
                </TableCell>
                <TableCell className="text-right">{line.qty_received === null ? 'Belum diisi' : formatQty(line.qty_received)}</TableCell>
                <TableCell className="text-right">{line.qty_rejected === null ? '—' : formatQty(line.qty_rejected)}</TableCell>
                {isWet && <TableCell className="text-right">{line.qty_nota === null ? '—' : formatQty(line.qty_nota)}</TableCell>}
                <TableCell>{line.rejection_reason ?? '—'}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        <div>
          <h2 className="mb-2 text-sm font-semibold text-gray-700">Foto dari Dapur ({doc.photos.length})</h2>
          {doc.photos.length === 0 ? (
            <p className="text-sm text-amber-700">Belum ada foto dari Dapur.</p>
          ) : (
            <div className="flex flex-wrap gap-3">
              {doc.photos.map((photo) => (
                <a key={photo.id} href={photo.url} target="_blank" rel="noreferrer">
                  <img src={photo.url} alt={photo.original_name} className="h-32 w-32 rounded-md border border-gray-200 object-cover" />
                </a>
              ))}
            </div>
          )}
        </div>

        <div className="flex flex-col gap-3 rounded-lg border border-gray-200 p-4">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="notes">Catatan (wajib jika ada selisih)</Label>
            <Input id="notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
          <div className="flex flex-wrap justify-end gap-2">
            <Button type="button" variant="outline" asChild>
              <Link href={route('ops.verifications.index')}>Kembali</Link>
            </Button>
            <Button type="button" variant="outline" disabled={processing || notes.trim() === ''} onClick={() => submit('ops.verifications.discrepancy')}>
              {isVerified ? 'Buka Kembali (Ada Selisih)' : 'Tandai Ada Selisih'}
            </Button>
            {!isVerified && (
              <Button type="button" disabled={processing || !sttComplete} onClick={() => submit('ops.verifications.verify')}>
                Terverifikasi
              </Button>
            )}
          </div>
          {!sttComplete && !isVerified && <p className="text-right text-xs text-gray-500">Terverifikasi aktif setelah semua barang diisi STT oleh Dapur.</p>}
        </div>
      </div>
    </>
  );
}

VerificationShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
