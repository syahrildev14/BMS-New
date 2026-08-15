import type { ColumnDef } from '@tanstack/react-table';
import type { PaginatedResponse } from '@/shared/types/api.type';

export interface DataTableProps<TData> {
  columns: ColumnDef<TData, any>[];
  /** Response paginated langsung dari TanStack Query (data?.data, data?.meta) */
  response: PaginatedResponse<TData> | undefined;
  isLoading: boolean;
  isFetching?: boolean;

  // Server-side state — dikontrol oleh parent (Page), bukan internal state DataTable
  page: number;
  onPageChange: (page: number) => void;
  search: string;
  onSearchChange: (value: string) => void;

  /** Slot bebas di sisi kanan toolbar: filter dropdown, button export, dsb (per modul beda-beda) */
  toolbarRight?: React.ReactNode;
  /** Teks saat data kosong, bisa dikustomisasi per modul */
  emptyMessage?: string;
}
