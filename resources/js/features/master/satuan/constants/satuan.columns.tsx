import type { ColumnDef } from '@tanstack/react-table';
import { Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/shared/components/ui/button';
import type { Satuan } from '../types/satuan.type';

export function getSatuanColumns(
  onEdit: (satuan: Satuan) => void,
  onDelete: (satuan: Satuan) => void,
): ColumnDef<Satuan>[] {
  return [
    {
      id: "no",
      header: "No",
      cell: ({ row }) => row.index + 1,
    },
    {
      accessorKey: 'kode_satuan',
      header: 'Kode Satuan',
    },
    {
      accessorKey: 'nama_satuan',
      header: 'Nama Satuan',
    },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) => (
        <div className="flex justify-end gap-1">
          <Button
            variant="ghost"
            size="icon"
            onClick={() => onEdit(row.original)}
          >
            <Pencil className="h-4 w-4 text-gray-500" />
          </Button>

          <Button
            variant="ghost"
            size="icon"
            onClick={() => onDelete(row.original)}
          >
            <Trash2 className="h-4 w-4 text-red-500" />
          </Button>
        </div>
      ),
    },
  ];
}