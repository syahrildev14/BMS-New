import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/shared/components/ui/dialog';
import { SatuanForm } from './SatuanForm';
import { useCreateSatuan, useUpdateSatuan } from '../hooks/useSatuan';
import type { Satuan } from '../types/satuan.type';
import type { SatuanFormValues } from '../schemas/satuan.schema';

interface SatuanDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** null = mode create, terisi = mode edit */
  satuan: Satuan | null;
}

export function SatuanDialog({ open, onOpenChange, satuan }: SatuanDialogProps) {
  const createMutation = useCreateSatuan();
  const updateMutation = useUpdateSatuan();

  const isEdit = !!satuan;
  const isSubmitting = createMutation.isPending || updateMutation.isPending;

  function handleSubmit(values: SatuanFormValues) {
    if (isEdit && satuan) {
      updateMutation.mutate(
        { id: satuan.id, payload: values },
        { onSuccess: () => onOpenChange(false) },
      );
    } else {
      createMutation.mutate(values, { onSuccess: () => onOpenChange(false) });
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Satuan' : 'Tambah Satuan'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'Perbarui informasi satuan di bawah ini.' : 'Isi informasi satuan baru.'}
          </DialogDescription>
        </DialogHeader>

        <SatuanForm
          defaultValues={satuan}
          onSubmit={handleSubmit}
          onCancel={() => onOpenChange(false)}
          isSubmitting={isSubmitting}
        />
      </DialogContent>
    </Dialog>
  );
}
