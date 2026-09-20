import React, { ReactNode, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    FileSpreadsheet,
    CheckCircle2,
    LogOut,
    Building2,
    Calendar,
    Scale,
    ChevronRight,
    CheckCircle,
    AlertCircle,
    Menu,
    X,
} from 'lucide-react';
import { RoleSwitcher } from '@/Components/RoleSwitcher';

interface AuthenticatedLayoutProps {
    children: ReactNode;
    title?: string;
    breadcrumbs?: { label: string; href?: string }[];
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: {
            name?: string;
            roles?: string[];
            unit_kerja?: { nama?: string; singkatan?: string } | null;
        } | null;
    };
    flash?: {
        success?: string;
        error?: string;
    };
    can?: Record<string, boolean>;
}

export const AuthenticatedLayout: React.FC<AuthenticatedLayoutProps> = ({
    children,
    title,
    breadcrumbs = [],
}) => {
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const { auth, flash, can = {} } = usePage<SharedPageProps>().props;
    const user = auth?.user;
    const currentRole = user?.roles?.[0] || 'pegawai';
    const canReadRegulasi = can['regulasi:read'] === true;

    const handleLogout = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/logout');
    };

    const canViewVerification = can['verifikasi:read'] === true;

    return (
        <div className="flex min-h-screen flex-col overflow-x-hidden bg-page font-sans text-ink">
            {/* Top Quick Role Switcher for local dev */}
            <RoleSwitcher />

            <div className="flex flex-1 overflow-hidden">
                {mobileNavOpen && (
                    <button
                        type="button"
                        className="fixed inset-0 z-30 bg-ink/55 lg:hidden"
                        onClick={() => setMobileNavOpen(false)}
                        aria-label="Tutup navigasi"
                    />
                )}
                {/* Modern Institutional Sidebar */}
                <aside className={`fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 select-none flex-col border-r border-secondary/40 bg-primary text-surface transition-transform duration-200 lg:static lg:translate-x-0 ${mobileNavOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                    {/* Brand / Logo */}
                    <div className="p-5 border-b border-white/10 flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg border border-secondary/50 bg-surface text-lg font-bold text-primary">
                            S
                        </div>
                        <div>
                            <div className="text-base font-bold tracking-tight text-surface flex items-center gap-1.5">
                                SAKIP
                                <span className="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-bold uppercase text-ink">
                                    XVI
                                </span>
                            </div>
                            <div className="text-[11px] text-surface/75 font-normal leading-tight">
                                LLDIKTI Wilayah XVI
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setMobileNavOpen(false)}
                            className="ml-auto rounded-lg p-2 text-surface/75 hover:bg-surface/10 hover:text-surface focus:outline-none focus:ring-2 focus:ring-secondary/60 lg:hidden"
                            aria-label="Tutup menu"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {/* Navigation Menu */}
                    <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
                        <div className="px-3 py-2 text-[10px] font-semibold tracking-wider text-surface/60 uppercase">
                            Menu Utama
                        </div>

                        <Link
                            href="/dashboard"
                            className={`flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition-colors ${
                                window.location.pathname.startsWith('/dashboard')
                                    ? 'bg-surface/15 text-surface font-semibold shadow-xs'
                                    : 'text-surface/75 hover:bg-surface/10 hover:text-surface'
                            }`}
                        >
                            <LayoutDashboard className="h-4 w-4 text-secondary" />
                            Dashboard Capaian
                        </Link>

                        <Link
                            href="/pengukuran"
                            className={`flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition-colors ${
                                window.location.pathname.startsWith('/pengukuran')
                                    ? 'bg-surface/15 text-surface font-semibold shadow-xs'
                                    : 'text-surface/75 hover:bg-surface/10 hover:text-surface'
                            }`}
                        >
                            <FileSpreadsheet className="h-4 w-4 text-secondary" />
                            Pengukuran Kinerja
                        </Link>

                        {canReadRegulasi && (
                            <Link
                                href="/regulasi"
                                className={`flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition-colors ${
                                    window.location.pathname.startsWith('/regulasi')
                                        ? 'bg-surface/15 text-surface font-semibold shadow-xs'
                                        : 'text-surface/75 hover:bg-surface/10 hover:text-surface'
                                }`}
                            >
                                <Scale className="h-4 w-4 text-secondary" />
                                Dasar Aturan
                            </Link>
                        )}

                        {canViewVerification && (
                            <>
                                <div className="px-3 pt-4 pb-1 text-[10px] font-semibold tracking-wider text-surface/60 uppercase">
                                    Verifikasi & Reviu
                                </div>
                                <Link
                                    href="/verifikasi"
                                    className={`flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition-colors ${
                                        window.location.pathname.startsWith('/verifikasi')
                                            ? 'bg-surface/15 text-surface font-semibold shadow-xs'
                                            : 'text-surface/75 hover:bg-surface/10 hover:text-surface'
                                    }`}
                                >
                                    <CheckCircle2 className="h-4 w-4 text-secondary" />
                                    Verifikasi & Pengesahan
                                </Link>
                            </>
                        )}
                    </nav>

                    {/* Bottom User Profile */}
                    <div className="p-4 border-t border-surface/10 bg-ink/15">
                        <div className="flex items-center gap-3 mb-3">
                            <div className="flex h-9 w-9 items-center justify-center rounded-full border border-secondary/40 bg-primary text-xs font-bold uppercase text-surface">
                                {user?.name?.slice(0, 2) || 'US'}
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="text-xs font-semibold text-surface truncate">
                                    {user?.name || 'Pengguna'}
                                </div>
                                <div className="text-[11px] text-surface/60 truncate flex items-center gap-1">
                                    <span className="font-medium capitalize text-secondary">{currentRole}</span>
                                    <span>•</span>
                                    <span>{user?.unit_kerja?.singkatan || 'LLDIKTI'}</span>
                                </div>
                            </div>
                        </div>

                        <form onSubmit={handleLogout}>
                            <button
                                type="submit"
                                className="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium text-surface/75 hover:bg-danger/20 hover:text-surface transition-colors cursor-pointer"
                            >
                                <LogOut className="w-3.5 h-3.5" />
                                Keluar Sistem
                            </button>
                        </form>
                    </div>
                </aside>

                {/* Main Content Area */}
                <div className="flex min-w-0 flex-1 flex-col overflow-y-auto">
                    {/* Top Header */}
                    <header className="flex shrink-0 items-center justify-between gap-3 border-b border-border bg-surface px-4 py-3.5 sm:px-6">
                        {/* Breadcrumbs & Title */}
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setMobileNavOpen(true)}
                                className="shrink-0 rounded-lg border border-border bg-surface p-2 text-ink hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20 lg:hidden"
                                aria-label="Buka menu navigasi"
                                aria-expanded={mobileNavOpen}
                            >
                                <Menu className="h-5 w-5" />
                            </button>
                            <div className="min-w-0">
                            {breadcrumbs.length > 0 && (
                                <nav className="flex items-center gap-1.5 text-xs text-muted mb-1">
                                    <Link href="/dashboard" className="hover:text-ink">
                                        SAKIP
                                    </Link>
                                    {breadcrumbs.map((b, idx) => (
                                        <React.Fragment key={idx}>
                                            <ChevronRight className="w-3 h-3 text-muted" />
                                            {b.href ? (
                                                <Link href={b.href} className="hover:text-ink">
                                                    {b.label}
                                                </Link>
                                            ) : (
                                                <span className="font-semibold text-ink">{b.label}</span>
                                            )}
                                        </React.Fragment>
                                    ))}
                                </nav>
                            )}
                            <h1 className="truncate text-lg font-bold tracking-tight text-ink">
                                {title || 'Dashboard Kinerja'}
                            </h1>
                            </div>
                        </div>

                        {/* Top Right Badges */}
                        <div className="flex shrink-0 items-center gap-3">
                            <div className="hidden sm:flex items-center gap-2 px-3 py-1 bg-soft rounded-lg text-xs text-muted border border-border">
                                <Calendar className="w-3.5 h-3.5 text-primary" />
                                <span className="font-medium">Tahun Anggaran 2026</span>
                            </div>

                            <div className="hidden items-center gap-2 rounded-lg border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary sm:flex">
                                <Building2 className="h-3.5 w-3.5 text-primary" />
                                <span className="max-w-48 truncate">{user?.unit_kerja?.nama || 'LLDIKTI XVI'}</span>
                            </div>
                        </div>
                    </header>

                    {/* Flash Message Alerts */}
                    {flash?.success && (
                        <div className="mx-4 mt-4 flex items-center gap-2.5 rounded-lg border border-success/25 bg-success/10 p-3 text-xs font-medium text-success sm:mx-6">
                            <CheckCircle className="h-4 w-4 shrink-0 text-success" />
                            <span>{flash.success}</span>
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mx-4 mt-4 flex items-center gap-2.5 rounded-lg border border-danger/25 bg-danger/10 p-3 text-xs font-medium text-danger sm:mx-6">
                            <AlertCircle className="h-4 w-4 shrink-0 text-danger" />
                            <span>{flash.error}</span>
                        </div>
                    )}

                    {/* Page Content */}
                    <main className="flex-1 p-4 sm:p-6">
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
};
