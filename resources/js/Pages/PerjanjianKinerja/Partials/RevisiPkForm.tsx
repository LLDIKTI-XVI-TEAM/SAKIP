import { useEffect, useRef, useState } from 'react';
import type { FormDataKeys } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import { Select } from '@/Components/Select';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { RevisiPk, RevisiPkFormData, StorageSettings } from '@/types/perjanjian-kinerja';

export const ID_NOMOR_SURAT = 'revisi-pk-nomor-surat';

/** Galat yang tampil di modal alasan; galat lain menutup modal agar panel dengan props segar dapat diperiksa. */
const GALAT_MODAL = ['alasan', 'konfirmasi_pengukuran_sebelumnya'];
const galatField = (key: string) => ['nomor_surat', 'tanggal_surat', 'lampiran'].includes(key) || key.startsWith('target.') || key.startsWith('lampiran.');

/**
 * Meniru `TargetTahunanDecimal::normalize` tanpa `Number`, yang kehilangan presisi di atas sekitar 15 digit.
 * `null` berarti tak terbaca. Hasilnya hanya untuk tampilan; server tetap memutuskan entri yang berubah.
 */
function normalisasi(nilai: string): string | null {
    const teks = nilai.trim();
    if (!/^[0-9]+([.,][0-9]+)?$/.test(teks)) return null;
    const [bulat, pecahan = ''] = teks.replace(',', '.').split('.');
    const utuh = bulat.replace(/^0+/, '') || '0';
    const desimal = pecahan.replace(/0+$/, '');
    return desimal ? `${utuh}.${desimal}` : utuh;
}

interface RevisiPkFormProps {
    pkId: string;
    nomorVersi: number;
    revisi: RevisiPk;
    storageSettings?: StorageSettings;
    onClose: () => void;
}

/**
 * Isi panel revisi PK. Baris target dikirim apa adanya; server membuang entri yang sama dengan target berlaku,
 * menetapkan periode mulai, dan memeriksa kedua token di bawah lock.
 */
