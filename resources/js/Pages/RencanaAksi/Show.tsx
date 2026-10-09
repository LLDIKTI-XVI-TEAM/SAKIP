import { useMemo, useRef, useState, type FormEvent } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Textarea } from '@/Components/Textarea';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import MatriksTarget from './MatriksTarget';
import TargetPreview from './TargetPreview';
import type { RencanaAksiShow } from './types';
import { dapatDisuntingPeriode, kunciSel } from './types';

interface ShowProps {
    rencanaAksi: RencanaAksiShow;
}

export default function RencanaAksiShow(props: ShowProps) {
    // T6: sertakan versi dalam key agar useForm remount saat Inertia
    // mengembalikan props versi baru pasca-simpan; tanpa ini expected_versi
    // tetap usang dan simpan ke-2 tanpa reload kena 409 palsu. Versi sama
    // (mis. validasi gagal) mempertahankan draf. F4: token snapshot ikut
    // dalam key agar token usang tak dipertahankan bila props disegarkan
    // dengan snapshot koreksi baru pada versi header yang sama. F2: lingkup
    // koreksi ikut dalam key agar perubahan scope tanpa bump versi tetap
    // me-remount formulir (input luar lingkup tak dipertahankan).
    const koreksiKey = props.rencanaAksi.koreksi.aktif
        ? `koreksi:${props.rencanaAksi.koreksi.periode_ids.slice().sort().join(',')}`
        : 'tanpa-koreksi';
    return <RencanaAksiForm key={`${props.rencanaAksi.id}::${props.rencanaAksi.versi}::${props.rencanaAksi.expected_snapshot_id ?? 'tanpa-snapshot'}::${props.rencanaAksi.expected_snapshot_versi ?? 0}::${koreksiKey}`} {...props} />;
}

