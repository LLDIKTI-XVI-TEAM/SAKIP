# SAKIP Design System — Implementation Guide

> Panduan implementasi antarmuka dan design system aplikasi **SAKIP** (Sistem Akuntabilitas Kinerja Instansi Pemerintah) LLDIKTI Wilayah XVI.
> Dokumen ini adalah panduan kanonis untuk pengembangan UI SAKIP berbasis arsitektur **Laravel + Inertia.js + React + TypeScript + Tailwind CSS v4**.
>
> Gunakan panduan ini saat membuat atau memodifikasi komponen antarmuka React (`resources/js/Components/`, `Pages/`, `Layouts/`). Jangan menggunakan kelas ad-hoc atau raw hex apabila token dan komponen reusable yang sesuai sudah tersedia.

---

## 📋 Deskripsi Proyek & Arsitektur

Design system dan panduan implementasi UI untuk aplikasi **SAKIP LLDIKTI Wilayah XVI** berbasis:

| Teknologi | Versi / Keterangan |
|-----------|--------------------|
| **Backend** | Laravel (v12 / v13) |
| **Adapter / Bridge** | **Inertia.js** (`@inertiajs/react` v2 & `inertiajs/inertia-laravel`) |
| **Frontend** | **React** + **TypeScript** (Inertia Pages & Components) |
| **Styling** | **Tailwind CSS v4** (`@theme` token-based) |
| **Bundler & Build** | **Vite** (`@vitejs/plugin-react`) |
| **Database** | **PostgreSQL 17** (Database relasional utama) |
| **Visualisasi / Chart** | **ApexCharts** (`react-apexcharts`) |
| **Autentikasi / SSO** | **Keycloak** (OIDC Authorization Code Flow via Socialite) |

---

## 🎯 Prinsip Desain

**Physical scene:** Tim Perencanaan Kinerja, Pimpinan Lembaga, Penanggung Jawab (PIC Unit Kerja), serta Auditor/Admin di lingkungan LLDIKTI Wilayah XVI. Pengguna bekerja menggunakan laptop atau komputer kerja di lingkungan kantor terang, memproses data akuntabilitas instansi: penyusunan Renstra, penjadwalan triwulanan, penginputan realisasi indikator kinerja, pengunggahan dokumen bukti dukung, verifikasi bertingkat, dan pemantauan capaian IKU.

Antarmuka harus **legible**, **scannable**, berorientasi pada **kepadatan data kinerja tinggi**, andal, dan fungsional — bukan dekoratif.

**Color strategy:** Restrained — *tinted neutrals* dengan satu warna institusi utama yang berwibawa (*institutional blue*) membawa otoritas resmi kementerian/lembaga. Warna emas (*secondary gold*) digunakan secara selektif sebagai aksen prestisius. Warna semantik (*success, warning, danger, info*) memiliki peran tegas dalam status alur dan evaluasi capaian kinerja.

- ✅ Tema **light only** — tidak ada dark mode (konsistensi dokumen formal instansi)
- ✅ Prioritaskan **keterbacaan data numerik**, persentase, dan label indikator
- ✅ Tampilan **profesional, enterprise, dan berwibawa** — formal instansi pemerintah tanpa kaku
- ✅ Hindari efek visual berlebihan (no decorative glassmorphism, no gradient text, no side-stripe borders)
- ✅ Pertahankan **spacing konsisten** dan ritme visual berjenjang
- ✅ Semua halaman wajib **mobile responsive** dan rapi di layar desktop
- ✅ **Single Page Application Feel** — Navigasi instan tanpa reload halaman via `<Link>` dari `@inertiajs/react`

---

## 🎨 Referensi Token Desain

Semua token didefinisikan sebagai CSS custom properties di `resources/css/app.css` via Tailwind CSS v4 `@theme {}`.
Warna dan font **tetap sama persis** dengan identitas visual institusi:

### Brand Colors

| Token | CSS Variable | Kelas Tailwind | Nilai Hex | Peran Implementasi |
|-------|-------------|---------------|-----------|---------------------|
| Primary | `--color-primary` | `bg-primary` / `text-primary` / `border-primary` | `#122E92` | Brand utama, CTA, active state sidebar, header resmi |
| Secondary | `--color-secondary` | `bg-secondary` / `text-secondary` | `#D6AC48` | Aksen emas, badge sekunder, highlight target khusus |

