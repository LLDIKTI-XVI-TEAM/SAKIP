import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Card } from '@/Components/Card';
import { Pagination, type PaginationLinkItem } from '@/Components/Pagination';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';

interface ItemNotifikasi {
    id: string;
    jenis: string;
    judul: string;
    ringkasan: string | null;
    dibaca: boolean;
    dibuat_pada: string | null;
}

interface NotifikasiIndexProps {
    notifikasi: {
        data: ItemNotifikasi[];
        links: PaginationLinkItem[];
        from: number | null;
        to: number | null;
        total: number;
    };
}

/**
 * Daftar notifikasi milik pengguna. Setiap item adalah satu tombol: POST menandai dibaca di server lalu
 * mengalihkan ke halaman tujuan (yang memeriksa izin sendiri). GET tidak dipakai agar prefetch tidak menandai dibaca.
 */
export default function NotifikasiIndex({ notifikasi }: NotifikasiIndexProps) {
    const formatTanggal = useFormatTanggal();
    const [sedangDibuka, setSedangDibuka] = useState<string | null>(null);

    const buka = (id: string) => {
        router.post(`/notifikasi/${id}/buka`, {}, {
            onStart: () => setSedangDibuka(id),
            onFinish: () => setSedangDibuka(null),
        });
    };

    return (
        <AuthenticatedLayout title="Notifikasi" breadcrumbs={[{ label: 'Notifikasi' }]}>
            <Head title="Notifikasi" />
            <div className="mx-auto w-full max-w-7xl space-y-3 px-4 py-4 sm:px-6 lg:px-8">
                {notifikasi.data.length === 0 ? (
                    <Card className="px-6 py-10 text-center text-sm text-muted">Belum ada notifikasi.</Card>
                ) : (
                    <ul className="space-y-2">
                        {notifikasi.data.map((item) => {
                            const membuka = sedangDibuka === item.id;

                            return (
                                <li key={item.id}>
                                    <Card className={item.dibaca ? '' : 'border-primary/30'}>
                                        <button
                                            type="button"
                                            onClick={() => buka(item.id)}
                                            disabled={membuka}
                                            aria-busy={membuka || undefined}
                                            className="flex w-full flex-col gap-1 px-4 py-3 text-left transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary/30 disabled:cursor-wait disabled:opacity-70 sm:px-5"
                                        >
                                            <span className="flex flex-wrap items-center gap-2">
                                                {!item.dibaca && <Badge variant="primary" size="sm">Baru</Badge>}
                                                <span className={`text-sm text-ink ${item.dibaca ? 'font-medium' : 'font-semibold'}`}>{item.judul}</span>
                                            </span>
                                            {item.ringkasan && <span className="block text-sm text-muted">{item.ringkasan}</span>}
                                            <span className="block text-xs text-muted">
                                                {membuka ? 'Membuka…' : formatTanggal(item.dibuat_pada, { withTime: true })}
                                            </span>
                                        </button>
                                    </Card>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <Pagination links={notifikasi.links} from={notifikasi.from} to={notifikasi.to} total={notifikasi.total} ariaLabel="Paginasi notifikasi" />
            </div>
        </AuthenticatedLayout>
    );
}
