import { Head, router } from '@inertiajs/react';
import { type FormEventHandler, type ReactElement, useState } from 'react';
import { AppLayout } from '@/layouts/AppLayout';
import { PageHeader } from '@/shared/components/layout/PageHeader';
import { Button } from '@/shared/components/ui/button';
import { Input } from '@/shared/components/ui/input';
import { SortableTableHead } from '@/shared/components/ui/sortable-table-head';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TablePagination } from '@/shared/components/ui/table-pagination';
import { TableSearchInput } from '@/shared/components/ui/table-search-input';
import { useTableControls, type TableColumn } from '@/shared/hooks/use-table-controls';
import { errorAlert } from '@/shared/lib/alerts';
import type { DailyPrice, Item } from '@/shared/types/prd.type';

const columns: TableColumn<Item>[] = [
  { key: 'name', accessor: (item) => item.name },
  { key: 'supplier', accessor: (item) => item.default_supplier?.name },
  { key: 'margin', accessor: (item) => Number(item.margin_value) },
];

export default function AdminPricingIndex({
  date,
  items,
  dailyPrices,
}: {
  date: string;
  items: Item[];
  dailyPrices: Record<number, DailyPrice>;
}) {
  const [selectedDate, setSelectedDate] = useState(date);
  const [costPrices, setCostPrices] = useState<Record<number, string>>(
    Object.fromEntries(items.map((item) => [item.id, dailyPrices[item.id]?.cost_price ?? ''])),
  );
  const [processing, setProcessing] = useState(false);
  const { search, setSearch, sort, toggleSort, rows, page, setPage, totalPages, totalItems, pageSize } = useTableControls(items, columns);

  const changeDate = (value: string) => {
    setSelectedDate(value);
    router.get(route('admin.pricing.index'), { date: value });
  };

  const submit: FormEventHandler = (e) => {
    e.preventDefault();

    const prices = items
      .filter((item) => costPrices[item.id])
      .map((item) => ({ item_id: item.id, cost_price: Number(costPrices[item.id]) }));

    if (prices.length === 0) return;

    setProcessing(true);
    router.post(
      route('admin.pricing.store'),
      { date: selectedDate, prices },
      {
        onFinish: () => setProcessing(false),
        onError: (errors) => errorAlert(Object.values(errors)[0] ?? 'Periksa kembali input harga.'),
      },
    );
  };

  return (
    <>
      <Head title="Harga Harian" />

      <PageHeader
        title="Daily Dynamic Pricing"
        description="Input harga beli harian — harga jual terhitung otomatis dan langsung men-sync base_cost_price / base_selling_price master Item."
        breadcrumbs={[{ label: 'Master' }, { label: 'Harga Harian' }]}
        actions={
          <Input type="date" value={selectedDate} onChange={(e) => changeDate(e.target.value)} className="w-40" />
        }
      />

      <div className="mb-4">
        <TableSearchInput value={search} onChange={setSearch} placeholder="Cari nama barang atau supplier..." />
      </div>

      <form onSubmit={submit} className="flex flex-col gap-4">
        <Table>
          <TableHeader>
            <TableRow>
              <SortableTableHead label="Item" columnKey="name" sort={sort} onSort={toggleSort} />
              <SortableTableHead label="Supplier Default" columnKey="supplier" sort={sort} onSort={toggleSort} />
              <SortableTableHead label="Margin" columnKey="margin" sort={sort} onSort={toggleSort} />
              <TableHead>Harga Beli ({selectedDate})</TableHead>
              <TableHead>Harga Jual Terhitung</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="h-32 text-center text-gray-400">
                  Tidak ada barang yang cocok dengan pencarian.
                </TableCell>
              </TableRow>
            )}
            {rows.map((item) => (
              <TableRow key={item.id}>
                <TableCell className="font-medium">
                  {item.name} ({item.satuan?.name})
                </TableCell>
                <TableCell>{item.default_supplier?.name ?? '-'}</TableCell>
                <TableCell>
                  {item.margin_type === 'PERCENTAGE' ? `${item.margin_value}%` : `Rp${item.margin_value}`}
                </TableCell>
                <TableCell>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    className="w-32"
                    value={costPrices[item.id] ?? ''}
                    onChange={(e) => setCostPrices((prev) => ({ ...prev, [item.id]: e.target.value }))}
                  />
                </TableCell>
                <TableCell>{dailyPrices[item.id]?.calculated_price ?? '-'}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        <TablePagination page={page} totalPages={totalPages} totalItems={totalItems} pageSize={pageSize} onPageChange={setPage} />

        <div className="flex justify-end">
          <Button type="submit" disabled={processing}>
            {processing ? 'Menyimpan...' : 'Simpan Harga Harian'}
          </Button>
        </div>
      </form>
    </>
  );
}

AdminPricingIndex.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;
