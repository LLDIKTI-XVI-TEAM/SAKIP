import { useId, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { teksTotalAnggaran } from '@/lib/rupiah';
import { ARAH_DAMPAK_OPTIONS, statusKegiatanConfig, type ArahDampak } from '@/types/kegiatan';
import KegiatanPanel from './KegiatanPanel';
import type { KegiatanTanpaKlaim, RencanaAksiKegiatan, RencanaAksiKomponen, RencanaAksiShow } from './types';

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

/**
 * Galat klaim (422) yang tidak terikat field baris; `arah_dampak`, `komponen_id`,
 * dan `catatan` tampil pada kontrol barisnya masing-masing.
 */
function pesanGalatTautan(errors: Record<string, string | undefined>): string {
    return errors.rencana_aksi_id ?? errors.jendela ?? errors.status_alur ?? errors.unit_id ?? errors.indikator_id
        ?? 'Penautan kegiatan gagal. Muat ulang halaman untuk melihat data terbaru.';
}

/** Field klaim yang galatnya tampil pada kontrol baris penautan, bukan alert kartu. */
type FieldTautan = 'arah_dampak' | 'komponen_id' | 'catatan';
type GalatBaris = Partial<Record<FieldTautan, string>>;

interface KegiatanBelumTertautProps {
    rencanaAksiId: string;
    /** Komponen indikator beku RA tujuan; penautan boleh tanpa komponen. */
    komponen: RencanaAksiKomponen[];
    /** Sudah disaring ke periode yang dapat ditulis aktor; server tetap penentu. */
    kandidat: KegiatanTanpaKlaim[];
}

/**
 * Jalur penautan kegiatan lama tanpa klaim lewat endpoint klaim yang sudah ada:
 * arah dampak wajib per kegiatan, komponen dan catatan opsional mengikuti isian
 * `KlaimPanel`. Redirect `back()` memuat ulang props halaman, jadi kandidat yang
 * tertaut hilang dari daftar tanpa state tambahan di klien.
 */
function KegiatanBelumTertaut({ rencanaAksiId, komponen, kandidat }: KegiatanBelumTertautProps) {
    const judulId = useId();
    const [menautkan, setMenautkan] = useState<string | null>(null);
    const [galat, setGalat] = useState('');
    const [galatBaris, setGalatBaris] = useState<Record<string, GalatBaris>>({});
    const [arah, setArah] = useState<Record<string, ArahDampak | ''>>({});
    const [komponenId, setKomponenId] = useState<Record<string, string>>({});
    const [catatan, setCatatan] = useState<Record<string, string>>({});
    const namaMenautkan = kandidat.find((item) => item.id === menautkan)?.nama ?? '';
    const opsiKomponen = [
        { value: '', label: 'Tanpa komponen' },
        ...komponen.map((item) => ({ value: item.komponen_id, label: `${item.kode} — ${item.label}` })),
    ];

    const hapusGalat = (kegiatanId: string, field: FieldTautan): void => {
        setGalatBaris((sebelum) => {
            if (sebelum[kegiatanId]?.[field] === undefined) return sebelum;
            const sisaBaris = { ...sebelum[kegiatanId] };
            delete sisaBaris[field];
            const sisa = { ...sebelum };
            if (Object.keys(sisaBaris).length > 0) {
                sisa[kegiatanId] = sisaBaris;
            } else {
                delete sisa[kegiatanId];
            }
            return sisa;
        });
    };

    const hapusGalatBaris = (kegiatanId: string): void => {
        setGalatBaris((sebelum) => {
            if (sebelum[kegiatanId] === undefined) return sebelum;
            const sisa = { ...sebelum };
            delete sisa[kegiatanId];
            return sisa;
        });
    };

    const pilihArah = (kegiatanId: string, nilai: string): void => {
        setArah((sebelum) => ({ ...sebelum, [kegiatanId]: nilai as ArahDampak | '' }));
        // Galat arah lama merujuk pilihan sebelumnya; pilihan baru belum tentu salah.
        hapusGalat(kegiatanId, 'arah_dampak');
    };

    const pilihKomponen = (kegiatanId: string, nilai: string): void => {
        setKomponenId((sebelum) => ({ ...sebelum, [kegiatanId]: nilai }));
        hapusGalat(kegiatanId, 'komponen_id');
    };

    const ubahCatatan = (kegiatanId: string, nilai: string): void => {
        setCatatan((sebelum) => ({ ...sebelum, [kegiatanId]: nilai }));
        hapusGalat(kegiatanId, 'catatan');
    };

    const tautkan = (kegiatanId: string): void => {
        const arahDipilih = arah[kegiatanId] ?? '';
        if (menautkan !== null || arahDipilih === '') return;
        setMenautkan(kegiatanId);
        setGalat('');
        hapusGalatBaris(kegiatanId);
        // Kosong berarti tanpa komponen/catatan: string kosong tidak setara null pada validasi uuid/nullable.
        const catatanBaris = (catatan[kegiatanId] ?? '').trim();
        router.post(`/kegiatan/${kegiatanId}/klaim`, {
            rencana_aksi_id: rencanaAksiId,
            arah_dampak: arahDipilih,
            komponen_id: komponenId[kegiatanId] || null,
            catatan: catatanBaris === '' ? null : catatanBaris,
        }, {
            preserveScroll: true,
            onSuccess: () => setGalat(''),
            onError: (errors) => {
                const daftarGalat = errors as Record<string, string | undefined>;
                const galatArahBaru = daftarGalat.arah_dampak;
                const galatKomponenBaru = daftarGalat.komponen_id;
                const galatCatatanBaru = daftarGalat.catatan;
                const galatField: GalatBaris = {
                    ...(galatArahBaru ? { arah_dampak: galatArahBaru } : {}),
                    ...(galatKomponenBaru ? { komponen_id: galatKomponenBaru } : {}),
                    ...(galatCatatanBaru ? { catatan: galatCatatanBaru } : {}),
                };
                if (Object.keys(galatField).length > 0) {
                    setGalatBaris((sebelum) => ({ ...sebelum, [kegiatanId]: { ...(sebelum[kegiatanId] ?? {}), ...galatField } }));
                }
                // Galat field tampil di kontrol barisnya; alert umum hanya untuk galat lain.
                const adaGalatLain = Object.entries(daftarGalat).some(([kunci, pesan]) => Boolean(pesan)
                    && kunci !== 'arah_dampak' && kunci !== 'komponen_id' && kunci !== 'catatan');
                setGalat(adaGalatLain ? pesanGalatTautan(daftarGalat) : '');
            },
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
                        Kegiatan unit dan tahun yang sama yang belum diklaim rencana aksi mana pun. Pilih arah dampak tiap kegiatan, lengkapi komponen dan catatan bila perlu, lalu tautkan. Penautan hanya menambah klaim; nilai target dan pengukuran tidak berubah.
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
                        const arahDipilih = arah[item.id] ?? '';
                        const galatKolom = galatBaris[item.id] ?? {};

                        return (
                            <li key={item.id} aria-busy={sedangMenautkan} className="flex flex-wrap items-start justify-between gap-3 p-3">
                                <div className="min-w-0 space-y-1">
                                    <p className="break-words text-sm font-semibold text-ink">{item.nama}</p>
                                    <p className="text-xs text-muted">{item.periode_nama}</p>
                                    <Badge variant={status.variant} size="sm">{status.label}</Badge>
                                </div>
                                <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start">
                                    <div className="w-full shrink-0 sm:w-48">
                                        <Select
                                            id={`tautkan-arah-${item.id}`}
                                            label="Arah dampak"
                                            aria-label={`Arah dampak kegiatan ${item.nama}`}
                                            required
                                            value={arahDipilih}
                                            onChange={(e) => pilihArah(item.id, e.target.value)}
                                            options={[{ value: '', label: 'Pilih arah dampak' }, ...ARAH_DAMPAK_OPTIONS]}
                                            error={galatKolom.arah_dampak}
                                            disabled={sedangMenautkan}
                                        />
                                    </div>
                                    <div className="w-full shrink-0 sm:w-56">
                                        <Select
                                            id={`tautkan-komponen-${item.id}`}
                                            label="Komponen (opsional)"
                                            aria-label={`Komponen kegiatan ${item.nama}`}
                                            hoverScroll
                                            value={komponenId[item.id] ?? ''}
                                            onChange={(e) => pilihKomponen(item.id, e.target.value)}
                                            options={opsiKomponen}
                                            error={galatKolom.komponen_id}
                                            disabled={sedangMenautkan}
                                        />
                                    </div>
                                    <div className="w-full sm:min-w-[12rem] sm:flex-1">
                                        <Input
                                            id={`tautkan-catatan-${item.id}`}
                                            label="Catatan (opsional)"
                                            aria-label={`Catatan klaim kegiatan ${item.nama}`}
                                            maxLength={255}
                                            value={catatan[item.id] ?? ''}
                                            onChange={(e) => ubahCatatan(item.id, e.target.value)}
                                            error={galatKolom.catatan}
                                            disabled={sedangMenautkan}
                                        />
                                    </div>
                                    {/* mt-8 menyelaraskan tombol h-9 dengan kontrol Select h-[42px] di bawah label baris. */}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className="w-full sm:mt-8 sm:w-auto"
                                        isLoading={sedangMenautkan}
                                        disabled={arahDipilih === '' || menautkan !== null}
                                        aria-label={`Tautkan kegiatan ${item.nama} ke rencana aksi ini`}
                                        onClick={() => tautkan(item.id)}
                                    >
                                        {sedangMenautkan ? 'Menautkan…' : 'Tautkan'}
                                    </Button>
                                </div>
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
    // Kegiatan unik; beberapa klaim komponen pada kegiatan yang sama tidak dihitung ganda. Angka tampilan, bukan formula.
    const jumlahKegiatan = new Set((kegiatan?.daftar ?? []).map((row) => row.kegiatan_id)).size;

    return (
        <AuthenticatedLayout
            title={judul}
            breadcrumbs={[
                { label: 'Rencana Aksi', href: '/rencana-aksi' },
                { label: `${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`, href: halamanRencanaAksi },
                { label: 'Kegiatan' },
            ]}
        >
            <Head title={judul} />
            <div className="space-y-5">
                <Link href={halamanRencanaAksi} className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke rincian rencana aksi
                </Link>

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
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-5 text-sm lg:grid-cols-4">
                            <div>
                                <dt className="text-muted">Unit penanggung jawab</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.unit?.nama ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Penanggung jawab</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.penanggung_jawab?.nama ?? '—'}</dd>
                            </div>
                            {kegiatan && (
                                <>
                                    <div>
                                        <dt className="text-muted">Jumlah kegiatan</dt>
                                        <dd className="mt-1 font-medium">{jumlahKegiatan}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted">Total anggaran</dt>
                                        <dd className="mt-1 font-medium">{teksTotalAnggaran(kegiatan.total)}</dd>
                                    </div>
                                </>
                            )}
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
                            <KegiatanBelumTertaut
                                rencanaAksiId={rencanaAksi.id}
                                komponen={rencanaAksi.komponen}
                                kandidat={kandidatTautan}
                            />
                        )}
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