### Surface Colors

| Token | CSS Variable | Kelas Tailwind | Nilai Hex | Peran Implementasi |
|-------|-------------|---------------|-----------|---------------------|
| Page | `--color-page` | `bg-page` | `#F8FAFC` | Background body seluruh aplikasi |
| Surface | `--color-surface` | `bg-surface` | `#FFFFFF` | Card, container data, panel, navbar, sidebar |

### Text Colors

| Token | CSS Variable | Kelas Tailwind | Nilai Hex | Peran Implementasi |
|-------|-------------|---------------|-----------|---------------------|
| Ink | `--color-ink` | `text-ink` | `#0F172A` | Teks utama, judul, angka metrik, isi tabel |
| Muted | `--color-muted` | `text-muted` | `#64748B` | Label, metadata, satuan indikator, placeholder |

### Status Colors (Alur & Capaian Kinerja)

| Token | CSS Variable | Kelas Tailwind | Nilai Hex | Penggunaan SAKIP |
|-------|-------------|---------------|-----------|------------------|
| Success | `--color-success` | `bg-success` / `text-success` | `#16A34A` | Status **Disahkan**, status capaian **Tercapai**, Renstra/Jadwal Aktif |
| Warning | `--color-warning` | `bg-warning` / `text-warning` | `#EAB308` | Status **Diajukan** (menunggu verifikasi), pengingat deadline H-3 |
| Warning Dark | `--color-warning-dark` | `text-warning-dark` | `#A16207` | Teks peringatan pada badge/alert terang (kontras ≥ 4.5:1) |
| Danger | `--color-danger` | `bg-danger` / `text-danger` | `#DC2626` | Status **Dikembalikan** (revisi), status capaian **Belum Tercapai**, deadline H-1 |
| Info | `--color-info` | `bg-info` | `#2563EB` | Status **Diverifikasi**, arah **Naik Baik**, pengingat H-7 |
| Info Dark | `--color-info-dark` | `text-info-dark` | `#1D4ED8` | Teks informasi pada badge/alert surface terang |

### Utility Colors

| Token | CSS Variable | Kelas Tailwind | Nilai Hex | Peran Implementasi |
|-------|-------------|---------------|-----------|---------------------|
| Border | `--color-border` | `border-border` | `#E2E8F0` | Garis pemisah tabel, border input, border card |
| Soft | `--color-soft` | `bg-soft` | `#F1F5F9` | Header tabel, background hover menu, fill subtle |

---

## ✅ Kelas Tailwind yang Diizinkan

### Gunakan (Allowed Tokens Only):

```
bg-primary        text-primary        border-primary
bg-secondary      text-secondary      border-secondary
bg-page           bg-surface
text-ink          text-muted
bg-success        text-success        border-success
bg-warning        text-warning        text-warning-dark
bg-danger         text-danger         border-danger
bg-info           text-info-dark
border-border     bg-soft
```

### ❌ Dilarang Keras (Forbidden):

```
bg-blue-600       bg-yellow-500       bg-red-500
text-gray-500     border-gray-300     text-slate-600
```

---

## 🔤 Typography

- **Token**: `font-sans`
- **Font Utama**: `Poppins` (Google Fonts, dikonfigurasi pada Tailwind `@theme` di `app.css`)
- **Font Monospace**: `font-mono` (khusus angka target, persentase, kode indikator, NIP, atau UUID log audit)

```css
/* resources/css/app.css */
@theme {
  --font-sans: 'Poppins', ui-sans-serif, system-ui, sans-serif;
  --font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
```

---

## 🏗️ Struktur Layout Inertia & React

Aplikasi menggunakan root blade template minimalis yang memuat bundle Inertia + React, dengan komponen layout React modular di `resources/js/Layouts/`.

### 1. Root Template: `resources/views/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <title inertia>{{ config('app.name', 'SAKIP LLDIKTI XVI') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="h-full bg-page font-sans text-ink antialiased">
    @inertia