export function RevisiPkForm({ pkId, nomorVersi, revisi, storageSettings, onClose }: RevisiPkFormProps) {
    const recovery = useAuthRecovery();
    const formatNilai = useFormatNilai();
    const form = useForm<RevisiPkFormData>({ nomor_surat: '', tanggal_surat: '', alasan: '', target: [], lampiran: [], konfirmasi_pengukuran_sebelumnya: false });
    const [pilihan, setPilihan] = useState('');
    const [modalTerbuka, setModalTerbuka] = useState(false);
    const [tidakPasti, setTidakPasti] = useState(false);
    const kirimBerjalan = useRef(false);
    const fokusAlert = useRef(false);
    const fokusBaris = useRef<string | null>(null);
    const judulAlert = useRef<HTMLHeadingElement>(null);
    const errors = form.errors as Record<string, string | undefined>;
    const dasar = new Map(revisi.target.map((item) => [item.indikator_id, item]));
    const periode = revisi.periode_mulai?.nama;
    // Dipakai bersama oleh deskripsi modal, peringatan dampak, dan daftar konfirmasi agar ketiganya selalu sepakat.
    const berubah = form.data.target.filter((baris) => {
        const nilai = normalisasi(baris.nilai);
        return nilai === null || nilai !== normalisasi(dasar.get(baris.indikator_id)?.nilai ?? '');
    });
    const pengukuran = berubah.flatMap((baris) => (revisi.pengukuran_belum_disahkan[baris.indikator_id] ?? [])
        .map((item) => ({ ...item, kode: dasar.get(baris.indikator_id)?.kode ?? '' })));
    const galatUmum = Object.entries(errors).filter(([key]) => !GALAT_MODAL.includes(key) && !galatField(key));
    const adaGalatField = Object.keys(errors).some(galatField);
    const maksMb = storageSettings?.ukuran_maks_kb ? Math.round(storageSettings.ukuran_maks_kb / 1024) : 10;
    const format = storageSettings?.format_diizinkan ?? 'pdf,docx,xlsx,jpg,jpeg,png';
    const path = `/perjanjian-kinerja/${pkId}/versi`;

    useEffect(() => { document.getElementById(ID_NOMOR_SURAT)?.focus(); }, []);
    // Dijalankan setelah modal benar-benar tertutup, karena dialog.close() mengembalikan fokus ke pemicunya.
    useEffect(() => {
        if (!modalTerbuka && fokusAlert.current) {
            fokusAlert.current = false;
            judulAlert.current?.focus();
        }
    }, [modalTerbuka]);
    // Tombol Tambah nonaktif setelah dipakai; fokus dipindah ke isian baris baru agar pengguna keyboard tidak kehilangan posisi.
    useEffect(() => {
        if (fokusBaris.current) {
            document.getElementById(`revisi-pk-target-${fokusBaris.current}`)?.focus();
            fokusBaris.current = null;
        }
    }, [form.data.target.length]);

    const tutupModalKePanel = () => {
        fokusAlert.current = true;
        setModalTerbuka(false);
    };

    // Galat `target.N.*` berindeks posisi baris; setelah baris bergeser galat itu tidak lagi milik baris yang sama.
    const batalkan = (index: number) => {
        form.setData('target', form.data.target.filter((_, posisi) => posisi !== index));
        // clearErrors() tanpa argumen menghapus semua galat, jadi hanya dipanggil bila ada galat baris.
        const kunci = Object.keys(errors).filter((key) => key.startsWith('target.'));
        if (kunci.length > 0) form.clearErrors(...(kunci as FormDataKeys<RevisiPkFormData>[]));
        (document.getElementById('revisi-pk-indikator') ?? document.getElementById('revisi-pk-target'))?.focus();
    };

    const tambah = () => {
        const item = dasar.get(pilihan);
        if (!item) return;
        fokusBaris.current = item.indikator_id;
        form.setData('target', [...form.data.target, { indikator_id: item.indikator_id, nilai: item.nilai }]);
        setPilihan('');
    };

    const simpan = () => {
        if (form.processing || kirimBerjalan.current) return;
        kirimBerjalan.current = true;
        form.transform(({ target, konfirmasi_pengukuran_sebelumnya, lampiran, ...isian }) => ({
            ...isian,
            expected_nomor_versi: revisi.expected_nomor_versi,
            expected_dasar: revisi.expected_dasar,
            // Tanpa saringan isi: indeks galat `target.N.nilai` dari server harus sama dengan indeks baris di layar.
            ...(target.length > 0 ? { target } : {}),
            ...(pengukuran.length > 0 ? { konfirmasi_pengukuran_sebelumnya } : {}),
            lampiran: lampiran.map((item) => ({ file: item.file, keterangan: item.keterangan ?? '' })),
        }));
        form.post(path, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (galat) => {
                if (Object.keys(galat).some((key) => !GALAT_MODAL.includes(key))) tutupModalKePanel();
            },
            // Hasil ambigu tidak diulang otomatis; token versi akan menolak simpan ganda setelah halaman dimuat ulang.
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) {
                    setTidakPasti(true);
                    tutupModalKePanel();
                }
                return false;
            },
            onNetworkError: () => {
                setTidakPasti(true);
                tutupModalKePanel();
                return false;
            },
            onFinish: () => { kirimBerjalan.current = false; },
        });
    };

    return (
        <div className="space-y-5 rounded-xl border border-primary/30 p-4 sm:p-5">
            {(galatUmum.length > 0 || adaGalatField || tidakPasti) && (
                <div role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-ink">
                    <h4 ref={judulAlert} tabIndex={-1} className="mb-1 font-semibold">Revisi belum tersimpan</h4>
                    {tidakPasti && <p>Hasil penyimpanan belum dapat dipastikan. Muat ulang halaman PK sebelum mencoba lagi.</p>}
                    {galatUmum.map(([key, pesan]) => <p key={key} className="break-words">{pesan}</p>)}
                    {adaGalatField && <p>Periksa isian yang ditandai.</p>}
                </div>
            )}

            <div>
                <h3 className="text-base font-semibold text-ink">Revisi baru (versi {nomorVersi})</h3>
                {periode && <p className="mt-0.5 text-sm text-muted">Berlaku mulai {periode} (perkiraan)</p>}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Input id={ID_NOMOR_SURAT} label="Nomor surat Eselon I" required maxLength={255} disabled={form.processing}
                    value={form.data.nomor_surat} error={errors.nomor_surat} onChange={(event) => form.setData('nomor_surat', event.target.value)} />
                <Input id="revisi-pk-tanggal-surat" type="date" label="Tanggal surat" required disabled={form.processing}
                    value={form.data.tanggal_surat} error={errors.tanggal_surat} onChange={(event) => form.setData('tanggal_surat', event.target.value)} />
            </div>

            <section aria-labelledby="revisi-pk-target" className="space-y-3">
                <h4 id="revisi-pk-target" tabIndex={-1} className="text-sm font-semibold text-ink">Target yang berubah (opsional)</h4>
                {!revisi.dapat_ubah_target ? <p className="text-sm text-muted">{revisi.alasan_tidak_dapat_ubah_target}</p> : (
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <Select id="revisi-pk-indikator" label="Pilih indikator" value={pilihan} disabled={form.processing}
                            onChange={(event) => setPilihan(event.target.value)}
                            options={[{ value: '', label: 'Pilih salah satu' }, ...revisi.target
                                .filter((item) => !form.data.target.some((baris) => baris.indikator_id === item.indikator_id))
                                .map((item) => ({ value: item.indikator_id, label: `${item.kode} ${item.nama}` }))]} />
                        <Button type="button" variant="outline" className="h-11 shrink-0 sm:h-[42px]" disabled={pilihan === '' || form.processing} onClick={tambah}>Tambah</Button>
                    </div>
                )}
                {/* Target dapat terkunci setelah panel dibuka (props dari respons penolakan); baris yang sudah ada tetap
                    tampil agar dapat dibatalkan, karena server menolak simpan selama nilainya berbeda dari target berlaku. */}
                {form.data.target.length > 0 && (
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {form.data.target.map((baris, index) => {
                            const item = dasar.get(baris.indikator_id);
                            const kode = item?.kode ?? 'Indikator';
                            return (
                                <li key={baris.indikator_id} className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-start">
                                    <div className="min-w-0 text-sm">
                                        <p className="break-words text-ink"><span className="font-mono font-semibold">{kode}</span> {item?.nama}</p>
                                        {item && <p className="mt-0.5 text-xs text-muted">Target berlaku {formatNilai(item.nilai, item.desimal_tampilan)} {item.satuan}</p>}
                                    </div>
                                    <Input id={`revisi-pk-target-${baris.indikator_id}`} label="Target baru" inputMode="decimal" disabled={form.processing} value={baris.nilai}
                                        error={errors[`target.${index}.nilai`] ?? errors[`target.${index}.indikator_id`]}
                                        onChange={(event) => form.setData('target', form.data.target.map((lain, posisi) => posisi === index ? { ...lain, nilai: event.target.value } : lain))} />
                                    <Button type="button" variant="outline" className="h-11 w-11 p-0 sm:mt-7 sm:h-9 sm:w-9" aria-label={`Batalkan perubahan ${kode}`}
                                        disabled={form.processing} onClick={() => batalkan(index)}>
                                        <X className="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                </li>
                            );
                        })}
                    </ul>
                )}
                {revisi.dapat_ubah_target && <p className="text-xs text-muted">Indikator lain tetap memakai target berlaku.</p>}
            </section>

            <LampiranDraftFields
                items={form.data.lampiran}
                errors={errors}
                onChange={(update) => form.setData('lampiran', update(form.data.lampiran))}
                title="Lampiran file"
                modes={['file']}
                modeDescriptions={{ file: `Format ${format}; maksimal ${maksMb} MB.`, tautan: '', teks: '' }}
                placeholders={{ tautan: '', teks: '' }}
                emptyText="Unggah dokumen PK bertanda tangan."
                accept={format.split(',').map((item) => `.${item.trim()}`).join(',')}
                disabled={form.processing}
                renderItemFields={(index) => (
                    <Input label="Keterangan (opsional)" maxLength={1000} disabled={form.processing} placeholder="Contoh: PK akhir bertanda tangan"
                        value={form.data.lampiran[index]?.keterangan ?? ''} error={errors[`lampiran.${index}.keterangan`]}
                        onChange={(event) => form.setData('lampiran', form.data.lampiran.map((item, posisi) => posisi === index ? { ...item, keterangan: event.target.value } : item))} />
                )}
            />

            {berubah.length > 0 && periode && (
                <div className="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-ink">
                    <p className="mb-1 font-semibold">Dampak mulai {periode}</p>
                    <ul className="list-disc space-y-1 pl-5">
                        <li>Periode sebelumnya tetap memakai target lama.</li>
                        <li>Draf pengukuran {periode} di versi lama perlu dipindahkan.</li>
                        <li>Pengajuan {periode} menunggu Rencana Aksi disahkan ulang.</li>
                    </ul>
                </div>
            )}

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" size="sm" className="h-11 sm:h-9" disabled={form.processing} onClick={onClose}>Batal</Button>
                <Button type="button" size="sm" className="h-11 sm:h-9" disabled={form.processing || tidakPasti} onClick={() => setModalTerbuka(true)}>Simpan revisi</Button>
            </div>

            <AuditReasonModal
                open={modalTerbuka}
                title={`Simpan revisi PK versi ${nomorVersi}`}
                description={berubah.length > 0 ? `${berubah.length} target berubah${periode ? ` · berlaku mulai ${periode}` : ''}` : 'Revisi dokumen saja'}
                reason={form.data.alasan}
                error={errors.alasan}
                busy={form.processing}
                submitDisabled={form.data.alasan.trim() === '' || Boolean(recovery.recovery)
                    || (pengukuran.length > 0 && !form.data.konfirmasi_pengukuran_sebelumnya)}
                reasonRequired
                confirmLabel="Simpan revisi"
                onReasonChange={(value) => form.setData('alasan', value)}
                onClose={() => { if (!form.processing && !kirimBerjalan.current) setModalTerbuka(false); }}
                onConfirm={simpan}
                notice={(recovery.recovery || pengukuran.length > 0 || errors.konfirmasi_pengukuran_sebelumnya) && (
                    <div className="mb-4 space-y-4">
                        <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                        {pengukuran.length > 0 && (
                            <div className="space-y-2 rounded-lg border border-border p-3 text-sm">
                                <p id="revisi-pk-pengukuran" className="font-semibold">Pengukuran belum disahkan sebelum periode mulai</p>
                                <ul aria-labelledby="revisi-pk-pengukuran" className="space-y-2">
                                    {pengukuran.map((item) => (
                                        <li key={`${item.kode}-${item.periode_id}`} className="flex flex-wrap items-center gap-2">
                                            <span>{item.kode}: {item.periode_nama}</span>
                                            <span aria-hidden="true">·</span><Badge status={item.status_alur} size="sm" />
                                            <span aria-hidden="true">·</span><span>Versi {item.nomor_versi}</span>
                                        </li>
                                    ))}
                                </ul>
                                <label className="flex items-start gap-2">
                                    <input type="checkbox" className="mt-0.5 h-4 w-4 shrink-0 accent-primary" disabled={form.processing}
                                        checked={form.data.konfirmasi_pengukuran_sebelumnya}
                                        onChange={(event) => form.setData('konfirmasi_pengukuran_sebelumnya', event.target.checked)} />
                                    Saya mengerti, pengukuran ini tetap dinilai dengan versi lama.
                                </label>
                            </div>
                        )}
                        {errors.konfirmasi_pengukuran_sebelumnya && <p role="alert" className="text-xs font-medium text-danger">{errors.konfirmasi_pengukuran_sebelumnya}</p>}
                    </div>
                )}
            />
        </div>
    );
}
