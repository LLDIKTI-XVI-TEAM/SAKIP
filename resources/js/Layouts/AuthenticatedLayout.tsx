import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Fragment, useEffect, useRef, useState, type ReactNode, type FormEvent } from 'react';
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
    FileText,
    Search,
    Bell,
    User,
    Home,
    Settings,
} from 'lucide-react';
import type { SharedPageProps } from '@/types/auth';

interface AuthenticatedLayoutProps {
    children: ReactNode;
    title?: string;
    breadcrumbs?: { label: string; href?: string }[];
    renderTitleHeading?: boolean;
    hasCustomHeading?: boolean;
}

export function AuthenticatedLayout({
    children,
    title,
    breadcrumbs = [],
    renderTitleHeading = true,
    hasCustomHeading = false,
}: AuthenticatedLayoutProps) {
    const shouldRenderH1 = renderTitleHeading && !hasCustomHeading;
    const { props: { auth, flash, pengaturan }, url } = usePage<SharedPageProps>();
    const appName = (pengaturan?.['aplikasi.nama'] as string) || 'SAKIP';
    const instansiNama = (pengaturan?.['instansi.nama_pendek'] as string)
        || (pengaturan?.['instansi.nama'] as string)
        || 'LLDIKTI WILAYAH XVI';
    const rawLogo = pengaturan ? (pengaturan['instansi.logo'] as string | null | undefined) : undefined;
    const hasLogoKey = Boolean(pengaturan && Object.prototype.hasOwnProperty.call(pengaturan, 'instansi.logo'));
    const logoUrl = hasLogoKey
        ? (rawLogo && rawLogo.trim() !== '' ? rawLogo : null)
        : '/img/dikti16-favicon-blue-150x150.png';
    const [navigationOpen, setNavigationOpen] = useState(false);
    const [isDesktopViewport, setIsDesktopViewport] = useState(() => typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(min-width: 768px)').matches);
    const menuButtonRef = useRef<HTMLButtonElement>(null);
    const drawerRef = useRef<HTMLElement>(null);
    const recovery = useAuthRecovery();
    const [logoutError, setLogoutError] = useState('');
    const logout = useForm({});

    const normalizedBreadcrumbs = breadcrumbs.length > 0
        ? (breadcrumbs[0]?.label.toLowerCase() === 'dashboard'
            ? breadcrumbs
            : [
                auth?.can?.dashboard
                    ? { label: 'Dashboard', href: '/dashboard' }
                    : { label: 'Dashboard' },
                ...breadcrumbs,
            ])
        : [];

    useEffect(() => {
        const mediaQuery = window.matchMedia('(min-width: 768px)');
        const updateViewport = () => setIsDesktopViewport(mediaQuery.matches);

        updateViewport();
        mediaQuery.addEventListener('change', updateViewport);

        return () => mediaQuery.removeEventListener('change', updateViewport);
    }, []);

    const sidebarHidden = !isDesktopViewport && !navigationOpen;
    const isMobileDrawerOpen = navigationOpen && !isDesktopViewport;

    useEffect(() => {
        if (!isMobileDrawerOpen) return;

        const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const focusFirstDrawerControl = () => {
            drawerRef.current?.querySelector<HTMLElement>(focusableSelector)?.focus();
        };
        const focusFrame = window.requestAnimationFrame(focusFirstDrawerControl);
        const trapFocus = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                setNavigationOpen(false);
                return;
            }

            if (event.key !== 'Tab') return;

            const controls = Array.from(drawerRef.current?.querySelectorAll<HTMLElement>(focusableSelector) ?? []);
            const firstControl = controls.at(0);
            const lastControl = controls.at(-1);

            if (!firstControl || !lastControl) {
                event.preventDefault();
                drawerRef.current?.focus();
                return;
            }

            if (event.shiftKey && document.activeElement === firstControl) {
                event.preventDefault();
                lastControl.focus();
            } else if (!event.shiftKey && document.activeElement === lastControl) {
                event.preventDefault();
                firstControl.focus();
            }
        };

        document.addEventListener('keydown', trapFocus);

        return () => {
            window.cancelAnimationFrame(focusFrame);
            document.removeEventListener('keydown', trapFocus);
            if (!window.matchMedia('(min-width: 768px)').matches && menuButtonRef.current?.isConnected) {
                menuButtonRef.current.focus();
            } else if (previousFocus?.isConnected) {
                previousFocus.focus();
            }
        };
    }, [isMobileDrawerOpen]);

    const navigation = [
        { href: '/dashboard', label: 'Dashboard', icon: Home, visible: auth.can.dashboard },
        { href: '/pengukuran', label: 'Pengukuran Kinerja', icon: FileSpreadsheet, visible: auth.can.pengukuran },
        { href: '/verifikasi', label: 'Verifikasi & Pengesahan', icon: CheckCircle2, visible: auth.can.verifikasi },
        { href: '/regulasi', label: 'Dasar Aturan', icon: BookOpen, visible: auth.can.regulasi },
        { href: '/jenis-berkas', label: 'Persyaratan Berkas', icon: FileText, visible: auth.can.jenisBerkas ?? false },
        { href: '/akses/aktivasi', label: 'Aktivasi Pengguna', icon: UserCheck, visible: auth.can.aktivasi },
        { href: '/akses/peran', label: 'Penetapan Peran', icon: UserPlus, visible: auth.can.assignRole },
        { href: '/akses/deny', label: 'Pembatasan Izin', icon: UserCheck, visible: auth.can.manageDeny },
        { href: '/akses/izin-peran', label: 'Izin Peran', icon: UserCheck, visible: auth.can.manageRolePermissions },
        { href: '/pengaturan', label: 'Pengaturan', icon: Settings, visible: auth.can.pengaturan },
    ];

    const handleLogout = (event: FormEvent) => {
        event.preventDefault();
        if (logout.processing || recovery.recovery || logoutError) return;
        logout.post('/logout', {
            onStart: () => router.clearHistory(),
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path: '/logout', mutation: true })) setLogoutError('Keluar belum terkonfirmasi. Periksa sesi dengan memuat ulang halaman.');
                return false;
            },
            onCancel: () => { setLogoutError('Keluar belum terkonfirmasi. Periksa sesi dengan memuat ulang halaman.'); },
            onNetworkError: () => { setLogoutError('Keluar belum terkonfirmasi. Periksa sesi dengan memuat ulang halaman.'); return false; },
        });
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
                    {logoUrl && (
                        <img
                            src={logoUrl}
                            width={36}
                            height={36}
                            alt=""
                            className="h-9 w-9 shrink-0 object-contain rounded-md"
                        />
                    )}
                    <div>
                        <span className="text-base font-bold tracking-tight text-white leading-none">{appName}</span>
                    </div>
                </div>
                <button
                    ref={menuButtonRef}
                    type="button"
                    aria-expanded={navigationOpen}
                    aria-controls="application-navigation"
                    aria-label={navigationOpen ? 'Tutup navigasi' : 'Buka navigasi'}
                    onClick={() => setNavigationOpen((isOpen) => !isOpen)}
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
                ref={drawerRef}
                role={isMobileDrawerOpen ? 'dialog' : undefined}
                aria-modal={isMobileDrawerOpen || undefined}
                aria-label={isMobileDrawerOpen ? 'Navigasi utama' : undefined}
                aria-hidden={sidebarHidden || undefined}
                inert={sidebarHidden || undefined}
                tabIndex={-1}
                className={`fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col bg-primary text-white transition-transform duration-200 ease-in-out md:sticky md:top-0 md:h-screen md:shrink-0 md:translate-x-0 ${
                    navigationOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full md:translate-x-0 md:shadow-none'
                }`}
            >
                {/* Brand Header */}
                <div className="flex h-[68px] items-center justify-between border-b border-white/10 px-5 shrink-0">
                    <div className="flex items-center gap-3">
                        {logoUrl && (
                            <img
                                src={logoUrl}
                                width={38}
                                height={38}
                                alt=""
                                className="h-[38px] w-[38px] shrink-0 object-contain rounded-md"
                            />
                        )}
                        <div>
                            <span className="text-lg font-bold tracking-tight text-white leading-tight block">{appName}</span>
                            <p className="text-[10px] font-semibold text-white/75 tracking-wider leading-tight">
                                {instansiNama}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setNavigationOpen(false)}
                        className="inline-flex items-center justify-center rounded-lg p-2 text-white hover:bg-surface/10 focus:outline-none focus:ring-2 focus:ring-white/40 md:hidden"
                        aria-label="Tutup navigasi"
                    >
                        <X aria-hidden="true" className="h-5 w-5" />
                    </button>
                </div>

                {/* Scrollable Sidebar Body: Navigation & Profile */}
                <div className="flex-1 min-h-0 overflow-y-auto flex flex-col justify-between sidebar-scroll">
                    <div className="px-3 py-3">
                        <nav aria-label="Navigasi utama" className="space-y-1.5">
                            {navigation.filter((item) => item.visible).map(({ href, label, icon: Icon }) => {
                                const active = url.split('?')[0].startsWith(href);
                                return (
                                    <Link
                                        key={href}
                                        href={href}
                                        title={label}
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
                    <div className="mt-auto border-t border-white/10 bg-primary p-4 shrink-0">
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface/15 text-white">
                                <User className="h-5 w-5" aria-hidden="true" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-xs font-bold text-white leading-tight">
                                    {auth.user?.nama || 'Pengguna'}
                                </p>
                                <p className="mt-0.5 truncate text-[11px] text-white/75 capitalize leading-tight">
                                    {auth.user?.role || 'Belum ada peran'}
                                </p>
                            </div>
                        </div>

                        {recovery.recovery && (
                            <div className="mt-3 flow-root rounded-lg bg-surface px-3 text-ink">
                                <AuthRecoveryNotice recovery={recovery.recovery} pending={logout.processing} logout />
                            </div>
                        )}
                        {logoutError && <p role="alert" className="mt-3 rounded-lg border border-danger/30 bg-surface p-3 text-xs font-medium leading-relaxed text-danger">{logoutError}</p>}

                        <form onSubmit={handleLogout} className="mt-3">
                            <button
                                type="submit"
                                disabled={logout.processing || Boolean(recovery.recovery || logoutError)}
                                className="flex w-full items-center justify-center gap-2 rounded-lg border border-white/20 px-3 py-2 text-xs font-medium text-white hover:bg-surface/10 focus:outline-none focus:ring-2 focus:ring-white/40 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <LogOut aria-hidden="true" className="h-3.5 w-3.5" />
                                <span>{logout.processing ? 'Keluar…' : 'Keluar sistem'}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            {/* Main Column */}
            <div
                aria-hidden={isMobileDrawerOpen || undefined}
                inert={isMobileDrawerOpen || undefined}
                className="min-w-0 flex-1 flex flex-col"
            >
                {/* Top White Header Bar */}
                <header className="hidden md:flex h-[68px] items-center justify-between border-b border-border bg-surface px-4 xl:px-8 sticky top-0 z-30 shadow-2xs">
                    {/* Left: Search Input Pill */}
                    <div className="relative hidden xl:block">
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
                    <div className="ml-auto flex items-center gap-2 xl:gap-4">

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
                            <div className="hidden xl:block text-left">
                                <p className="text-xs font-bold text-primary leading-none truncate max-w-[140px]">
                                    {auth.user?.nama || 'Pengguna'}
                                </p>
                                <p className="mt-1 text-[11px] text-muted leading-none capitalize">
                                    {auth.user?.role || 'Belum ada peran'}
                                </p>
                            </div>
                        </div>
                    </div>
                </header>

                {(title || normalizedBreadcrumbs.length > 0) && (
                    <header className="px-4 pt-4 pb-0 sm:px-6 sm:pt-5 lg:px-8 max-w-7xl w-full mx-auto">
                        {title && (
                            shouldRenderH1 ? (
                                <h1 className="text-lg font-bold text-ink">{title}</h1>
                            ) : (
                                <p className="text-lg font-bold text-ink">{title}</p>
                            )
                        )}
                        {normalizedBreadcrumbs.length > 0 && (
                            <nav aria-label="Jejak navigasi" className={`${title ? 'mt-1 ' : ''}flex flex-wrap items-center gap-1.5 text-xs text-muted`}>
                                {normalizedBreadcrumbs.map((item, index) => (
                                    <Fragment key={`${index}-${item.label}`}>
                                        {index > 0 && <ChevronRight aria-hidden="true" className="h-3 w-3" />}
                                        {item.href ? (
                                            <Link href={item.href} className="rounded hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary">
                                                {item.label}
                                            </Link>
                                        ) : (
                                            <span aria-current="page" className="font-medium text-ink">
                                                {item.label}
                                            </span>
                                        )}
                                    </Fragment>
                                ))}
                            </nav>
                        )}
                    </header>
                )}

                {/* Alerts */}
                {flash?.success && (
                    <div className="px-4 mt-3 sm:px-6 lg:px-8 max-w-7xl w-full mx-auto">
                        <div role="status" className="flex items-start gap-2.5 rounded-xl border border-success/30 bg-success/10 p-3.5 text-sm text-ink shadow-2xs">
                            <CheckCircle aria-hidden="true" className="h-4 w-4 shrink-0 text-success mt-0.5" />
                            <span>{flash.success}</span>
                        </div>
                    </div>
                )}
                {flash?.warning && (
                    <div className="px-4 mt-3 sm:px-6 lg:px-8 max-w-7xl w-full mx-auto">
                        <div role="alert" className="flex items-start gap-2.5 rounded-xl border border-warning/30 bg-warning/10 p-3.5 text-sm text-warning-dark shadow-2xs">
                            <AlertCircle aria-hidden="true" className="h-4 w-4 shrink-0 text-warning-dark mt-0.5" />
                            <span>{flash.warning}</span>
                        </div>
                    </div>
                )}
                {flash?.error && (
                    <div className="px-4 mt-3 sm:px-6 lg:px-8 max-w-7xl w-full mx-auto">
                        <div role="alert" className="flex items-start gap-2.5 rounded-xl border border-danger/30 bg-danger/10 p-3.5 text-sm text-danger shadow-2xs">
                            <AlertCircle aria-hidden="true" className="h-4 w-4 shrink-0 text-danger mt-0.5" />
                            <span>{flash.error}</span>
                        </div>
                    </div>
                )}

                {/* Main Viewport */}
                <main
                    id="main-content"
                    tabIndex={-1}
                    className={`flex-1 px-4 sm:px-6 lg:px-8 pb-8 ${title || normalizedBreadcrumbs.length > 0 ? 'pt-3 sm:pt-4' : 'pt-5 sm:pt-6 lg:pt-8'} max-w-7xl w-full mx-auto outline-none`}
                >
                    {children}
                </main>
            </div>
        </div>
    );
}
