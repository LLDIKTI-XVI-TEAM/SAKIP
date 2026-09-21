import { Fragment, useState, type ReactNode, type FormEvent } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    FileSpreadsheet,
    CheckCircle2,
    LogOut,
    ChevronRight,
    CheckCircle,
    AlertCircle,
    UserCheck,
    Menu,
    X,
    Layers,
    TrendingUp,
    ListTodo,
    Building2,
    ShieldCheck,
    BookOpen,
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
        { href: '/dashboard', label: 'Dashboard Capaian', icon: LayoutDashboard, visible: auth?.can?.dashboard ?? true },
        { href: '/renstra', label: 'Renstra & Sasaran', icon: Layers, visible: true },
        { href: '/indikator', label: 'Indikator Kinerja (IKU)', icon: TrendingUp, visible: true },
        { href: '/rencana-aksi', label: 'Rencana Aksi (RA)', icon: ListTodo, visible: true },
        { href: '/pengukuran', label: 'Pengukuran Kinerja', icon: FileSpreadsheet, visible: auth?.can?.pengukuran ?? true },
        { href: '/verifikasi', label: 'Verifikasi & Pengesahan', icon: CheckCircle2, visible: auth?.can?.verifikasi ?? true },
        { href: '/regulasi', label: 'Dasar Aturan', icon: BookOpen, visible: auth?.can?.regulasi ?? false },
        { href: '/unit', label: 'Master Unit', icon: Building2, visible: true },
        { href: '/akses/grant', label: 'Izin Unit (Grant)', icon: ShieldCheck, visible: true },
        { href: '/akses/aktivasi', label: 'Aktivasi Pengguna', icon: UserCheck, visible: auth?.can?.aktivasi ?? false },
        { href: '/akses/peran', label: 'Penetapan Peran', icon: UserCheck, visible: auth?.can?.assignRole ?? false },
    ];
    const handleLogout = (event: FormEvent) => {
        event.preventDefault();
        if (!logout.processing) logout.post('/logout', { onStart: () => router.clearHistory() });
    };

    return (
        <div className="min-h-screen bg-page font-sans text-ink">
            <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded focus:bg-surface focus:p-3 focus:text-primary">Langsung ke isi halaman</a>
            <div className="flex items-center justify-between border-b border-border bg-surface px-4 py-3 md:hidden">
                <div className="flex min-w-0 items-center gap-3">
                    <img src="/img/dikti16-favicon-blue-150x150.png" width={150} height={150} alt="" className="h-10 w-10 shrink-0 object-contain" />
                    <span className="text-sm font-bold text-primary">SAKIP LLDIKTI XVI</span>
                </div>
                <button type="button" aria-expanded={navigationOpen} aria-controls="application-navigation" aria-label={navigationOpen ? 'Tutup navigasi' : 'Buka navigasi'} onClick={() => setNavigationOpen(!navigationOpen)} className="rounded-lg p-2 text-primary focus:outline-none focus:ring-2 focus:ring-primary">
                    {navigationOpen ? <X aria-hidden="true" className="h-5 w-5" /> : <Menu aria-hidden="true" className="h-5 w-5" />}
                </button>
            </div>
            <div className="md:flex md:min-h-screen">
                <aside id="application-navigation" className={`${navigationOpen ? 'flex' : 'hidden'} flex-col border-b border-border bg-surface md:flex md:w-64 md:shrink-0 md:border-b-0 md:border-r`}>
                    <div className="hidden items-center gap-3 border-b border-border p-5 md:flex">
                        <img src="/img/dikti16-favicon-blue-150x150.png" width={150} height={150} alt="" className="h-12 w-12 shrink-0 object-contain" />
                        <div>
                            <p className="text-lg font-bold text-primary">SAKIP <span className="rounded bg-secondary px-1.5 text-xs text-ink">XVI</span></p>
                            <p className="mt-1 text-xs text-muted">LLDIKTI Wilayah XVI</p>
                        </div>
                    </div>
                    <nav aria-label="Navigasi utama" className="flex-1 space-y-1 p-3">
                        {navigation.filter((item) => item.visible).map(({ href, label, icon: Icon }) => {
                            const active = url ? url.split('?')[0].startsWith(href) : false;
                            return (
                                <Link
                                    key={href}
                                    href={href}
                                    aria-current={active ? 'page' : undefined}
                                    onClick={() => setNavigationOpen(false)}
                                    className={`flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary ${active ? 'bg-primary text-white' : 'text-ink hover:bg-soft'}`}
                                >
                                    <Icon aria-hidden="true" className="h-4 w-4 shrink-0" />{label}
                                </Link>
                            );
                        })}
                    </nav>
                    <div className="border-t border-border p-4">
                        <p className="break-words text-sm font-semibold">{auth?.user?.nama ?? 'Pengguna'}</p>
                        <p className="mt-1 break-all text-xs text-muted">{auth?.user?.email ?? '-'}</p>
                        {auth?.user?.role && <p className="mt-1 text-xs font-medium capitalize text-primary">{auth.user.role}</p>}
                        <form onSubmit={handleLogout} className="mt-3">
                            <button type="submit" disabled={logout.processing} className="flex w-full items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm text-ink hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary disabled:cursor-not-allowed disabled:opacity-50">
                                <LogOut aria-hidden="true" className="h-4 w-4" />{logout.processing ? 'Keluar…' : 'Keluar sistem'}
                            </button>
                        </form>
                    </div>
                </aside>
                <div className="min-w-0 flex-1">
                    <header className="border-b border-border bg-surface px-4 py-4 sm:px-6">
                        {breadcrumbs.length > 0 && <nav aria-label="Jejak navigasi" className="mb-1 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                            {auth?.can?.dashboard ? <Link href="/dashboard" className="rounded hover:text-primary focus:ring-2 focus:ring-primary">SAKIP</Link> : <span>SAKIP</span>}
                            {breadcrumbs.map((item, index) => (
                                <Fragment key={`${index}-${item.label}`}>
                                    <ChevronRight aria-hidden="true" className="h-3 w-3" />
                                    {item.href ? <Link href={item.href} className="rounded hover:text-primary focus:ring-2 focus:ring-primary">{item.label}</Link> : <span aria-current="page" className="font-medium text-ink">{item.label}</span>}
                                </Fragment>
                            ))}
                        </nav>}
                        <p className="text-lg font-bold">{title || 'SAKIP'}</p>
                    </header>
                    {flash?.success && (
                        <div role="status" className="mx-4 mt-4 flex items-start gap-2 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-ink sm:mx-6">
                            <CheckCircle aria-hidden="true" className="h-4 w-4 shrink-0" /><span>{flash.success}</span>
                        </div>
                    )}
                    {flash?.error && (
                        <div role="alert" className="mx-4 mt-4 flex items-start gap-2 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger sm:mx-6">
                            <AlertCircle aria-hidden="true" className="h-4 w-4 shrink-0" /><span>{flash.error}</span>
                        </div>
                    )}
                    <main id="main-content" tabIndex={-1} className="p-4 sm:p-6">{children}</main>
                </div>
            </div>
        </div>
    );
}
