import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const SHOW_DELAY_MS = 200;

/**
 * Global loading screen for every Inertia navigation/form submission — one
 * instance mounted in AppProviders covers every page without per-page code.
 * Delayed by SHOW_DELAY_MS so fast transitions don't flash the overlay.
 */
export function PageLoadingOverlay() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    let showTimer: ReturnType<typeof setTimeout> | null = null;

    const clearShowTimer = () => {
      if (showTimer) {
        clearTimeout(showTimer);
        showTimer = null;
      }
    };

    const removeStart = router.on('start', () => {
      clearShowTimer();
      showTimer = setTimeout(() => setVisible(true), SHOW_DELAY_MS);
    });

    const removeFinish = router.on('finish', () => {
      clearShowTimer();
      setVisible(false);
    });

    return () => {
      clearShowTimer();
      removeStart();
      removeFinish();
    };
  }, []);

  if (!visible) return null;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center bg-white/60 backdrop-blur-sm dark:bg-gray-950/60"
      role="status"
      aria-live="polite"
    >
      <div className="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-5 py-3 shadow-lg dark:border-gray-800 dark:bg-gray-900">
        <span className="h-5 w-5 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600 dark:border-gray-700 dark:border-t-blue-400" />
        <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Memuat...</span>
      </div>
    </div>
  );
}
