import { createRoot } from 'react-dom/client';
import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { AuthRecoveryFallback } from '@/Components/Auth/AuthRecoveryFallback';

// Kembali dari bfcache harus memeriksa session dan izin terbaru di server.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});

createInertiaApp({
    title: (title) => (title ? `${title} - SAKIP LLDIKTI XVI` : 'SAKIP LLDIKTI XVI'),
    resolve: (name) => {
        const pages = import.meta.glob<{ default: ResolvedComponent }>('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`];
        if (!page) {
            throw new Error(`Page component not found: ./Pages/${name}.tsx`);
        }
        return page;
    },
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<><App {...props} /><AuthRecoveryFallback /></>);
    },
    progress: {
        color: 'var(--color-primary)',
        showSpinner: true,
    },
});
