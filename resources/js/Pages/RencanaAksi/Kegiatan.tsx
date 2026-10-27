import { useId, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { statusKegiatanConfig } from '@/types/kegiatan';
import KegiatanPanel from './KegiatanPanel';
import type { KegiatanTanpaKlaim, RencanaAksiKegiatan, RencanaAksiShow } from './types';

/**
 * Props halaman kegiatan satu Rencana Aksi. Payload `rencanaAksi` sama dengan
 * `RencanaAksi/Show` (sumber `IndexRencanaAksi`); `kegiatan` null bila aktor
 * tidak berhak membaca kegiatan unit — halaman menampilkan state tanpa data,
 * bukan 403.
 */
export interface KegiatanRencanaAksiProps {
    rencanaAksi: RencanaAksiShow;
    kegiatan: RencanaAksiKegiatan | null;
}

/** Galat klaim (422) dapat terikat ke field di luar tiga field formulir tautan. */
function pesanGalatTautan(errors: Record<string, string | undefined>): string {
    return errors.rencana_aksi_id ?? errors.komponen_id ?? errors.arah_dampak ?? errors.catatan
        ?? errors.jendela ?? errors.status_alur ?? errors.unit_id ?? errors.indikator_id
        ?? 'Penautan kegiatan gagal. Muat ulang halaman untuk melihat data terbaru.';
}

interface KegiatanBelumTertautProps {
    rencanaAksiId: string;
    /** Sudah disaring ke periode yang dapat ditulis aktor; server tetap penentu. */
    kandidat: KegiatanTanpaKlaim[];
}

/**
 * Jalur penautan kegiatan lama (mis. lanjutan hasil tunda) lewat endpoint klaim
 * yang sudah ada: tanpa komponen, arah dampak `menambah`, catatan penanda.
 * Redirect `back()` memuat ulang props halaman, jadi kandidat yang tertaut
 * hilang dari daftar tanpa state tambahan di klien.
 */
function KegiatanBelumTertaut({ rencanaAksiId, kandidat }: KegiatanBelumTertautProps) {
    const judulId = useId();
    const [menautkan, setMenautkan] = useState<string | null>(null);
    const [galat, setGalat] = useState('');
    const namaMenautkan = kandidat.find((item) => item.id === menautkan)?.nama ?? '';

    const tautkan = (kegiatanId: string): void => {
        if (menautkan !== null) return;
        setMenautkan(kegiatanId);
        setGalat('');
        router.post(`/kegiatan/${kegiatanId}/klaim`, {
            rencana_aksi_id: rencanaAksiId,
            arah_dampak: 'menambah',
            catatan: 'Penautan kegiatan tanpa klaim.',
        }, {
            preserveScroll: true,
            onSuccess: () => setGalat(''),
            onError: (errors) => setGalat(pesanGalatTautan(errors as Record<string, string | undefined>)),
            onHttpException: (response) => {
                setGalat(response.status === 403
                    ? 'Izin menautkan kegiatan ditolak. Muat ulang halaman untuk melihat akses terbaru.'
                    : 'Hasil penautan belum dapat dipastikan. Muat ulang halaman sebelum mencoba kembali.');
                return false;
            },
            onNetworkError: () => {
                setGalat('Koneksi terputus. Muat ulang halaman untuk memastikan kegiatan sudah tertaut.');
                return false;
            },
            onFinish: () => setMenautkan(null),
        });
    };

    return (
        <Card role="region" aria-labelledby={judulId}>
            <CardHeader>
                <div className="min-w-0 flex-1 space-y-1.5">
                    <CardTitle id={judulId}>Kegiatan belum tertaut</CardTitle>
                    <p className="text-sm text-muted">
                        Kegiatan unit dan tahun yang sama yang belum diklaim rencana aksi mana pun. Penautan hanya menambah klaim; nilai target dan pengukuran tidak berubah.
                    </p>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                <p role="status" className="sr-only">{menautkan ? `Menautkan kegiatan ${namaMenautkan}…` : ''}</p>
                {galat && <p role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark">{galat}</p>}
                <ul className="divide-y divide-border rounded-lg border border-border">
                    {kandidat.map((item) => {
                        const status = statusKegiatanConfig[item.status] ?? { label: item.status, variant: 'muted' as const };
                        const sedangMenautkan = menautkan === item.id;

                        return (
                            <li key={item.id} aria-busy={sedangMenautkan} className="flex flex-wrap items-start justify-between gap-3 p-3">
                                <div className="min-w-0 space-y-1">
                                    <p className="break-words text-sm font-semibold text-ink">{item.nama}</p>
                                    <p className="text-xs text-muted">{item.periode_nama}</p>
                                    <span className="flex flex-wrap gap-1">
                                        <Badge variant={status.variant} size="sm">{status.label}</Badge>
                                        {item.kegiatan_asal_id !== null && <Badge variant="info" size="sm">Lanjutan</Badge>}
                                    </span>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    isLoading={sedangMenautkan}
                                    disabled={menautkan !== null}
                                    aria-label={`Tautkan kegiatan ${item.nama} ke rencana aksi ini`}
                                    onClick={() => tautkan(item.id)}
                                >
                                    {sedangMenautkan ? 'Menautkan…' : 'Tautkan'}
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            </CardContent>
        </Card>
    );
}

/**
 * Halaman kegiatan per periode di dalam Rencana Aksi (Q74 §74.1): satu-satunya
 * permukaan tambah, ubah, klaim, berkas, dan status kegiatan. Otorisasi,
 * kunci periode, dan capability tetap dari server; React hanya menampilkan
 * `kegiatan.can.*` dan `periode_dapat_ditulis`.
 */
export default function KegiatanRencanaAksi({ rencanaAksi, kegiatan }: KegiatanRencanaAksiProps) {
    const judul = `Kegiatan ${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`;
    const halamanRencanaAksi = `/rencana-aksi/${rencanaAksi.id}`;
    // Sama dengan detail RA: hanya periode efektif yang tampil, dan periode di
    // luar `periode_dapat_ditulis` tidak menawarkan tambah kegiatan. Daftar itu
    // sudah dihitung server (efektif ∩ lingkup koreksi − terkunci bagi aktor),
    // jadi klien tidak menurunkan ulang aturan kunci dari `koreksi` +
    // `periode_terkunci`. Tampilan saja; server tetap menolak di bawah lock.
    const periode = [...rencanaAksi.periode].sort((a, b) => a.urutan - b.urutan).filter((baris) => baris.efektif);
    const dapatDitulis = new Set(rencanaAksi.periode_dapat_ditulis ?? []);
    const terkunci = periode.filter((baris) => !dapatDitulis.has(baris.id)).map((baris) => baris.id);
    // Kandidat penautan hanya dari periode yang dapat ditulis aktor (UX); server
    // tetap penentu jendela/periode. Tanpa periode dapat ditulis, kartu tidak tampil.
    const kandidatTautan = kegiatan === null
        ? []
        : (kegiatan.kegiatan_tanpa_klaim ?? []).filter((item) => dapatDitulis.has(item.periode_id));

    return (
        <AuthenticatedLayout
            title={judul}
            breadcrumbs={[
                { label: 'Rencana Aksi', href: '/rencana-aksi' },
                { label: `${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`, href: halamanRencanaAksi },
                { label: 'Kegiatan' },
            ]}
            headerActions={
                <Link href={halamanRencanaAksi} className="ml-auto inline-flex h-9 shrink-0 translate-y-1 items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke rincian
                </Link>
            }
        >
            <Head title={judul} />
            <div className="space-y-5">
                <Card>
                    <CardHeader>
                        <div className="min-w-0 flex-1 space-y-1.5">
                            <CardTitle className="break-words">
                                {rencanaAksi.indikator.kode} · {rencanaAksi.indikator.nama}
                            </CardTitle>
                            <p className="text-xs text-muted">Tahun {rencanaAksi.tahun}</p>
                        </div>
                        <Badge status={rencanaAksi.status_alur} />
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt className="text-muted">Unit penanggung jawab</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.unit?.nama ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Periode efektif</dt>
                                <dd className="mt-1 font-medium">{periode.length > 0 ? `${periode.length} periode` : 'Belum ada'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Penanggung jawab</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.penanggung_jawab?.nama ?? '—'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {kegiatan === null ? (
                    <Card role="status">
                        <CardContent className="space-y-2 p-4 sm:p-6">
                            <p className="text-sm font-semibold text-ink">Kegiatan tidak dapat ditampilkan</p>
                            <p className="text-sm text-muted">
                                Anda tidak memiliki izin membaca kegiatan pada unit rencana aksi ini. Hubungi pengelola izin bila akses
                                diperlukan.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <KegiatanPanel
                            rencanaAksiId={rencanaAksi.id}
                            periode={periode}
                            komponen={rencanaAksi.komponen}
                            terkunci={terkunci}
                            kegiatan={kegiatan}
                            // Halaman ini tidak memuat form target; tidak ada draf yang perlu disimpan sebelum mengelola kegiatan.
                            kotor={false}
                        />
                        {kegiatan.can.tautkan === true && kandidatTautan.length > 0 && (
                            <KegiatanBelumTertaut rencanaAksiId={rencanaAksi.id} kandidat={kandidatTautan} />
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
