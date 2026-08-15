import { QueryClient } from '@tanstack/react-query';

/**
 * Factory, BUKAN singleton — dipanggil sekali di client entry (app.tsx, aman sebagai
 * satu instance per browser tab), dan dipanggil ULANG per request di ssr.jsx (server
 * menangani banyak request bersamaan, tidak boleh berbagi cache antar user/request).
 */
export function createQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 60 * 1000,
        retry: 1,
        refetchOnWindowFocus: false,
      },
      mutations: {
        retry: 0,
      },
    },
  });
}