</body>
</html>
```

### 2. Layout Utama: `resources/js/Layouts/AppLayout.tsx`

```tsx
import React, { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import Sidebar from '@/Components/Layouts/Sidebar';
import Navbar from '@/Components/Layouts/Navbar';
import FlashMessages from '@/Components/UI/FlashMessages';
import { PageProps } from '@/types';

interface AppLayoutProps {
    title?: string;
    children: React.ReactNode;
}

export default function AppLayout({ title, children }: AppLayoutProps) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { flash } = usePage<PageProps>().props;

    return (
        <div className="flex min-h-screen bg-page text-ink font-sans">
            <Head title={title ? `${title} — SAKIP LLDIKTI XVI` : 'SAKIP LLDIKTI XVI'} />

            {/* Mobile Overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-ink/50 lg:hidden transition-opacity"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />

            {/* Main Column */}
            <div className="flex flex-1 flex-col min-w-0">
                <Navbar onToggleSidebar={() => setSidebarOpen(prev => !prev)} />

                {/* Flash Messages */}
                {flash && <FlashMessages flash={flash} />}

                {/* Page Content */}
                <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8 max-w-7xl w-full mx-auto">
                    {children}
                </main>
            </div>
        </div>
    );
}
```

---

## 🧩 Komponen Reusable React (TypeScript)

### 1. Button Component (`resources/js/Components/UI/Button.tsx`)

```tsx
import React from 'react';
import { Link } from '@inertiajs/react';

export type ButtonVariant = 
    | 'primary' 
    | 'secondary' 
    | 'success' 
    | 'warning' 
    | 'danger' 
    | 'danger-solid' 
    | 'ghost' 
    | 'link';

export type ButtonSize = 'sm' | 'md' | 'lg' | 'compact';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    href?: string;
    loading?: boolean;
}

const variantClasses: Record<ButtonVariant, string> = {
    primary: 'bg-primary text-white hover:opacity-90 shadow-sm focus:ring-primary/20',
    secondary: 'bg-surface border border-border text-ink hover:bg-soft shadow-sm focus:ring-primary/20',
    success: 'bg-success text-white hover:opacity-90 shadow-sm focus:ring-success/20',
    warning: 'bg-warning text-white hover:opacity-90 shadow-sm focus:ring-warning/20',
    danger: 'bg-surface border border-danger/30 text-danger hover:bg-danger/10 focus:ring-danger/20',
    'danger-solid': 'bg-danger text-white hover:opacity-90 shadow-sm focus:ring-danger/20',
    ghost: 'text-muted hover:bg-soft hover:text-ink',
    link: 'text-primary hover:underline p-0 h-auto font-medium',
};

const sizeClasses: Record<ButtonSize, string> = {
    sm: 'px-3 py-1.5 text-xs rounded-md',
    md: 'px-4 py-2 text-sm rounded-lg',
    lg: 'px-6 py-2.5 text-base rounded-lg',
    compact: 'px-2.5 py-1 text-xs rounded',
};

export default function Button({
    variant = 'primary',
    size = 'md',
    href,
    loading = false,
    className = '',
    children,
    disabled,
    ...props
}: ButtonProps) {
    const baseClasses = 'inline-flex items-center justify-center font-semibold transition-colors focus:outline-none focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed';
    const classes = `${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`;

    if (href) {
        return (
            <Link href={href} className={classes}>
                {children}
            </Link>
        );
    }

    return (
        <button className={classes} disabled={disabled || loading} {...props}>
            {loading && (
                <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-current" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
            )}
            {children}
        </button>
    );
}
```

---

### 2. Badge Component (`resources/js/Components/UI/Badge.tsx`)

```tsx
import React from 'react';

export type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'secondary' | 'muted';

interface BadgeProps {
    variant?: BadgeVariant;
    children: React.ReactNode;
    className?: string;
}

const badgeVariants: Record<BadgeVariant, string> = {
    success: 'bg-success/10 text-success border-success/20',
    warning: 'bg-warning/10 text-warning-dark border-warning/20',
    danger: 'bg-danger/10 text-danger border-danger/20',
    info: 'bg-info/10 text-info-dark border-info/20',
    secondary: 'bg-secondary/15 text-ink border-secondary/30',
    muted: 'bg-soft text-muted border-border',
};

