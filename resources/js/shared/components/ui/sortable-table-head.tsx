import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { TableHead } from '@/shared/components/ui/table';
import { cn } from '@/shared/lib/utils';
import type { SortState } from '@/shared/hooks/use-table-controls';

interface SortableTableHeadProps {
  label: string;
  columnKey: string;
  sort: SortState | null;
  onSort: (key: string) => void;
  className?: string;
}

export function SortableTableHead({ label, columnKey, sort, onSort, className }: SortableTableHeadProps) {
  const active = sort?.key === columnKey;
  const Icon = active ? (sort?.dir === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown;

  return (
    <TableHead className={className}>
      <button
        type="button"
        onClick={() => onSort(columnKey)}
        className={cn(
          'inline-flex items-center gap-1 text-left text-xs font-semibold uppercase tracking-wide transition-colors hover:text-gray-900 dark:hover:text-gray-100',
          active ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400',
        )}
      >
        {label}
        <Icon className="h-3.5 w-3.5" />
      </button>
    </TableHead>
  );
}
