import { api } from '@/shared/lib/axios';
import type {
  ApiResponse,
  PaginatedResponse,
  ResourceQueryParams,
} from '@/shared/types/api.type';

/**
 * Factory generic untuk resource CRUD standar.
 *
 * Dipakai oleh modul master sederhana (Barang, Supplier, Gudang, Satuan, Armada, SPPG) tanpa
 * perlu menulis ulang axios call di tiap modul. Modul yang butuh endpoint tambahan
 * (mis. approve, cancel, generate PDF) tinggal extend hasil factory ini dengan method tambahan
 * di file service masing-masing — TIDAK perlu mengubah factory ini.
 *
 * @param endpoint - path relatif terhadap baseURL axios, mis. '/master/barang'
 */
export function createResourceService<
  TEntity,
  TCreatePayload = Partial<TEntity>,
  TUpdatePayload = Partial<TEntity>,
>(endpoint: string) {
  return {
    async getAll(params?: ResourceQueryParams): Promise<PaginatedResponse<TEntity>> {
      const { data } = await api.get<PaginatedResponse<TEntity>>(endpoint, { params });
      return data;
    },

    async getById(id: number | string): Promise<TEntity> {
      const { data } = await api.get<ApiResponse<TEntity>>(`${endpoint}/${id}`);
      return data.data;
    },

    async create(payload: TCreatePayload): Promise<TEntity> {
      const { data } = await api.post<ApiResponse<TEntity>>(endpoint, payload);
      return data.data;
    },

    async update(id: number | string, payload: TUpdatePayload): Promise<TEntity> {
      const { data } = await api.put<ApiResponse<TEntity>>(`${endpoint}/${id}`, payload);
      return data.data;
    },

    async remove(id: number | string): Promise<void> {
      await api.delete(`${endpoint}/${id}`);
    },
  };
}

export type ResourceService<TEntity, TCreate = Partial<TEntity>, TUpdate = Partial<TEntity>> =
  ReturnType<typeof createResourceService<TEntity, TCreate, TUpdate>>;