function RencanaAksiForm({ rencanaAksi }: ShowProps) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const manual = rencanaAksi.tipe_perhitungan === 'manual';
    const desimal = rencanaAksi.indikator.desimal_tampilan;
    const satuan = rencanaAksi.indikator.satuan;
    const errorSummary = useRef<HTMLUListElement>(null);
    const [requestError, setRequestError] = useState('');

    const periodeEfektif = useMemo(
        () => [...rencanaAksi.periode].sort((a, b) => a.urutan - b.urutan).filter((baris) => baris.efektif),
        [rencanaAksi.periode],
    );
    const komponenTerurut = useMemo(
        () => [...rencanaAksi.komponen].sort((a, b) => a.urutan - b.urutan || a.kode.localeCompare(b.kode)),
        [rencanaAksi.komponen],
    );

    // F2: saat koreksi aktif hanya periode dalam `periode_ids` yang
    // disunting/dikirim (kosong = tidak ada); tanpa koreksi semua periode
    // efektif boleh. Validasi fail-closed N1 tetap di backend.
    const koreksi = rencanaAksi.koreksi;
    const bolehSunting = (periodeId: string): boolean => dapatDisuntingPeriode(koreksi, periodeId);
    const periodeDapatDisunting = useMemo(
        () => periodeEfektif.filter((baris) => bolehSunting(baris.id)),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [periodeEfektif, koreksi.aktif, JSON.stringify(koreksi.periode_ids)],
    );

    const { nilaiAwal, keteranganAwal, urutanKirim } = useMemo(() => {
        const nilai: Record<string, string> = {};
        const keterangan: Record<string, string | null> = {};
        const order: { periode_id: string; komponen_id: string | null; key: string }[] = [];
        for (const baris of periodeEfektif) {
            const terkunci = !bolehSunting(baris.id);
            if (manual) {
                const sel = baris.nilai.find((cell) => cell.komponen_id === null) ?? baris.nilai[0];
                const key = kunciSel(baris.id, null);
                nilai[key] = sel?.nilai === null || sel?.nilai === undefined ? '' : String(sel.nilai);
                keterangan[key] = sel?.keterangan ?? null;
                // F2: periode di luar lingkup koreksi tidak dikirim agar
                // koreksi parsial (mis. 1 dari 4) tersimpan via UI.
                if (!terkunci) {
                    order.push({ periode_id: baris.id, komponen_id: null, key });
                }
            } else {
                for (const item of komponenTerurut) {
                    const sel = baris.nilai.find((cell) => cell.komponen_id === item.komponen_id);
                    const key = kunciSel(baris.id, item.komponen_id);
                    nilai[key] = sel?.nilai === null || sel?.nilai === undefined ? '' : String(sel.nilai);
                    keterangan[key] = sel?.keterangan ?? null;
                    if (!terkunci) {
                        order.push({ periode_id: baris.id, komponen_id: item.komponen_id, key });
                    }
                }
            }
        }
        return { nilaiAwal: nilai, keteranganAwal: keterangan, urutanKirim: order };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [periodeEfektif, komponenTerurut, manual, koreksi.aktif, JSON.stringify(koreksi.periode_ids)]);

    const { data, setData, transform, post, processing, errors } = useForm({
        expected_versi: rencanaAksi.expected_versi,
        // F4: token konkurensi snapshot dikembalikan apa adanya (tanpa
        // logika formula di React); server menolak 409 bila snapshot
        // terbaru berubah sejak payload dibaca.
        expected_snapshot_id: rencanaAksi.expected_snapshot_id,
        expected_snapshot_versi: rencanaAksi.expected_snapshot_versi,
        uraian: rencanaAksi.uraian ?? '',
        alasan_deviasi_pk: rencanaAksi.alasan_deviasi_pk ?? '',
        nilai: nilaiAwal,
    });

    const fieldErrors = errors as Record<string, string | undefined>;
    const konflik = fieldErrors.expected_versi ?? fieldErrors.expected_snapshot_id;
    const canUpdate = rencanaAksi.can.update;
    const formDisabled = !canUpdate || processing;
    const kosong = periodeEfektif.length === 0 || (!manual && komponenTerurut.length === 0);
    // F2: koreksi aktif dengan lingkup menyisakan sebagian periode — hanya
    // yang tercakup yang dikirim; bila tak ada yang tercakup, simpan
    // dinonaktifkan (backend menolak targets kosong).
    const terkunciSemua = !kosong && periodeDapatDisunting.length === 0;

    const indeksKirim = useMemo(() => {
        const map = new Map<string, number>();
        urutanKirim.forEach((item, index) => map.set(item.key, index));
        return map;
    }, [urutanKirim]);

    const galatSel = (periodeId: string, komponenId: string | null): string | undefined => {
        const key = kunciSel(periodeId, komponenId);
        const index = indeksKirim.get(key);
        if (index === undefined) {
            return undefined;
        }
        return (
            fieldErrors[`targets.${index}.nilai`] ??
            fieldErrors[`targets.${index}.komponen_id`] ??
            fieldErrors[`targets.${index}.periode_id`] ??
            fieldErrors[`targets.${index}.keterangan`]
        );
    };

    const kotor = useMemo(() => {
        if (data.uraian !== (rencanaAksi.uraian ?? '')) {
            return true;
        }
        if (data.alasan_deviasi_pk !== (rencanaAksi.alasan_deviasi_pk ?? '')) {
            return true;
        }
        return urutanKirim.some((item) => (data.nilai[item.key] ?? '') !== (nilaiAwal[item.key] ?? ''));
    }, [data.uraian, data.alasan_deviasi_pk, data.nilai, nilaiAwal, urutanKirim, rencanaAksi.uraian, rencanaAksi.alasan_deviasi_pk]);

    const ringkasanGalat = useMemo(() => Object.entries(fieldErrors).filter((entry): entry is [string, string] => typeof entry[1] === 'string' && entry[1].length > 0), [fieldErrors]);

    const handleNilai = (periodeId: string, komponenId: string | null, value: string): void => {
        const key = kunciSel(periodeId, komponenId);
        setData('nilai', { ...data.nilai, [key]: value });
        if (requestError !== '') {
            setRequestError('');
        }
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (formDisabled || kosong || terkunciSemua || requestError !== '') {
            return;
        }
        setRequestError('');
        const targets = urutanKirim.map((item) => ({
            periode_id: item.periode_id,
            komponen_id: item.komponen_id,
            nilai: (data.nilai[item.key] ?? '') === '' ? null : data.nilai[item.key],
            keterangan: keteranganAwal[item.key] ?? null,
        }));
        transform(() => ({
            expected_versi: data.expected_versi,
            expected_snapshot_id: data.expected_snapshot_id,
            expected_snapshot_versi: data.expected_snapshot_versi,
            uraian: data.uraian,
            alasan_deviasi_pk: data.alasan_deviasi_pk,
            targets,
        }));
        post(`/rencana-aksi/${rencanaAksi.id}/target`, {
            preserveScroll: true,
            onError: () => {
                requestAnimationFrame(() => errorSummary.current?.focus());
            },
            onCancel: () => {
                setRequestError('Permintaan dibatalkan. Hasil penyimpanan belum diketahui; periksa data terbaru sebelum mencoba kembali.');
            },
            onNetworkError: () => {
                setRequestError('Koneksi terputus. Hasil penyimpanan belum diketahui; periksa status rencana aksi sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => {
                if (response.status === 403) {
                    setRequestError('Izin penyimpanan ditolak. Periksa akses sebelum mencoba kembali.');
                } else {
                    setRequestError('Hasil penyimpanan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                }
                return false;
            },
        });
    };

    const muatUlang = (): void => {
        router.get(`/rencana-aksi/${rencanaAksi.id}`, {}, { preserveScroll: false, preserveState: false });
    };

    const deviasi = rencanaAksi.deviasi_pk;

    // F5: pratinjau reaktif server-side (tanpa persistensi, tanpa formula di
    // React). Dibangun dari nilai formulir saat ini untuk periode yang
    // dikirim (di luar lingkup koreksi tak ikut), dipanggil debounce oleh
    // `TargetPreview` mengikuti pola `CalculationPreview` pengukuran.
    // F1+F2: versi header + token snapshot halaman ikut dikirim ke preview
    // agar konteks usang ditolak 409 — yang ditampilkan = yang dipakai
    // simpan.
    const targetsPreview = useMemo(
        () =>
            urutanKirim.map((item) => ({
                periode_id: item.periode_id,
                komponen_id: item.komponen_id,
                nilai: (data.nilai[item.key] ?? '') === '' ? null : data.nilai[item.key],
            })),
        [urutanKirim, data.nilai],
    );
    const namaPeriode = (periodeId: string): string =>
        rencanaAksi.periode.find((baris) => baris.id === periodeId)?.nama ?? 'Periode';

    return (
        <AuthenticatedLayout
            title={`Rencana Aksi ${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}`}
            breadcrumbs={[{ label: 'Rencana Aksi', href: '/rencana-aksi' }, { label: `${rencanaAksi.indikator.kode} · ${rencanaAksi.tahun}` }]}
        >
            <Head title={`Rencana Aksi ${rencanaAksi.indikator.kode}`} />
            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href="/rencana-aksi" className="inline-flex items-center gap-2 rounded text-sm text-primary focus:outline-none focus:ring-2 focus:ring-primary">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Kembali ke daftar
                    </Link>
                    <div className="flex items-center gap-2">
                        <Badge status={rencanaAksi.status_alur} />
                        <span className="text-xs text-muted">Versi {rencanaAksi.versi}</span>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {rencanaAksi.indikator.kode} · {rencanaAksi.indikator.nama}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <dl className="grid gap-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted">Unit penanggung jawab</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.unit?.nama ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Cara hitung</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.tipe_perhitungan.replaceAll('_', ' ')}</dd>
                                <dd className="text-xs text-muted">{rencanaAksi.indikator.arah === 'turun_baik' ? 'Nilai lebih kecil lebih baik' : 'Nilai lebih besar lebih baik'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Target PK tahunan</dt>
                                <dd className="mt-1 font-medium">
                                    {rencanaAksi.target_pk === null ? 'Belum tersedia' : `${formatNilai(rencanaAksi.target_pk, desimal)} ${satuan}`}
                                </dd>
                            </div>
                        </dl>
                        <dl className="grid gap-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted">Penanggung jawab</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.penanggung_jawab?.nama ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Jendela penyusunan</dt>
                                <dd className="mt-1 font-medium">
                                    {rencanaAksi.jadwal.rencana_aksi_mulai ? formatTanggal(rencanaAksi.jadwal.rencana_aksi_mulai) : '—'}
                                    {' — '}
                                    {rencanaAksi.jadwal.rencana_aksi_selesai ? formatTanggal(rencanaAksi.jadwal.rencana_aksi_selesai) : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted">Penutupan jadwal</dt>
                                <dd className="mt-1 font-medium">{rencanaAksi.jadwal.penutupan ? formatTanggal(rencanaAksi.jadwal.penutupan) : '—'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {!canUpdate && !terkunciSemua && (
                    <p className="rounded-lg border border-info/30 bg-info/10 p-4 text-sm text-info-dark">
                        Formulir hanya dapat dibaca sesuai status dan izin akses Anda.
                    </p>
                )}
                {koreksi.aktif && !terkunciSemua && (
                    <p className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning-dark" role="note">
                        Sesi koreksi aktif: hanya {periodeDapatDisunting.length} dari {periodeEfektif.length} periode dalam lingkup yang dapat
                        disunting; baris lain dikunci dan tidak dikirim.
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Deviasi terhadap target PK</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        {!deviasi.dapat_dinilai ? (
                            <p className="text-muted">Deviasi belum dapat dinilai; skor periode terakhir atau target PK belum tersedia.</p>
                        ) : deviasi.ada ? (
                            <div role="note" className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning-dark">
                                <p className="font-semibold">Skor periode terakhir berbeda dari target PK</p>
                                <p className="mt-1">
                                    Skor {deviasi.skor_periode_terakhir !== null ? `${formatNilai(deviasi.skor_periode_terakhir, desimal)} ${satuan}` : '—'}
                                    {' vs '}
                                    target PK {deviasi.target_pk !== null ? `${formatNilai(deviasi.target_pk, desimal)} ${satuan}` : '—'}.
                                    Alasan deviasi diperlukan dan disimpan pada kolom alasan (D5); peringatan ini tidak memblokir penyimpanan.
                                </p>
                                {!deviasi.alasan_terisi && (
                                    <p className="mt-2">Alasan belum terisi; lengkapi kolom alasan deviasi sebelum pengajuan.</p>
                                )}
                            </div>
                        ) : (
                            <p className="text-muted">
                                Skor periode terakhir setara dengan target PK
                                {deviasi.skor_periode_terakhir !== null ? ` (${formatNilai(deviasi.skor_periode_terakhir, desimal)} ${satuan})` : ''};
                                alasan deviasi tidak diperlukan.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="space-y-6" aria-busy={processing}>
                    {ringkasanGalat.length > 0 && (
                        <ul ref={errorSummary} tabIndex={-1} id="rencana-aksi-errors" role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                            {ringkasanGalat.map(([field, message]) => (
                                <li key={field}>{message}</li>
                            ))}
                        </ul>
                    )}
                    {konflik && (
                        <div role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-4 text-sm">
                            <p className="font-semibold text-danger">Data telah berubah di server</p>
                            <p className="mt-1 text-danger">{konflik}</p>
                            <Button type="button" variant="outline" onClick={muatUlang} disabled={processing} className="mt-3">
                                Muat ulang data terbaru
                            </Button>
                        </div>
                    )}
                    {requestError !== '' && (
                        <p role="alert" className="text-sm text-danger">{requestError}</p>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Matriks target per periode</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {kosong ? (
                                <p className="rounded-lg border border-border bg-soft p-4 text-sm text-muted">
                                    {manual && periodeEfektif.length === 0
                                        ? 'Tidak ada periode efektif untuk indikator ini pada tahun berjalan.'
                                        : 'Definisi komponen efektif belum tersedia untuk indikator nonmanual.'}
                                </p>
                            ) : (
                                <>
                                    {/* Saat semua periode terkunci, nilai tersimpan tetap terbaca; hanya petunjuk isian yang diganti. */}
                                    {terkunciSemua ? (
                                        <p className="rounded-lg border border-border bg-soft p-4 text-sm text-muted">
                                            Tidak ada periode yang dapat dikoreksi.
                                        </p>
                                    ) : (
                                        <p className="text-sm text-muted">
                                            Isi setiap sel periode yang berlaku. Kolom skor menampilkan hasil tersimpan dari server dan tidak dihitung ulang di peramban.
                                            {koreksi.aktif ? ' Baris di luar lingkup koreksi dikunci dan tidak dikirim.' : ''}
                                            {kotor ? ' Perubahan input belum mengubah hasil ini; simpan untuk memperbarui.' : ''}
                                        </p>
                                    )}
                                    <MatriksTarget
                                        tipePerhitungan={rencanaAksi.tipe_perhitungan}
                                        komponen={komponenTerurut}
                                        periode={[...rencanaAksi.periode].sort((a, b) => a.urutan - b.urutan)}
                                        desimalTampilan={desimal}
                                        satuan={satuan}
                                        disabled={formDisabled || kosong || terkunciSemua}
                                        dapatDisunting={bolehSunting}
                                        values={data.nilai}
                                        onValueChange={handleNilai}
                                        galatSel={galatSel}
                                        formatNilai={formatNilai}
                                    />
                                    {canUpdate && !terkunciSemua && (
                                        <TargetPreview
                                            id={rencanaAksi.id}
                                            targets={targetsPreview}
                                            alasanDeviasi={data.alasan_deviasi_pk}
                                            satuan={satuan}
                                            desimalTampilan={desimal}
                                            komponen={komponenTerurut}
                                            namaPeriode={namaPeriode}
                                            expectedVersi={rencanaAksi.expected_versi}
                                            expectedSnapshotId={rencanaAksi.expected_snapshot_id}
                                            expectedSnapshotVersi={rencanaAksi.expected_snapshot_versi}
                                            disabled={formDisabled || kosong || terkunciSemua}
                                        />
                                    )}
                                    <div className="rounded-lg border border-border bg-soft p-4">
                                        <p className="text-xs font-medium text-muted">Skor tersimpan per periode (server)</p>
                                        <ul className="mt-2 space-y-1 text-sm">
                                            {periodeEfektif.map((baris) => (
                                                <li key={baris.id} className="flex flex-wrap items-center justify-between gap-2">
                                                    <span>{baris.nama}</span>
                                                    <span className="font-mono font-semibold">
                                                        {baris.skor.nilai === null
                                                            ? (baris.skor.status_perhitungan === 'tidak_dapat_dihitung' ? 'Tidak dapat dihitung' : 'Belum diisi')
                                                            : `${formatNilai(baris.skor.nilai, desimal)} ${satuan}`}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                        <p className="mt-2 text-xs text-muted">Perubahan input belum mengubah hasil ini.</p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Uraian dan alasan deviasi</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Textarea
                                name="uraian"
                                label="Uraian rencana aksi"
                                value={data.uraian}
                                onChange={(event) => {
                                    setData('uraian', event.target.value);
                                    if (requestError !== '') {
                                        setRequestError('');
                                    }
                                }}
                                disabled={formDisabled}
                                helperText="Ringkasan pendekatan pencapaian target per periode."
                                error={fieldErrors.uraian}
                                aria-invalid={fieldErrors.uraian ? true : undefined}
                                aria-describedby={fieldErrors.uraian ? 'rencana-aksi-errors' : undefined}
                            />
                            <Textarea
                                name="alasan_deviasi_pk"
                                label="Alasan deviasi terhadap target PK (D5)"
                                value={data.alasan_deviasi_pk}
                                onChange={(event) => {
                                    setData('alasan_deviasi_pk', event.target.value);
                                    if (requestError !== '') {
                                        setRequestError('');
                                    }
                                }}
                                disabled={formDisabled}
                                helperText={
                                    deviasi.alasan_diperlukan
                                        ? 'Skor terakhir menyimpang dari target PK; alasan diperlukan sebelum pengajuan dan disimpan di sini.'
                                        : 'Diisi bila skor terakhir menyimpang dari target PK; penegakan wajib milik gerbang pengajuan.'
                                }
                                error={fieldErrors.alasan_deviasi_pk}
                                aria-invalid={fieldErrors.alasan_deviasi_pk ? true : undefined}
                                aria-describedby={fieldErrors.alasan_deviasi_pk ? 'rencana-aksi-errors' : undefined}
                            />
                        </CardContent>
                    </Card>

                    {canUpdate && !kosong && !terkunciSemua && (
                        <div className="flex flex-wrap justify-end gap-3 border-t border-border pt-4">
                            <Button type="submit" variant="primary" isLoading={processing} disabled={processing || requestError !== ''}>
                                <Save className="mr-2 h-4 w-4" aria-hidden="true" />
                                Simpan Target
                            </Button>
                        </div>
                    )}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
