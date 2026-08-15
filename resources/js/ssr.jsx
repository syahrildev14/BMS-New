/* prettier-ignore */
import {
createInertiaApp
} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { QueryClientProvider } from '@tanstack/react-query';
import { createQueryClient } from './shared/lib/queryClient';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.tsx', {
                eager: true,
            });
            return pages[`./pages/${name}.tsx`];
        },
        // prettier-ignore
        setup: ({ App, props }) => {
            // QueryClient baru per request — WAJIB, jangan pakai singleton di server,
            // supaya cache tidak bocor antar user/request yang diproses bersamaan.
            const queryClient = createQueryClient();
            return (
                <QueryClientProvider client={queryClient}>
                    <App {...props} />
                </QueryClientProvider>
            );
        },
    }),
);
