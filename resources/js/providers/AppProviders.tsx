import { useState } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { createQueryClient } from '@/shared/lib/queryClient';
import { PageLoadingOverlay } from '@/shared/components/layout/PageLoadingOverlay';

export function AppProviders({ children }: { children: React.ReactNode }) {
  // useState(() => ...) memastikan QueryClient dibuat SEKALI per mount, tidak dibuat ulang tiap render
  const [queryClient] = useState(() => createQueryClient());

  return (
    <QueryClientProvider client={queryClient}>
      <PageLoadingOverlay />
      {children}
    </QueryClientProvider>
  );
}
