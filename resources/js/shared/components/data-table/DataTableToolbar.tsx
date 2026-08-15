import { Search } from 'lucide-react';
import { Input } from '@/shared/components/ui/input';

interface DataTableToolbarProps {
  search: string;
  onSearchChange: (value: string) => void;
  toolbarRight?: React.ReactNode;
  searchPlaceholder?: string;
}

export function DataTableToolbar({
  search,
  onSearchChange,
  toolbarRight,
  searchPlaceholder = 'Cari data...',
}: DataTableToolbarProps) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="relative w-full sm:w-72">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <Input
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder={searchPlaceholder}
          className="pl-9"
        />
      </div>

      {toolbarRight && <div className="flex items-center gap-2">{toolbarRight}</div>}
    </div>
  );
}
