import { createResourceService } from '@/shared/services/createResourceService';
import type { Satuan, SatuanCreatePayload, SatuanUpdatePayload } from '../types/satuan.type';

/**
 * Endpoint disesuaikan dengan route API Laravel yang sudah ada: /api/master/satuan
 * Kalau nanti butuh endpoint khusus, tambahkan method di sini —
 * JANGAN ubah createResourceService generic.
 */
export const satuanService = createResourceService<Satuan, SatuanCreatePayload, SatuanUpdatePayload>(
  '/master/satuan',
);
