import React, { ReactNode } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { 
    LayoutDashboard, 
    FileSpreadsheet, 
    CheckCircle2, 
    LogOut, 
    Building2, 
    Calendar,
    ChevronRight,
    CheckCircle,
    AlertCircle
} from 'lucide-react';
import { RoleSwitcher } from '@/Components/RoleSwitcher';

interface AuthenticatedLayoutProps {
    children: ReactNode;
    title?: string;
    breadcrumbs?: { label: string; href?: string }[];
}

export const AuthenticatedLayout: React.FC<AuthenticatedLayoutProps> = ({
    children,
    title,
    breadcrumbs = [],
}) => {
    const { auth, flash } = usePage<any>().props;
    const user = auth?.user;
    const currentRole = user?.roles?.[0] || 'pegawai';

    const handleLogout = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/logout');
    };

    const isPerencanaanOrSuper = currentRole === 'perencanaan' || currentRole === 'superadmin';

    return (
        <div className="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-800">
            {/* Top Quick Role Switcher for local dev */}
            <RoleSwitcher />

            <div className="flex flex-1 overflow-hidden">
                {/* Modern Institutional Sidebar */}
                <aside className="w-64 bg-[#0a1b5c] text-white flex flex-col border-r border-[#122E92]/40 shrink-0 select-none">
                    {/* Brand / Logo */}
                    <div className="p-5 border-b border-white/10 flex items-center gap-3">
                        <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-[#122E92] to-[#D6AC48] flex items-center justify-center font-bold text-white shadow-md text-lg">
                            S
                        </div>
                        <div>
                            <div className="text-base font-bold tracking-tight text-white flex items-center gap-1.5">
                                SAKIP
                                <span className="text-[10px] bg-[#D6AC48] text-slate-900 font-bold px-1.5 py-0.2 rounded uppercase">
                                    XVI
                                </span>
                            </div>
                            <div className="text-[11px] text-slate-300 font-normal leading-tight">
                                LLDIKTI Wilayah XVI
                            </div>
                        </div>
                    </div>

                    {/* Navigation Menu */}
                    <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
                        <div className="px-3 py-2 text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                            Menu Utama
                        </div>

                        <Link
                            href="/dashboard"
                            className={`flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition-colors ${
                                window.location.pathname.startsWith('/dashboard')
                                    ? 'bg-white/15 text-white font-semibold shadow-xs'
                                    : 'text-slate-300 hover:bg-white/10 hover:text-white'
                            }`}
                        >
                            <LayoutDashboard className="w-4 h-4 text-[#D6AC48]" />
                            Dashboard Capaian
                        </Link>

                        <Link
                            href="/pengukuran"
                            className={`flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition-colors ${
                                window.location.pathname.startsWith('/pengukuran')
                                    ? 'bg-white/15 text-white font-semibold shadow-xs'
                                    : 'text-slate-300 hover:bg-white/10 hover:text-white'
                            }`}
                        >
                            <FileSpreadsheet className="w-4 h-4 text-[#D6AC48]" />
                            Pengukuran Kinerja
                        </Link>

                        {isPerencanaanOrSuper && (
                            <>
                                <div className="px-3 pt-4 pb-1 text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                                    Verifikasi & Reviu
                                </div>
                                <Link
                                    href="/verifikasi"
                                    className={`flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition-colors ${
                                        window.location.pathname.startsWith('/verifikasi')
                                            ? 'bg-white/15 text-white font-semibold shadow-xs'
                                            : 'text-slate-300 hover:bg-white/10 hover:text-white'
                                    }`}
                                >
                                    <CheckCircle2 className="w-4 h-4 text-[#D6AC48]" />
                                    Verifikasi & Pengesahan
                                </Link>
                            </>
                        )}
                    </nav>

                    {/* Bottom User Profile */}
                    <div className="p-4 border-t border-white/10 bg-black/15">
                        <div className="flex items-center gap-3 mb-3">
                            <div className="w-9 h-9 rounded-full bg-[#122E92] border border-[#D6AC48]/40 flex items-center justify-center font-bold text-xs text-white uppercase">
                                {user?.name?.slice(0, 2) || 'US'}
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="text-xs font-semibold text-white truncate">
                                    {user?.name || 'Pengguna'}
                                </div>
                                <div className="text-[11px] text-slate-400 truncate flex items-center gap-1">
                                    <span className="capitalize text-[#D6AC48] font-medium">{currentRole}</span>
                                    <span>•</span>
                                    <span>{user?.unit_kerja?.singkatan || 'LLDIKTI'}</span>
                                </div>
                            </div>
                        </div>

                        <form onSubmit={handleLogout}>
                            <button
                                type="submit"
                                className="w-full flex items-center justify-center gap-2 px-3 py-1.5 rounded-md text-xs font-medium text-slate-300 hover:bg-rose-500/20 hover:text-rose-300 transition-colors cursor-pointer"
                            >
                                <LogOut className="w-3.5 h-3.5" />
                                Keluar Sistem
                            </button>
                        </form>
                    </div>
                </aside>

                {/* Main Content Area */}
                <div className="flex-1 flex flex-col min-w-0 overflow-y-auto">
                    {/* Top Header */}
                    <header className="bg-white border-b border-slate-200 px-6 py-3.5 flex items-center justify-between shrink-0 shadow-2xs">
                        {/* Breadcrumbs & Title */}
                        <div>
                            {breadcrumbs.length > 0 && (
                                <nav className="flex items-center gap-1.5 text-xs text-slate-500 mb-1">
                                    <Link href="/dashboard" className="hover:text-slate-900">
                                        SAKIP
                                    </Link>
                                    {breadcrumbs.map((b, idx) => (
                                        <React.Fragment key={idx}>
                                            <ChevronRight className="w-3 h-3 text-slate-400" />
                                            {b.href ? (
                                                <Link href={b.href} className="hover:text-slate-900">
                                                    {b.label}
                                                </Link>
                                            ) : (
                                                <span className="font-semibold text-slate-800">{b.label}</span>
                                            )}
                                        </React.Fragment>
                                    ))}
                                </nav>
                            )}
                            <h1 className="text-lg font-bold text-slate-900 tracking-tight">
                                {title || 'Dashboard Kinerja'}
                            </h1>
                        </div>

                        {/* Top Right Badges */}
                        <div className="flex items-center gap-3">
                            <div className="hidden sm:flex items-center gap-2 px-3 py-1 bg-slate-100 rounded-lg text-xs text-slate-600 border border-slate-200">
                                <Calendar className="w-3.5 h-3.5 text-[#122E92]" />
                                <span className="font-medium">Tahun Anggaran 2026</span>
                            </div>

                            <div className="flex items-center gap-2 px-3 py-1 bg-[#122E92]/10 rounded-lg text-xs text-[#122E92] font-semibold border border-[#122E92]/20">
                                <Building2 className="w-3.5 h-3.5 text-[#122E92]" />
                                <span>{user?.unit_kerja?.nama || 'LLDIKTI XVI'}</span>
                            </div>
                        </div>
                    </header>

                    {/* Flash Message Alerts */}
                    {flash?.success && (
                        <div className="mx-6 mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center gap-2.5 text-emerald-800 text-xs font-medium animate-fade-in shadow-xs">
                            <CheckCircle className="w-4 h-4 text-emerald-600 shrink-0" />
                            <span>{flash.success}</span>
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mx-6 mt-4 p-3 bg-rose-50 border border-rose-200 rounded-lg flex items-center gap-2.5 text-rose-800 text-xs font-medium animate-fade-in shadow-xs">
                            <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
                            <span>{flash.error}</span>
                        </div>
                    )}

                    {/* Page Content */}
                    <main className="flex-1 p-6">
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
};
