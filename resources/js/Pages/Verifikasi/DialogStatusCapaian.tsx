import { useId, useState, type FormEvent, type ReactNode } from 'react';
import { router, useForm } from '@inertiajs/react';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Button } from '@/Components/Button';
import { Modal } from '@/Components/Modal';
import { StatusCapaianBadge } from '@/Components/Pengukuran/StatusCapaianBadge';
import { Textarea } from '@/Components/Textarea';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { BarisStatusCapaian, FormStatusCapaian, ModeStatusCapaian } from '@/types/statusCapaian';

const labelStatus: Record<'tercapai' | 'belum_tercapai', string> = {
    tercapai: 'Tercapai',
    belum_tercapai: 'Belum tercapai',
};

/** Selisih realisasi terhadap target PK; tanda mengikuti nilai server, warna tidak dipakai sebagai satu-satunya makna. */
function NilaiSelisihPk({ selisih, desimal, satuan }: { selisih: string | null; desimal: number; satuan: string | null }) {
    const formatNilai = useFormatNilai();
    if (selisih === null) return <span className="text-muted">-</span>;

    const negatif = selisih.startsWith('-');
    const mutlak = formatNilai(negatif ? selisih.slice(1) : selisih, desimal);
    const nol = !/[1-9]/.test(mutlak);
    const tanda = nol ? '' : negatif ? '−' : '+';

    return (
        <span>
            <span className="font-semibold tabular-nums text-ink">{`${tanda}${mutlak}${satuan ? ` ${satuan}` : ''}`}</span>
            <span className="block text-xs font-normal text-muted">{nol ? 'sama dengan target PK' : negatif ? 'di bawah target PK' : 'di atas target PK'}</span>
        </span>
    );
}

/**
 * Angka versi pengukuran; dipakai bersama dialog penetapan dan halaman rincian agar isinya selalu sama.
 * `luas` adalah tampilan kartu halaman rincian: tanpa latar, angka lebih besar, dan tanpa butir versi karena
 * versi sudah tampil di kartu identitas halaman itu.
 */
export function RincianAngka({ baris, children, luas = false }: { baris: BarisStatusCapaian; children?: ReactNode; luas?: boolean }) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const desimal = baris.indikator.desimal_tampilan ?? 0;
    const satuan = baris.indikator.satuan;
    const angka = (nilai: string | null) => nilai === null ? '-' : `${formatNilai(nilai, desimal)}${satuan ? ` ${satuan}` : ''}`;
    const butir = (label: string, isi: ReactNode) => (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-muted">{label}</dt>
            <dd className={`break-words font-semibold text-ink ${luas ? 'mt-1 text-lg tabular-nums' : 'mt-0.5 text-xs'}`}>{isi}</dd>
        </div>
    );

    return (
        <dl className={luas ? 'grid gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-5' : 'grid grid-cols-2 gap-3 rounded-lg bg-soft p-3'}>
            {butir('Realisasi', angka(baris.nilai))}
            {butir('Target periode', angka(baris.target))}
            {butir('Capaian terhadap target', baris.capaian_persen === null ? '-' : `${formatNilai(baris.capaian_persen, 2)}%`)}
            {butir('Target PK', angka(baris.target_pk))}
            {butir('Selisih terhadap target PK', <NilaiSelisihPk selisih={baris.selisih_target_pk} desimal={desimal} satuan={satuan} />)}
            {!luas && butir('Versi pengukuran', baris.versi_pengukuran === null
                ? 'Belum tersedia'
                : `Versi ${baris.versi_pengukuran.nomor} · disahkan ${formatTanggal(baris.versi_pengukuran.disahkan_pada)}`)}
            {children}
        </dl>
    );
}

