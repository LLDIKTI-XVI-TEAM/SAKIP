import { LogoutActions } from '@/Components/Auth/LogoutActions';
import { NotificationDropdown } from '@/Components/NotificationDropdown';
import { HoverScrollText } from '@/Components/HoverScrollText';
import { Fragment, useEffect, useRef, useState, type ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    FileSpreadsheet,
    CheckCircle2,
    ChevronRight,
    CheckCircle,
    AlertCircle,
    Info,
    UserCheck,
    UserCog,
    Menu,
    X,
    Building2,
    ShieldCheck,
    ShieldAlert,
    BookOpen,
    FileSignature,
    ClipboardList,
    KeyRound,
    Search,
    User,
    Settings,
    HardDrive,
    Layers,
    ChevronDown,
} from 'lucide-react';
import type { SharedPageProps } from '@/types/auth';

interface AuthenticatedLayoutProps {
    children: ReactNode;
    title?: string;
    breadcrumbs?: { label: string; href?: string }[];
    renderTitleHeading?: boolean;
    hasCustomHeading?: boolean;
    headerAction?: ReactNode;
}

export function AuthenticatedLayout({
    children,
    title,
    breadcrumbs = [],
    renderTitleHeading = true,
    hasCustomHeading = false,
    headerAction,
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

    const [logoFailed, setLogoFailed] = useState(false);
    const [isBrandHovered, setIsBrandHovered] = useState(false);
    const [isProfileMenuOpen, setIsProfileMenuOpen] = useState(false);
    const [isMobileProfileOpen, setIsMobileProfileOpen] = useState(false);
    const profileMenuRef = useRef<HTMLDivElement>(null);
    const profileButtonRef = useRef<HTMLButtonElement>(null);
    const mobileProfileMenuRef = useRef<HTMLDivElement>(null);
    const mobileProfileButtonRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        setLogoFailed(false);
    }, [logoUrl]);

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

    // Close profile dropdown on click outside or Escape
    useEffect(() => {
        if (!isProfileMenuOpen && !isMobileProfileOpen) return;

        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Node;
            if (
                isProfileMenuOpen &&
                profileMenuRef.current &&
                !profileMenuRef.current.contains(target) &&
                profileButtonRef.current &&
                !profileButtonRef.current.contains(target)
            ) {
                setIsProfileMenuOpen(false);
            }

            if (
                isMobileProfileOpen &&
                mobileProfileMenuRef.current &&
                !mobileProfileMenuRef.current.contains(target) &&
                mobileProfileButtonRef.current &&
                !mobileProfileButtonRef.current.contains(target)
            ) {
                setIsMobileProfileOpen(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                if (isProfileMenuOpen) {
                    setIsProfileMenuOpen(false);
                    profileButtonRef.current?.focus();
                }
                if (isMobileProfileOpen) {
                    setIsMobileProfileOpen(false);
                    mobileProfileButtonRef.current?.focus();
                }
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isProfileMenuOpen, isMobileProfileOpen]);

    const sidebarHidden = !isDesktopViewport && !navigationOpen;
    const isMobileDrawerOpen = navigationOpen && !isDesktopViewport;

    useEffect(() => {
        if (!isMobileDrawerOpen) return;

        const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const menuButton = menuButtonRef.current;
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
            if (!window.matchMedia('(min-width: 768px)').matches && menuButton?.isConnected) {
                menuButton.focus();
            } else if (previousFocus?.isConnected) {
                previousFocus.focus();
            }
        };
    }, [isMobileDrawerOpen]);

    const navigationGroups = [
        {
            title: 'Utama',
            items: [
                { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard, visible: auth.can.dashboard },
            ],
        },
        {
            title: 'Perencanaan & Kinerja',
            items: [
                { href: '/perjanjian-kinerja', label: 'Perjanjian Kinerja', icon: FileSignature, visible: auth?.can?.pk ?? false },
                { href: '/pengukuran', label: 'Pengukuran Kinerja', icon: FileSpreadsheet, visible: auth.can.pengukuran },
                { href: '/verifikasi', label: 'Verifikasi & Pengesahan', icon: CheckCircle2, visible: auth.can.verifikasi },
                { href: '/regulasi', label: 'Dasar Aturan', icon: BookOpen, visible: auth.can.regulasi },
                { href: '/renstra', label: 'Master Renstra', icon: Layers, visible: auth.can.renstra ?? false },
            ],
        },
        {
            title: 'Master Data',
            items: [
                { href: '/unit', label: 'Master Unit', icon: Building2, visible: auth.can.unit ?? false },
                { href: '/jenis-berkas', label: 'Persyaratan Berkas', icon: ClipboardList, visible: auth.can.jenisBerkas ?? false },
            ],
        },
        {
            title: 'Manajemen Akses',
            items: [
                { href: '/akses/aktivasi', label: 'Aktivasi Pengguna', icon: UserCheck, visible: auth.can.aktivasi },
                { href: '/akses/peran', label: 'Penetapan Peran', icon: UserCog, visible: auth.can.assignRole },
                { href: '/akses/deny', label: 'Pembatasan Izin', icon: ShieldAlert, visible: auth.can.manageDeny },
                { href: '/akses/izin-peran', label: 'Peran & Izin', icon: KeyRound, visible: auth.can.viewRolePermissions },
                { href: '/akses/grant', label: 'Izin Unit (Grant)', icon: ShieldCheck, visible: auth.can.grant ?? false },
            ],
        },
        {
            title: 'Sistem',
            items: [
                { href: '/pengaturan/storage', label: 'Kebijakan Storage', icon: HardDrive, visible: auth.can.storagePolicy ?? false },
                { href: '/pengaturan', label: 'Pengaturan', icon: Settings, visible: auth.can.pengaturan },
            ],
        },
    ];

    const currentPath = url.split('?')[0];
    const isItemActive = (href: string) => {
        if (href === '/pengaturan') {
            return currentPath === '/pengaturan';
        }
        return currentPath === href || currentPath.startsWith(`${href}/`);
    };

    return (
        <div className="min-h-screen bg-page font-sans text-ink flex flex-col md:flex-row w-full max-w-full overflow-x-clip md:overflow-x-visible">
            {/* Skip to Content for Accessibility */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded-md focus:bg-surface focus:p-3 focus:text-primary focus:shadow-md focus:ring-2 focus:ring-primary"
            >
                Langsung ke isi halaman
            </a>

            {/* Mobile Header Bar */}
            <header className="flex h-16 w-full items-center justify-between border-b border-primary bg-primary px-4 md:hidden sticky top-0 z-40 text-white shadow-sm">
                <div className="flex min-w-0 flex-1 items-center gap-2.5 mr-2">
                    {!logoFailed && logoUrl && (
                        <img
                            src={logoUrl}
                            width={36}
                            height={36}
                            alt=""
                            className="h-9 w-9 shrink-0 object-contain rounded-md"
                            onError={() => setLogoFailed(true)}
                        />
                    )}
                    <div className="min-w-0 flex-1">
                        <span className="text-base font-bold tracking-tight text-white leading-none block truncate">{appName}</span>
                    </div>
                </div>
                <div className="flex items-center gap-1">
                    {/* Mobile Profile Trigger */}
                    <div className="relative">
                        <button
                            ref={mobileProfileButtonRef}
                            type="button"
                            aria-expanded={isMobileProfileOpen}
                            aria-label="Menu akun"
                            onClick={() => setIsMobileProfileOpen((isOpen) => !isOpen)}
                            className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg p-2 text-white hover:bg-surface/10 active:bg-surface/20 focus:outline-none focus:ring-2 focus:ring-white/40 touch-manipulation"
                        >
                            <User className="h-5 w-5" aria-hidden="true" />
                        </button>

                        {isMobileProfileOpen && (
                            <div
                                ref={mobileProfileMenuRef}
                                role="menu"
                                aria-label="Menu akun mobile"
                                className="absolute right-0 top-full mt-2 w-64 origin-top-right overflow-hidden rounded-xl border border-border bg-surface shadow-xl z-50 text-ink"
                            >
                                <div className="border-b border-border px-4 py-3 bg-soft/40">
                                    <p className="text-xs font-bold text-ink truncate font-sans">
                                        {auth.user?.nama || 'Pengguna'}
                                    </p>
                                    <p className="mt-0.5 text-[11px] text-muted truncate font-sans">
                                        {auth.user?.email || auth.user?.role || ''}
                                    </p>
                                    <div className="mt-1.5">
                                        <span className="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary capitalize">
                                            {auth.user?.role || 'Belum ada peran'}
                                        </span>
                                    </div>
                                </div>
                                <LogoutActions variant="dropdown" onAction={() => setIsMobileProfileOpen(false)} />
                            </div>
                        )}
                    </div>

                    <button
                        ref={menuButtonRef}
                        type="button"
                        aria-expanded={navigationOpen}
                        aria-controls="application-navigation"
                        aria-label={navigationOpen ? 'Tutup navigasi' : 'Buka navigasi'}
                        onClick={() => setNavigationOpen((isOpen) => !isOpen)}
                        className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg p-2 text-white hover:bg-surface/10 active:bg-surface/20 focus:outline-none focus:ring-2 focus:ring-white/40 touch-manipulation"
                    >
                        {navigationOpen ? <X aria-hidden="true" className="h-5 w-5" /> : <Menu aria-hidden="true" className="h-5 w-5" />}
                    </button>
                </div>
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
                    <div
                        className="flex items-center gap-3 min-w-0 flex-1 mr-2"
                        onMouseEnter={() => setIsBrandHovered(true)}
                        onMouseLeave={() => setIsBrandHovered(false)}
                    >
                        {!logoFailed && logoUrl && (
                            <img
                                src={logoUrl}
                                width={38}
                                height={38}
                                alt=""
                                className="h-[38px] w-[38px] shrink-0 object-contain rounded-md"
                                onError={() => setLogoFailed(true)}
                            />
                        )}
                        <div className="min-w-0 flex-1">
                            <span className="text-lg font-bold tracking-tight text-white leading-tight block truncate">{appName}</span>
                            <HoverScrollText
                                text={instansiNama}
                                isParentHovered={isBrandHovered}
                                fadeFromColor="from-primary"
                                className="w-full text-[10px] font-semibold text-white/75 tracking-wider leading-tight"
                                textClassName="text-[10px] font-semibold text-white/75 tracking-wider leading-tight"
                                scrollSpeed={35}
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => setNavigationOpen(false)}
                        className="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg p-2 text-white hover:bg-surface/10 active:bg-surface/20 focus:outline-none focus:ring-2 focus:ring-white/40 md:hidden touch-manipulation"
                        aria-label="Tutup navigasi"
                    >
                        <X aria-hidden="true" className="h-5 w-5" />
                    </button>
                </div>

                {/* Scrollable Sidebar Body: Navigation Only */}
                <div className="flex-1 min-h-0 overflow-y-auto px-3 py-3 sidebar-scroll">
                    <nav aria-label="Navigasi utama" className="space-y-4">
                        {navigationGroups.map((group) => {
                            const visibleItems = group.items.filter((item) => item.visible);
                            if (visibleItems.length === 0) return null;

                            return (
                                <div key={group.title} className="space-y-1">
                                    <div className="px-3 pt-2 pb-1">
                                        <p className="text-[10px] font-bold uppercase tracking-wider text-white/50">
                                            {group.title}
                                        </p>
                                    </div>
                                    <div className="space-y-0.5">
                                        {visibleItems.map(({ href, label, icon: Icon }) => {
                                            const active = isItemActive(href);
                                            return (
                                                <Link
                                                    key={href}
                                                    href={href}
                                                    aria-current={active ? 'page' : undefined}
                                                    onClick={() => setNavigationOpen(false)}
                                                    className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 md:py-2 text-xs font-medium transition-colors min-h-[44px] md:min-h-0 touch-manipulation ${
                                                        active
                                                            ? 'bg-surface/20 text-white font-semibold shadow-xs'
                                                            : 'text-white/75 hover:bg-surface/10 hover:text-white'
                                                    }`}
                                                >
                                                    <Icon
                                                        aria-hidden="true"
                                                        className={`h-[18px] w-[18px] shrink-0 transition-colors ${
                                                            active ? 'text-white' : 'text-white/75 group-hover:text-white'
                                                        }`}
                                                    />
                                                    <span className="truncate">{label}</span>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        })}
                    </nav>
                </div>

                {/* Fixed Bottom User Profile Section (Gambar 3 - pinned, never scrolls with nav) */}
                <div className="border-t border-white/10 bg-primary p-4 shrink-0">
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
                </div>
            </aside>

            {/* Main Column */}
            <div
                aria-hidden={isMobileDrawerOpen || undefined}
                inert={isMobileDrawerOpen || undefined}
                className="min-w-0 flex-1 flex flex-col w-full max-w-full"
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

                        <NotificationDropdown />

                        {/* User Profile Dropdown Button (Gambar 2 - like SIMPEG) */}
                        <div className="relative pl-2 border-l border-border">
                            <button
                                ref={profileButtonRef}
                                id="profile-btn"
                                type="button"
                                aria-expanded={isProfileMenuOpen}
                                aria-controls="profile-menu"
                                aria-haspopup="menu"
                                onClick={() => setIsProfileMenuOpen((isOpen) => !isOpen)}
                                className="flex items-center gap-2.5 rounded-xl border border-border px-3 py-1.5 transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20 text-left cursor-pointer"
                            >
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <User className="h-4 w-4" aria-hidden="true" />
                                </div>
                                <div className="hidden xl:block text-left">
                                    <p className="text-xs font-bold text-ink leading-tight truncate max-w-[140px]">
                                        {auth.user?.nama || 'Pengguna'}
                                    </p>
                                    <p className="mt-0.5 text-[11px] text-muted leading-tight capitalize">
                                        {auth.user?.role || 'Belum ada peran'}
                                    </p>
                                </div>
                                <ChevronDown
                                    className={`h-3.5 w-3.5 text-muted transition-transform duration-150 ${
                                        isProfileMenuOpen ? 'rotate-180 text-primary' : ''
                                    }`}
                                    aria-hidden="true"
                                />
                            </button>

                            {/* Dropdown Menu */}
                            {isProfileMenuOpen && (
                                <div
                                    ref={profileMenuRef}
                                    id="profile-menu"
                                    role="menu"
                                    aria-label="Menu akun"
                                    className="absolute right-0 top-full mt-2 w-64 origin-top-right overflow-hidden rounded-xl border border-border bg-surface shadow-lg z-50 animate-in fade-in zoom-in-95 duration-100"
                                >
                                    <div className="border-b border-border px-4 py-3 bg-soft/40">
                                        <p className="text-xs font-bold text-ink truncate font-sans">
                                            {auth.user?.nama || 'Pengguna'}
                                        </p>
                                        <p className="mt-0.5 text-[11px] text-muted truncate font-sans">
                                            {auth.user?.email || auth.user?.role || ''}
                                        </p>
                                        <div className="mt-1.5">
                                            <span className="inline-flex items-center rounded-md bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary capitalize">
                                                {auth.user?.role || 'Belum ada peran'}
                                            </span>
                                        </div>
                                    </div>

                                    <LogoutActions variant="dropdown" onAction={() => setIsProfileMenuOpen(false)} />
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {(title || normalizedBreadcrumbs.length > 0 || headerAction) && (
                    <header className="px-4 pt-4 pb-0 sm:px-6 sm:pt-5 lg:px-8 max-w-7xl w-full mx-auto">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
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
                                                    <span aria-current={index === normalizedBreadcrumbs.length - 1 ? 'page' : undefined} className="font-medium text-ink">
                                                        {item.label}
                                                    </span>
                                                )}
                                            </Fragment>
                                        ))}
                                    </nav>
                                )}
                            </div>
                            {headerAction && (
                                <div className="shrink-0">
                                    {headerAction}
                                </div>
                            )}
                        </div>
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
                {flash?.message && (
                    <div className="mx-4 mt-4 lg:mx-8 max-w-7xl">
                        <div role="status" className="flex items-start gap-2.5 rounded-xl border border-primary/30 bg-primary/10 p-3.5 text-sm text-ink shadow-2xs">
                            <Info aria-hidden="true" className="h-4 w-4 shrink-0 text-primary mt-0.5" />
                            <span>{flash.message}</span>
                        </div>
                    </div>
                )}

                {/* Main Viewport */}
                <main
                    id="main-content"
                    tabIndex={-1}
                    className={`flex-1 px-4 sm:px-6 lg:px-8 pb-8 ${title || normalizedBreadcrumbs.length > 0 ? 'pt-3 sm:pt-4' : 'pt-5 sm:pt-6'} max-w-7xl w-full mx-auto outline-none`}
                >
                    {children}
                </main>
            </div>
        </div>
    );
}
