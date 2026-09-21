import { Fragment, useState, type ReactNode, type FormEvent } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    FileSpreadsheet,
    CheckCircle2,
    LogOut,
    ChevronRight,
    CheckCircle,
    AlertCircle,
    UserCheck,
    UserPlus,
    Menu,
    X,
    BookOpen,
    Search,
    Bell,
    User,
    Home,
} from 'lucide-react';
import type { SharedPageProps } from '@/types/auth';

interface AuthenticatedLayoutProps {
    children: ReactNode;
    title?: string;
    breadcrumbs?: { label: string; href?: string }[];
}

export function AuthenticatedLayout({ children, title, breadcrumbs = [] }: AuthenticatedLayoutProps) {
    const { props: { auth, flash }, url } = usePage<SharedPageProps>();
    const [navigationOpen, setNavigationOpen] = useState(false);
    const logout = useForm({});

    const navigation = [
        { href: '/dashboard', label: 'Dashboard', icon: Home, visible: auth.can.dashboard },
        { href: '/pengukuran', label: 'Pengukuran Kinerja', icon: FileSpreadsheet, visible: auth.can.pengukuran },
        { href: '/verifikasi', label: 'Verifikasi & Pengesahan', icon: CheckCircle2, visible: auth.can.verifikasi },
        { href: '/regulasi', label: 'Dasar Aturan', icon: BookOpen, visible: auth.can.regulasi },
        { href: '/akses/aktivasi', label: 'Aktivasi Pengguna', icon: UserCheck, visible: auth.can.aktivasi },
        { href: '/akses/peran', label: 'Penetapan Peran', icon: UserPlus, visible: auth.can.assignRole },
    ];

    const handleLogout = (event: FormEvent) => {
        event.preventDefault();
        if (!logout.processing) logout.post('/logout', { onStart: () => router.clearHistory() });
    };

    return (
        <div className="min-h-screen bg-page font-sans text-ink flex flex-col md:flex-row">
            {/* Skip to Content for Accessibility */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded-md focus:bg-surface focus:p-3 focus:text-primary focus:shadow-md focus:ring-2 focus:ring-primary"
            >
                Langsung ke isi halaman
            </a>

            {/* Mobile Header Bar */}
            <header className="flex h-16 items-center justify-between border-b border-primary bg-primary px-4 md:hidden sticky top-0 z-40 text-white shadow-sm">
                <div className="flex min-w-0 items-center gap-2.5">
                    <img
                        src="/img/dikti16-favicon-blue-150x150.png"
                        width={36}
                        height={36}
                        alt="Logo LLDIKTI XVI"
                        className="h-9 w-9 shrink-0 object-contain rounded-md"
                    />
                    <div>
                        <span className="text-base font-bold tracking-tight text-white leading-none">SAKIP</span>
                    </div>
                </div>
                <button
                    type="button"
                    aria-expanded={navigationOpen}
                    aria-controls="application-navigation"
                    aria-label={navigationOpen ? 'Tutup navigasi' : 'Buka navigasi'}
                    onClick={() => setNavigationOpen(!navigationOpen)}
                    className="inline-flex items-center justify-center rounded-lg p-2 text-white hover:bg-surface/10 focus:outline-none focus:ring-2 focus:ring-white/40"
                >
                    {navigationOpen ? <X aria-hidden="true" className="h-5 w-5" /> : <Menu aria-hidden="true" className="h-5 w-5" />}
                </button>
            </header>

            {/* Mobile Backdrop */}
            {navigationOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/60 backdrop-blur-xs transition-opacity md:hidden"
                    onClick={() => setNavigationOpen(false)}
                    aria-hidden="true"
                />
            )}

            {/* Deep Navy Sidebar */}
            <aside
                id="application-navigation"
                className={`fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col bg-primary text-white transition-transform duration-200 ease-in-out md:sticky md:top-0 md:h-screen md:shrink-0 md:translate-x-0 ${
                    navigationOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full md:translate-x-0 md:shadow-none'
                }`}
            >
                {/* Brand Header */}
                <div className="flex h-[68px] items-center border-b border-white/10 px-5 shrink-0">
                    <div className="flex items-center gap-3">
                        <img
                            src="/img/dikti16-favicon-blue-150x150.png"
                            width={38}
                            height={38}
                            alt="Logo LLDIKTI XVI"
                            className="h-[38px] w-[38px] shrink-0 object-contain rounded-md"
                        />
                        <div>
                            <span className="text-lg font-bold tracking-tight text-white leading-tight block">SAKIP</span>
                            <p className="text-[10px] font-semibold text-white/75 tracking-wider leading-tight">
                                LLDIKTI WILAYAH XVI
                            </p>
                        </div>
                    </div>
                </div>

                {/* Navigation Menu */}
                <div className="flex-1 overflow-y-auto px-3 py-3">
                    <nav aria-label="Navigasi utama" className="space-y-1.5">
                        {navigation.filter((item) => item.visible).map(({ href, label, icon: Icon }) => {
                            const active = url.split('?')[0].startsWith(href);
                            return (
                                <Link
                                    key={href}
                                    href={href}
                                    aria-current={active ? 'page' : undefined}
                                    onClick={() => setNavigationOpen(false)}
                                    className={`group flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium transition-all ${
                                        active
                                            ? 'bg-surface/15 text-white font-semibold shadow-xs'
                                            : 'text-white/75 hover:bg-surface/10 hover:text-white'
                                    }`}
                                >
                                    <Icon
                                        aria-hidden="true"
                                        className={`h-[18px] w-[18px] shrink-0 transition-colors ${active ? 'text-white' : 'text-white/75 group-hover:text-white'}`}
                                    />
                                    <span className="truncate">{label}</span>
                                </Link>
                            );
                        })}
                    </nav>
                </div>

                {/* Bottom User Profile Section */}
                <div className="border-t border-white/10 bg-primary p-4 shrink-0">
                    <div className="flex items-center gap-3">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface/15 text-white">
                            <User className="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-bold text-white leading-tight">
                                {auth.user?.nama || 'Superadmin LLDIKTI16'}
                            </p>
                            <p className="mt-0.5 truncate text-[11px] text-white/75 capitalize leading-tight">
                                {auth.user?.role || 'Superadmin'}
                            </p>
                        </div>
                    </div>

                    <form onSubmit={handleLogout} className="mt-3">
                        <button
                            type="submit"
                            disabled={logout.processing}
                            className="flex w-full items-center justify-center gap-2 rounded-lg border border-white/20 px-3 py-2 text-xs font-medium text-white hover:bg-surface/10 focus:outline-none focus:ring-2 focus:ring-white/40 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <LogOut aria-hidden="true" className="h-3.5 w-3.5" />
                            <span>{logout.processing ? 'Keluar…' : 'Keluar sistem'}</span>
                        </button>
                    </form>
                </div>
            </aside>

            {/* Main Column */}
            <div className="min-w-0 flex-1 flex flex-col">
                {/* Top White Header Bar */}
                <header className="hidden md:flex h-[68px] items-center justify-between border-b border-border bg-surface px-6 lg:px-8 sticky top-0 z-30 shadow-2xs">
                    {/* Left: Search Input Pill */}
                    <div className="relative">
                        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Cari menu, indikator, atau data..."
                            aria-label="Pencarian"
                            aria-describedby="search-coming-soon"
                            className="h-9 w-72 lg:w-80 rounded-xl border border-border bg-soft pl-9 pr-3 text-xs text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/20 shadow-2xs cursor-default"
                            readOnly
                        />
                        <span id="search-coming-soon" className="sr-only">Pencarian akan segera tersedia.</span>
                    </div>

                    {/* Right Functional Chips */}
                    <div className="flex items-center gap-4">

                        <button
                            type="button"
                            disabled
                            title="Notifikasi akan segera tersedia"
                            aria-label="Notifikasi akan segera tersedia"
                            className="inline-flex items-center justify-center rounded-lg p-2 text-muted disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Bell className="h-5 w-5" aria-hidden="true" />
                        </button>

                        {/* User Profile Chip */}
                        <div className="flex items-center gap-2.5 pl-2 border-l border-border">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                                <User className="h-4 w-4" />
                            </div>
                            <div className="hidden sm:block text-left">
                                <p className="text-xs font-bold text-primary leading-none truncate max-w-[140px]">
                                    {auth.user?.nama || 'Superadmin LLDIKTI16'}
                                </p>
                                <p className="mt-1 text-[11px] text-muted leading-none capitalize">
                                    {auth.user?.role || 'Superadmin'}
                                </p>
                            </div>
                        </div>
                    </div>
                </header>

                {(title || breadcrumbs.length > 0) && (
                    <header className="border-b border-border bg-surface px-4 py-4 sm:px-6 lg:px-8">
                        {breadcrumbs.length > 0 && (
                            <nav aria-label="Jejak navigasi" className="mb-1 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                                {auth.can.dashboard ? <Link href="/dashboard" className="rounded hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary">SAKIP</Link> : <span>SAKIP</span>}
                                {breadcrumbs.map((item, index) => (
                                    <Fragment key={`${index}-${item.label}`}>
                                        <ChevronRight aria-hidden="true" className="h-3 w-3" />
                                        {item.href ? <Link href={item.href} className="rounded hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary">{item.label}</Link> : <span aria-current="page" className="font-medium text-ink">{item.label}</span>}
                                    </Fragment>
                                ))}
                            </nav>
                        )}
                        {title && <h1 className="text-lg font-bold text-ink">{title}</h1>}
                    </header>
                )}

                {/* Alerts */}
                {flash?.success && (
                    <div className="mx-4 mt-4 lg:mx-8 max-w-7xl">
                        <div role="status" className="flex items-start gap-2.5 rounded-xl border border-success/30 bg-success/10 p-3.5 text-sm text-ink shadow-2xs">
                            <CheckCircle aria-hidden="true" className="h-4 w-4 shrink-0 text-success mt-0.5" />
                            <span>{flash.success}</span>
                        </div>
                    </div>
                )}
                {flash?.error && (
                    <div className="mx-4 mt-4 lg:mx-8 max-w-7xl">
                        <div role="alert" className="flex items-start gap-2.5 rounded-xl border border-danger/30 bg-danger/10 p-3.5 text-sm text-danger shadow-2xs">
                            <AlertCircle aria-hidden="true" className="h-4 w-4 shrink-0 text-danger mt-0.5" />
                            <span>{flash.error}</span>
                        </div>
                    </div>
                )}

                {/* Main Viewport */}
                <main id="main-content" tabIndex={-1} className="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto outline-none">
                    {children}
                </main>
            </div>
        </div>
    );
}