/** Dialog penetapan dan revisi status capaian; dibuka dari daftar maupun halaman rincian dengan alur simpan yang sama. */
export default function DialogStatusCapaian({ baris, mode, onClose }: { baris: BarisStatusCapaian; mode: ModeStatusCapaian; onClose: () => void }) {
    const formatNilai = useFormatNilai();
    const formatTanggal = useFormatTanggal();
    const recovery = useAuthRecovery();
    const formId = useId();
    const statusErrorId = `${formId}-status-error`;
    const [pesan, setPesan] = useState('');
    const [perluMuatUlang, setPerluMuatUlang] = useState(false);
    const form = useForm<FormStatusCapaian>({
        status: mode === 'revisi' ? (baris.status_berlaku?.status ?? '') : '',
        pengukuran_versi_id: baris.versi_pengukuran?.id ?? '',
        versi: baris.versi,
        alasan: '',
    });

    // Konflik 409 dan penolakan field dikirim server dengan key di luar data form.
    // Sebagian field (mis. status_capaian) dapat tiba sebagai array pesan validasi;
    // render hanya menerima teks, jadi normalisasi dilakukan sekali di sini.
    const galat = form.errors as Record<string, string | string[] | undefined>;
    const teksGalat = (nilai: string | string[] | undefined): string | undefined =>
        Array.isArray(nilai) ? nilai.join(' ') : nilai;
    const konflik = teksGalat(galat.versi);
    const galatPengukuranVersi = teksGalat(galat.pengukuran_versi_id);
    const galatStatus = teksGalat(galat.status);
    const galatAlasan = teksGalat(galat.alasan);
    const statusError = teksGalat(galat.status_capaian) ?? galatStatus;
    const versiTidakTersedia = baris.versi_pengukuran === null;
    const terkunci = form.processing || Boolean(konflik) || perluMuatUlang || Boolean(recovery.recovery);
    const judul = mode === 'revisi' ? 'Revisi status capaian' : 'Tetapkan status capaian';
    const muatUlang = () => router.reload({ onSuccess: () => onClose() });

    const simpan = (event: FormEvent) => {
        event.preventDefault();
        if (terkunci || form.data.status === '' || versiTidakTersedia) return;
        setPesan('');
        form.post(`/verifikasi/${baris.id}/status-capaian`, {
            preserveScroll: true,
            onSuccess: onClose,
            onCancel: () => setPesan('Permintaan dibatalkan. Hasil penetapan belum diketahui; muat ulang sebelum mencoba lagi.'),
            onNetworkError: () => {
                setPesan('Koneksi terputus. Hasil penetapan belum diketahui; muat ulang sebelum mencoba lagi.');
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/verifikasi/${baris.id}/status-capaian`, mutation: true })) return false;
                if (response.status === 409) {
                    setPerluMuatUlang(true);
                    setPesan('Data telah berubah. Muat ulang halaman sebelum menetapkan ulang.');
                    return false;
                }
                setPesan(response.status === 403
                    ? 'Izin menetapkan status capaian tidak tersedia atau telah dicabut.'
                    : response.status === 429
                        ? 'Terlalu banyak permintaan. Muat ulang halaman sebelum mencoba lagi.'
                        : 'Hasil penetapan belum dapat dipastikan. Muat ulang halaman sebelum mencoba lagi.');
                return false;
            },
        });
    };

    return (
        <Modal
            isOpen
            onClose={() => { if (!form.processing) onClose(); }}
            size="lg"
            title={judul}
            description={`${baris.indikator.kode ?? 'Indikator'} · ${baris.indikator.nama ?? 'Tanpa nama'} · ${baris.periode.nama ?? 'Periode'} ${baris.tahun}`}
            footer={(
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>Batal</Button>
                    <Button type="submit" form={formId} isLoading={form.processing} disabled={terkunci || form.data.status === '' || versiTidakTersedia}>
                        Simpan
                    </Button>
                </div>
            )}
        >
            <form id={formId} onSubmit={simpan} className="space-y-4" aria-busy={form.processing}>
                {recovery.recovery && <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />}

                {pesan && (
                    <div role="alert" className="space-y-2 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
                        <p>{pesan}</p>
                        {perluMuatUlang && <Button type="button" size="sm" variant="outline" onClick={muatUlang}>Muat ulang halaman</Button>}
                    </div>
                )}
                {konflik && (
                    <div role="alert" className="space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-ink">
                        <p className="font-medium">{konflik}</p>
                        <Button type="button" size="sm" variant="outline" onClick={muatUlang}>Muat ulang halaman</Button>
                    </div>
                )}
                {galatPengukuranVersi && <p role="alert" className="text-sm text-danger">{galatPengukuranVersi}</p>}
                {statusError && !konflik && <p role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">{statusError}</p>}

                <RincianAngka baris={baris}>
                    {mode === 'revisi' && baris.status_berlaku && (
                        <div className="col-span-2 min-w-0 border-t border-border pt-2">
                            <dt className="text-xs font-medium text-muted">Status berlaku</dt>
                            <dd className="mt-1 flex flex-wrap items-center gap-2 text-xs text-ink">
                                <StatusCapaianBadge kode={baris.status_berlaku.status} size="sm" />
                                <span className="text-muted">
                                    {baris.status_berlaku.penetap ?? 'Penetap tidak tercatat'} · {formatTanggal(baris.status_berlaku.ditetapkan_pada, { withTime: true })}
                                </span>
                            </dd>
                        </div>
                    )}
                </RincianAngka>

                {versiTidakTersedia && (
                    <p role="alert" className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-ink">
                        Versi pengukuran yang disahkan belum tersedia, sehingga status belum dapat ditetapkan.
                    </p>
                )}

                <fieldset disabled={form.processing || Boolean(konflik) || perluMuatUlang} aria-describedby={galatStatus ? statusErrorId : undefined}>
                    <legend className="text-sm font-semibold text-ink">
                        Status capaian <span className="text-danger" aria-hidden="true">*</span>
                    </legend>
                    <p className="mt-0.5 text-xs text-muted">
                        {mode === 'revisi'
                            ? 'Status baru menggantikan status berlaku; riwayat versi sebelumnya tetap tersimpan.'
                            : 'Status berlaku untuk versi pengukuran ini dan tidak mengubah versi berikutnya.'}
                    </p>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                        {(['tercapai', 'belum_tercapai'] as const).map((nilai) => (
                            <label
                                key={nilai}
                                className={`flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2.5 text-sm transition-colors focus-within:ring-2 focus-within:ring-primary/25 ${form.data.status === nilai ? 'border-primary bg-primary/5 text-ink' : 'border-border bg-surface text-ink hover:bg-soft'}`}
                            >
                                <input
                                    type="radio"
                                    name={`status-${formId}`}
                                    value={nilai}
                                    checked={form.data.status === nilai}
                                    onChange={() => form.setData('status', nilai)}
                                    aria-invalid={Boolean(galatStatus)}
                                    aria-required="true"
                                    className="mt-0.5 h-4 w-4 accent-primary"
                                />
                                <span>
                                    <span className="block font-semibold">{labelStatus[nilai]}</span>
                                    <span className="block text-xs font-normal text-muted">
                                        {nilai === 'tercapai' ? 'Status capaian dinyatakan tercapai.' : 'Status capaian dinyatakan belum tercapai.'}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {galatStatus && <p id={statusErrorId} role="alert" className="mt-1.5 text-xs font-medium text-danger">{galatStatus}</p>}
                </fieldset>

                <Textarea
                    label="Alasan (opsional)"
                    name="alasan"
                    rows={3}
                    maxLength={1000}
                    value={form.data.alasan}
                    error={galatAlasan}
                    onChange={(event) => form.setData('alasan', event.target.value)}
                    helperText="Alasan tidak wajib diisi. Penetapan tetap dicatat pada audit log beserta pengguna, waktu, dan nilai sebelum/sesudah."
                />
            </form>
        </Modal>
    );
}
