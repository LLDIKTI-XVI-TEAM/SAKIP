import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Banner, type BannerItem } from '@/Components/Banner';

export interface PengingatHalaman {
    pribadi: BannerItem[];
    pk: BannerItem[];
    ringkasan?: BannerItem[];
}

export interface PengingatPageProps {
    pengingat?: PengingatHalaman;
}

export function PengingatKontekstual({ pengingat }: PengingatPageProps) {
    const [ditutup, setDitutup] = useState<Record<string, string>>({});
    // Visit replace tidak memicu navigate; success juga mereset kunjungan yang mempertahankan instance halaman.
    useEffect(() => {
        const reset = () => setDitutup({});
        const lepasSuccess = router.on('success', reset);
        const lepasNavigate = router.on('navigate', reset);
        return () => {
            lepasSuccess();
            lepasNavigate();
        };
    }, []);

    const kelompok = [
        { key: 'pribadi', judul: 'Kewajiban Anda', items: pengingat?.pribadi ?? [] },
        { key: 'ringkasan', judul: 'Ringkasan Pengajuan', items: pengingat?.ringkasan ?? [] },
        { key: 'pk', judul: 'Perjanjian Kinerja', items: pengingat?.pk ?? [] },
    ].map((bagian) => ({ ...bagian, items: bagian.items.filter((item) => ditutup[item.id] !== item.signature) }))
        .filter((bagian) => bagian.items.length > 0);

    if (kelompok.length === 0) return null;

    return (
        <div className="mb-5 min-w-0 space-y-3">
            {kelompok.map((bagian) => (
                <section key={bagian.key} aria-label={bagian.judul} className="min-w-0 space-y-2">
                    {bagian.items.map((item) => (
                        <Banner key={item.id} item={item} heading={bagian.judul}
                            onDismiss={() => setDitutup((sebelumnya) => ({ ...sebelumnya, [item.id]: item.signature }))} />
                    ))}
                </section>
            ))}
        </div>
    );
}
