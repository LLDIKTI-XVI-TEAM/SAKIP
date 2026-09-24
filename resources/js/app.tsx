import { createRoot } from 'react-dom/client';
import { createInertiaApp, router, type ResolvedComponent } from '@inertiajs/react';
import { AuthRecoveryFallback } from '@/Components/Auth/AuthRecoveryFallback';
import type { SharedPageProps } from '@/types/auth';

// Kembali dari bfcache harus memeriksa session dan izin terbaru di server.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});

let currentAppName = (typeof document !== 'undefined' && document.querySelector('meta[name="app-name"]')?.getAttribute('content'))
    || 'SAKIP LLDIKTI XVI';

router.on('navigate', (event) => {
    const pageProps = event.detail.page.props as Partial<SharedPageProps>;
    const appName = pageProps.pengaturan?.['aplikasi.nama'] as string | undefined;
    if (appName) {
        currentAppName = appName;
        if (typeof document !== 'undefined') {
            document.querySelector('meta[name="app-name"]')?.setAttribute('content', appName);
        }
    }
});

createInertiaApp({
    title: (title) => {
        return title ? `${title} - ${currentAppName}` : currentAppName;
    },
    resolve: (name) => {
        const pages = import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Page component not found: ./Pages/${name}.tsx`);
        }
        return page;
    },
    setup({ el, App, props }) {
        const initialAppName = (props.initialPage.props as Partial<SharedPageProps>).pengaturan?.['aplikasi.nama'] as string | undefined;
        if (initialAppName) {
            currentAppName = initialAppName;
            if (typeof document !== 'undefined') {
                document.querySelector('meta[name="app-name"]')?.setAttribute('content', initialAppName);
            }
        }
        const root = createRoot(el);
        root.render(<><App {...props} /><AuthRecoveryFallback /></>);
    },
    progress: {
        color: 'var(--color-primary)',
        showSpinner: true,
    },
});
