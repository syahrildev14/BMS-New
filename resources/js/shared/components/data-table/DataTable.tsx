import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { Loader2 } from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/shared/components/ui/table';
import { DataTableToolbar } from './DataTableToolbar';
import { DataTablePagination } from './DataTablePagination';
import type { DataTableProps } from './data-table.types';

/**
 * DataTable generic satu-satunya di seluruh aplikasi.
 * Sorting/filtering/pagination dilakukan SERVER-SIDE (via TanStack Query di level Page),
 * komponen ini murni presentational + state UI lokal (bukan pemilik data).
 *
 * Pola pakai:
 *   <DataTable columns={barangColumns} response={data} isLoading={isLoading}
 *              page={page} onPageChange={setPage} search={search} onSearchChange={setSearch} />
 */
export function DataTable<TData>({
  columns,
  response,
  isLoading,
  isFetching,
  page,
  onPageChange,
  search,
  onSearchChange,
  toolbarRight,
  emptyMessage = 'Belum ada data',
}: DataTableProps<TData>) {
  const table = useReactTable({
    data: response?.data ?? [],
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
  });

  return (
    <div className="flex flex-col gap-4">
      <DataTableToolbar search={search} onSearchChange={onSearchChange} toolbarRight={toolbarRight} />

      <div className="relative overflow-hidden rounded-lg border border-gray-200 bg-white">
        {isFetching && !isLoading && (
          <div className="absolute right-3 top-3 z-10">
            <Loader2 className="h-4 w-4 animate-spin text-blue-500" />
          </div>
        )}

        <Table>
          <TableHeader className="bg-gray-50">
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id} className="hover:bg-transparent">
                {headerGroup.headers.map((header) => (
                  <TableHead key={header.id} className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {header.isPlaceholder
                      ? null
                      : flexRender(header.column.columnDef.header, header.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>

          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-32 text-center text-gray-400">
                  <Loader2 className="mx-auto h-5 w-5 animate-spin" />
                </TableCell>
              </TableRow>
            ) : table.getRowModel().rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={columns.length} className="h-32 text-center text-sm text-gray-400">
                  {emptyMessage}
                </TableCell>
              </TableRow>
            ) : (
              table.getRowModel().rows.map((row) => (
                <TableRow key={row.id} className="hover:bg-gray-50/70">
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id} className="text-sm text-gray-700">
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>

        <DataTablePagination meta={response?.meta} onPageChange={onPageChange} />
      </div>
    </div>
  );
}