export default function Badge({ variant = 'muted', children, className = '' }: BadgeProps) {
    return (
        <span className={`inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold leading-none ${badgeVariants[variant]} ${className}`}>
            {children}
        </span>
    );
}
```

#### Static Mapping untuk Status SAKIP:

```tsx
// Status Alur Pengukuran: draft, diajukan, diverifikasi, disahkan, dikembalikan
export const alurBadgeMap: Record<string, { variant: BadgeVariant; label: string }> = {
    draft: { variant: 'muted', label: 'Draft' },
    diajukan: { variant: 'warning', label: 'Diajukan' },
    diverifikasi: { variant: 'info', label: 'Diverifikasi' },
    disahkan: { variant: 'success', label: 'Disahkan' },
    dikembalikan: { variant: 'danger', label: 'Dikembalikan' },
};

// Status Capaian: tercapai, belum_tercapai, belum_ditetapkan
export const capaianBadgeMap: Record<string, { variant: BadgeVariant; label: string }> = {
    tercapai: { variant: 'success', label: 'Tercapai' },
    belum_tercapai: { variant: 'danger', label: 'Belum Tercapai' },
    belum_ditetapkan: { variant: 'muted', label: 'Belum Ditetapkan' },
};
```

---

### 3. Modal Alasan Wajib (`resources/js/Components/UI/ModalAlasan.tsx`)

Digunakan pada setiap aksi audit sensitif (pengembalian pengukuran, koreksi PK, buka kembali jadwal/pengukuran):

```tsx
import React, { useState } from 'react';
import Button from './Button';

interface ModalAlasanProps {
    isOpen: boolean;
    title: string;
    description: string;
    onClose: () => void;
    onSubmit: (alasan: string) => void;
    loading?: boolean;
}

export default function ModalAlasan({
    isOpen,
    title,
    description,
    onClose,
    onSubmit,
    loading = false,
}: ModalAlasanProps) {
    const [alasan, setAlasan] = useState('');
    const [error, setError] = useState('');

    if (!isOpen) return null;

    const handleConfirm = () => {
        if (!alasan.trim()) {
            setError('Alasan wajib diisi.');
            return;
        }
        setError('');
        onSubmit(alasan);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4">
            <div className="w-full max-w-lg rounded-xl border border-border bg-surface p-6 shadow-xl">
                <h3 className="text-base font-semibold text-ink flex items-center gap-2">
                    <svg className="h-5 w-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    {title}
                </h3>
                <p className="mt-2 text-xs text-muted leading-relaxed">{description}</p>

                <div className="mt-4 space-y-1">
                    <label className="text-sm font-medium text-ink">
                        Alasan Tindakan <span className="text-danger">*</span>
                    </label>
                    <textarea
                        rows={3}
                        value={alasan}
                        onChange={e => setAlasan(e.target.value)}
                        placeholder="Tuliskan alasan lengkap untuk audit log..."
                        className="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                    {error && <p className="text-xs text-danger">{error}</p>}
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" onClick={onClose} disabled={loading}>
                        Batal
                    </Button>
                    <Button variant="danger-solid" onClick={handleConfirm} loading={loading}>
                        Konfirmasi & Simpan
                    </Button>
                </div>
            </div>
        </div>
    );
}
```

---

## 📝 Pola Form Inertia (`useForm`)

Contoh pengisian form pengukuran realisasi dengan hook `useForm`:

```tsx
// resources/js/Pages/Pengukuran/Edit.tsx
import React from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/UI/Button';

interface Props {
    pengukuran: {
        id: string;
        indikator_nama: string;
        target: number;
        satuan: string;
        nilai: number | null;
        catatan: string | null;
        versi: number;
        wajib_catatan: boolean;
        arah: 'naik_baik' | 'turun_baik';
    };
}

