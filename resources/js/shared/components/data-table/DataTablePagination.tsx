import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/shared/components/ui/button';
import type { PaginatedResponse } from '@/shared/types/api.type';

interface DataTablePaginationProps<TData> {
  meta: PaginatedResponse<TData>['meta'] | undefined;
  onPageChange: (page: number) => void;
}

export function DataTablePagination<TData>({
  meta,
  onPageChange,
}: DataTablePaginationProps<TData>) {
  if (!meta) return null;

  return (
    <div className="flex items-center justify-between border-t border-gray-100 px-4 py-3">
      <p className="text-sm text-gray-500">
        Menampilkan <span className="font-medium text-gray-700">{meta.from ?? 0}</span>–
        <span className="font-medium text-gray-700">{meta.to ?? 0}</span> dari{' '}
        <span className="font-medium text-gray-700">{meta.total}</span> data
      </p>

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="icon"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          <ChevronLeft className="h-4 w-4" />
        </Button>
        <span className="min-w-[80px] text-center text-sm text-gray-600">
          Hal {meta.current_page} / {meta.last_page || 1}
        </span>
        <Button
          variant="outline"
          size="icon"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
