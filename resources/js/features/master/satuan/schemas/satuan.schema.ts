import { z } from 'zod';

/**
 * Zod schema HARUS selaras dengan Form Request Laravel di backend (rules yang sama).
 * Ini validasi client-side untuk UX cepat; validasi final tetap di backend.
 */
export const satuanSchema = z.object({
  kode_satuan: z.string().min(1, 'Kode satuan wajib diisi').max(255),
  nama_satuan: z.string().min(1, 'Nama satuan wajib diisi').max(255),
});

export type SatuanFormValues = z.infer<typeof satuanSchema>;