export default function EditPengukuran({ pengukuran }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        nilai: pengukuran.nilai ?? '',
        catatan: pengukuran.catatan ?? '',
        versi: pengukuran.versi,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/pengukuran/${pengukuran.id}`);
    };

    return (
        <AppLayout title="Input Realisasi Kinerja">
            <div className="max-w-2xl mx-auto rounded-xl border border-border bg-surface p-6 shadow-sm">
                <h2 className="text-lg font-semibold text-ink mb-1">{pengukuran.indikator_nama}</h2>
                <p className="text-xs text-muted mb-6">Target: {pengukuran.target} {pengukuran.satuan}</p>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-semibold text-ink mb-1">
                            Nilai Realisasi ({pengukuran.satuan}) <span className="text-danger">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            value={data.nilai}
                            onChange={e => setData('nilai', e.target.value)}
                            className="w-full rounded-lg border border-border bg-surface px-4 py-2.5 font-mono text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                        {errors.nilai && <p className="text-xs text-danger mt-1">{errors.nilai}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-semibold text-ink mb-1">
                            Catatan Kendala & Tindak Lanjut
                        </label>
                        <textarea
                            rows={4}
                            value={data.catatan}
                            onChange={e => setData('catatan', e.target.value)}
                            placeholder="Jelaskan kendala pencapaian bila realisasi di bawah target..."
                            className="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                        {errors.catatan && <p className="text-xs text-danger mt-1">{errors.catatan}</p>}
                    </div>

                    <div className="pt-4 flex justify-end gap-3">
                        <Button variant="secondary" href="/pengukuran">Batal</Button>
                        <Button type="submit" variant="primary" loading={processing}>Simpan Perubahan</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
```

---

## 🗂️ Navigasi Sidebar Berbasis `<Link>` Inertia

Navigasi antar halaman dilakukan menggunakan komponen `<Link>` dari `@inertiajs/react` agar transisi halaman cepat tanpa refresh layar penuh:

```tsx
import { Link, usePage } from '@inertiajs/react';

export default function SidebarNav() {
    const { url } = usePage();

    const navItems = [
        { label: 'Dashboard', href: '/dashboard', active: url.startsWith('/dashboard') },
        { label: 'Master Renstra', href: '/renstra', active: url.startsWith('/renstra') },
        { label: 'Periode & Jadwal', href: '/jadwal', active: url.startsWith('/jadwal') },
        { label: 'Penugasan PIC', href: '/penugasan', active: url.startsWith('/penugasan') },
        { label: 'Pengukuran Kinerja', href: '/pengukuran', active: url.startsWith('/pengukuran') },
        { label: 'Reviu & Pengesahan', href: '/reviu', active: url.startsWith('/reviu') },
        { label: 'Laporan & Ekspor', href: '/laporan', active: url.startsWith('/laporan') },
    ];

    return (
        <nav className="space-y-1 px-3 py-4">
            {navItems.map(item => (
                <Link
                    key={item.href}
                    href={item.href}
                    className={`flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                        item.active 
                            ? 'bg-primary text-white shadow-sm' 
                            : 'text-muted hover:bg-soft hover:text-ink'
                    }`}
                >
                    {item.label}
                </Link>
            ))}
        </nav>
    );
}
```

---

## ✅ Review Checklist Pengembangan UI SAKIP (Inertia + React)

Sebelum merge atau submit kode antarmuka SAKIP, pastikan seluruh item berikut terverifikasi:

- [ ] **Font Poppins**: Menggunakan `font-sans` (Poppins)
- [ ] **Design Tokens**: Menggunakan token resmi (`bg-primary`, `text-primary`, `bg-secondary`, `bg-surface`, `bg-page`, `text-ink`, `text-muted`)
- [ ] **Bebas Raw Hex**: Tidak ada hardcode `#122E92` atau warna Tailwind default di luar token
- [ ] **Navigasi Inertia**: Menggunakan `<Link>` dari `@inertiajs/react` untuk seluruh navigasi internal (hindari tag `<a>` biasa)
- [ ] **Form Handling**: Menggunakan `useForm` dari `@inertiajs/react` untuk submit form dan error binding
- [ ] **TypeScript Safety**: Seluruh komponen dan props memiliki interface/type yang eksplisit
- [ ] **Modal Alasan Audit**: Aksi sensitif memicu modal input alasan sebelum request dikirim
- [ ] **Aksesibilitas & Kontras**: Kontras teks body terhadap background ≥ 4.5:1
- [ ] **Mobile Responsive**: Sidebar off-canvas rapi di mobile, tidak ada horizontal overflow pada viewport

---

*Versi 3.1 — SAKIP LLDIKTI Wilayah XVI — September 2026 — Canonical Implementation Guide for Laravel + Inertia.js + React + TypeScript*
