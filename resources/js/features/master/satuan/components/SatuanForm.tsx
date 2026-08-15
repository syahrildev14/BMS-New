import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Input } from '@/shared/components/ui/input';
import { Label } from '@/shared/components/ui/label';
import { Button } from '@/shared/components/ui/button';
import { DialogFooter } from '@/shared/components/ui/dialog';
import { satuanSchema, type SatuanFormValues } from '../schemas/satuan.schema';
import type { Satuan } from '../types/satuan.type';

interface SatuanFormProps {
  defaultValues?: Satuan | null;
  onSubmit: (values: SatuanFormValues) => void;
  onCancel: () => void;
  isSubmitting: boolean;
}

const emptyValues: SatuanFormValues = {
  kode_satuan: '',
  nama_satuan: '',
};

export function SatuanForm({ defaultValues, onSubmit, onCancel, isSubmitting }: SatuanFormProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<SatuanFormValues>({
    resolver: zodResolver(satuanSchema),
    defaultValues: emptyValues,
  });

  // Sinkronkan form ketika mode edit (defaultValues berubah saat dialog dibuka untuk row berbeda)
  useEffect(() => {
    if (defaultValues) {
      reset({
        kode_satuan: defaultValues.kode_satuan,
        nama_satuan: defaultValues.nama_satuan,
      });
    } else {
      reset(emptyValues);
    }
  }, [defaultValues, reset]);

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="kode_satuan">Kode Satuan</Label>
        <Input id="kode_satuan" {...register('kode_satuan')} placeholder="KG" />
        {errors.kode_satuan && <p className="text-xs text-red-500">{errors.kode_satuan.message}</p>}
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="nama_satuan">Nama Satuan</Label>
        <Input id="nama_satuan" {...register('nama_satuan')} placeholder="Kilogram" />
        {errors.nama_satuan && <p className="text-xs text-red-500">{errors.nama_satuan.message}</p>}
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting} className='bg-secondary hover:bg-secondary/70'>
          Batal
        </Button>
        <Button type="submit" disabled={isSubmitting} className='bg-primary hover:bg-primary/70'>
          {isSubmitting ? 'Menyimpan...' : 'Simpan'}
        </Button>
      </DialogFooter>
    </form>
  );
}
