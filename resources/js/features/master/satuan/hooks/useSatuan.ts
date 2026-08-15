import { createResourceHooks } from '@/shared/hooks/createResourceHooks';
import { satuanService } from '../services/satuan.service';
import type { Satuan, SatuanCreatePayload, SatuanUpdatePayload } from '../types/satuan.type';

export const {
  useList: useSatuanList,
  useDetail: useSatuanDetail,
  useCreate: useCreateSatuan,
  useUpdate: useUpdateSatuan,
  useDelete: useDeleteSatuan,
} = createResourceHooks<Satuan, SatuanCreatePayload, SatuanUpdatePayload>(satuanService, ['satuan']);
