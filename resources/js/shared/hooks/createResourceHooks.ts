import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { AxiosError } from 'axios';
import type { ResourceService } from '@/shared/services/createResourceService';
import type { ApiValidationError, ResourceQueryParams } from '@/shared/types/api.type';
import { errorAlert, successAlert } from '@/shared/lib/alerts';

/**
 * Factory generic untuk hooks TanStack Query di atas createResourceService.
 * Menyediakan pola konsisten: useList, useDetail, useCreate, useUpdate, useDelete.
 *
 * queryKey harus unik per modul, mis. ['barang'], ['supplier'].
 */
export function createResourceHooks<
  TEntity,
  TCreatePayload = Partial<TEntity>,
  TUpdatePayload = Partial<TEntity>,
>(service: ResourceService<TEntity, TCreatePayload, TUpdatePayload>, queryKey: string[]) {
  function useList(params?: ResourceQueryParams) {
    return useQuery({
      queryKey: [...queryKey, 'list', params],
      queryFn: () => service.getAll(params),
      placeholderData: keepPreviousData, // hindari flicker saat ganti halaman/sort/filter
    });
  }

  function useDetail(id: number | string | undefined) {
    return useQuery({
      queryKey: [...queryKey, 'detail', id],
      queryFn: () => service.getById(id as number | string),
      enabled: !!id, // hanya fetch kalau id ada (mis. mode edit di dialog)
    });
  }

  function useCreate() {
    const qc = useQueryClient();
    return useMutation({
      mutationFn: (payload: TCreatePayload) => service.create(payload),
      onSuccess: () => {
        qc.invalidateQueries({ queryKey: [...queryKey, 'list'] });
        successAlert('Data berhasil ditambahkan');
      },
      onError: (error: AxiosError<ApiValidationError>) => {
        errorAlert(error.response?.data?.message ?? 'Gagal menambahkan data');
      },
    });
  }

  function useUpdate() {
    const qc = useQueryClient();
    return useMutation({
      mutationFn: ({ id, payload }: { id: number | string; payload: TUpdatePayload }) =>
        service.update(id, payload),
      onSuccess: () => {
        qc.invalidateQueries({ queryKey: [...queryKey, 'list'] });
        successAlert('Data berhasil diperbarui');
      },
      onError: (error: AxiosError<ApiValidationError>) => {
        errorAlert(error.response?.data?.message ?? 'Gagal memperbarui data');
      },
    });
  }

  function useDelete() {
    const qc = useQueryClient();
    return useMutation({
      mutationFn: (id: number | string) => service.remove(id),
      onSuccess: () => {
        qc.invalidateQueries({ queryKey: [...queryKey, 'list'] });
        successAlert('Data berhasil dihapus');
      },
      onError: (error: AxiosError<ApiValidationError>) => {
        errorAlert(error.response?.data?.message ?? 'Gagal menghapus data');
      },
    });
  }

  return { useList, useDetail, useCreate, useUpdate, useDelete };
}
