import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

export const primaryButton = 'bg-primary text-white hover:bg-primary/90 focus:ring-primary';
export const secondaryButton = 'border border-border bg-surface text-ink hover:bg-soft focus:ring-primary';
export const loginLink = 'inline-flex justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2';

export default function AuthShell({ title, children }: { title: string; children: ReactNode }) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-page p-4 font-sans text-ink sm:p-8">
            <Head title={title} />
            <div className="w-full max-w-lg">
                <header className="mb-6 text-center">
                    <img src="/img/dikti16-favicon-blue-150x150.png" width={150} height={150} alt="" className="mx-auto mb-4 h-16 w-16 object-contain" />
                    <p className="text-xl font-bold text-primary">SAKIP LLDIKTI XVI</p>
                    <p className="mt-2 text-sm text-muted">Sistem Akuntabilitas Kinerja Instansi Pemerintah</p>
                </header>
                <section className="rounded-xl border border-border bg-surface p-6 shadow-sm sm:p-8" aria-labelledby="auth-title">
                    <h1 id="auth-title" className="text-xl font-semibold">{title}</h1>
                    {children}
                </section>
            </div>
        </main>
    );
}
