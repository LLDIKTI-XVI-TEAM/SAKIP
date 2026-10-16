import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import type { HttpExceptionResponse } from '@inertiajs/core';
import { useCallback, useRef, useState, type FormEvent } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Save, Send } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Switch } from '@/Components/Switch';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import type { LampiranDraft } from '@/types/lampiran';
import { statusPerhitungan, type Pengukuran } from './types';
import EvidenceList from './EvidenceList';
import { useFormatNilai } from './formatNilai';
import { useLabelUnit } from '@/hooks/useLabelUnit';
import CalculationPreview from './CalculationPreview';
import { arahLabel, peranKomponenLabel, tipePerhitunganLabel } from '@/lib/indikator';

interface PengukuranEditProps { pengukuran: Pengukuran }

const narasi = [['progres_kegiatan', 'Progres kegiatan'], ['kendala_masalah', 'Kendala/masalah'], ['strategi_tindak_lanjut', 'Tindak lanjut/strategi penyelesaian']] as const;

export default function PengukuranEdit(props: PengukuranEditProps) {
    return <PengukuranForm key={props.pengukuran.id} {...props} />;
}

function PengukuranForm({ pengukuran }: PengukuranEditProps) {
    const formatNilai = useFormatNilai();
    const labelUnit = useLabelUnit();
    const { can } = pengukuran;
    const indikator = pengukuran.penugasan_indikator.indikator_kinerja;
    const historical = pengukuran.sumber_nilai === 'historis';
    const manual = pengukuran.sumber_nilai !== 'komponen';
    const errorSummary = useRef<HTMLUListElement>(null);
    const [requestError, setRequestError] = useState('');
    const recovery = useAuthRecovery();
    const { data, setData, transform, post, processing, errors } = useForm({
        versi: pengukuran.versi,
        nilai: pengukuran.nilai === null ? '' : String(pengukuran.nilai),
        komponen: pengukuran.komponen.map((item) => ({ komponen_id: item.komponen_id, nilai: item.nilai === null ? '' : String(item.nilai) })),
        catatan: pengukuran.catatan || '',
        // Penanda hanya berasal dari BDD tersimpan; status numerik selalu dihitung ulang server.
        status_perhitungan: (pengukuran.status_perhitungan === 'belum_dapat_diukur' ? 'belum_dapat_diukur' : null) as 'belum_dapat_diukur' | null,
        progres_kegiatan: pengukuran.progres_kegiatan ?? '',
        kendala_masalah: pengukuran.kendala_masalah ?? '',
        strategi_tindak_lanjut: pengukuran.strategi_tindak_lanjut ?? '',
        tindak_lanjut_rekomendasi: pengukuran.tindak_lanjut_rekomendasi ?? '',
        alasan_tidak_dapat_dihitung: pengukuran.alasan_tidak_dapat_dihitung || '',
        jenis_berkas_id: '',
        menggantikan_id: '',
        alasan_koreksi: '',
        lampiran: [] as LampiranDraft[],
        action: 'draft' as 'draft' | 'ajukan',
    });
    const { handleHttpException } = recovery;
    const handlePreviewRecovery = useCallback((response: HttpExceptionResponse) => {
        handleHttpException(response, {
            effectiveMethod: 'post',
            path: `/pengukuran/${pengukuran.id}/pratinjau`,
            mutation: false,
        });
    }, [handleHttpException, pengukuran.id]);
    const fieldErrors: Record<string, string | undefined> = errors;
    const disabled = !can.update || historical || processing;
    const unmeasurable = data.status_perhitungan !== null;
    const valueDisabled = disabled || unmeasurable;
    const rekomendasi = pengukuran.rekomendasi_sebelumnya;
    const requirement = pengukuran.persyaratan_bukti.find((item) => item.id === data.jenis_berkas_id);
    const modes = (['file', 'tautan', 'teks'] as const).filter((mode) => (!requirement || requirement[`izinkan_${mode}`]) && (mode !== 'file' || pengukuran.unggahan_aktif));
    const bukti = data.lampiran[0];
    const activeMode = bukti && modes.includes(bukti.mode) ? bukti.mode : null;
    const lampiranErrors = { lampiran: fieldErrors.bukti, 'lampiran.0.mode': fieldErrors['bukti.mode'], 'lampiran.0.file': fieldErrors['bukti.file'], 'lampiran.0.tautan': fieldErrors['bukti.tautan'], 'lampiran.0.isi_teks': fieldErrors['bukti.isi_teks'] };
    const lastRejection = pengukuran.riwayats?.find((item) => item.status_ke === 'dikembalikan');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (disabled || recovery.recovery || requestError) return;
        const submitter = (event.nativeEvent as SubmitEvent).submitter;
        const intent = submitter instanceof HTMLButtonElement && submitter.value === 'ajukan' ? 'ajukan' : 'draft';
        if (intent === 'ajukan' && !can.submit) return;
        setRequestError('');
        transform((values) => ({
            versi: values.versi, action: intent, status_perhitungan: values.status_perhitungan,
            ...(manual ? { nilai: values.nilai === '' ? null : values.nilai } : { komponen: values.komponen.map((item) => ({ ...item, nilai: item.nilai === '' ? null : item.nilai })) }),
            catatan: values.catatan, alasan_tidak_dapat_dihitung: values.alasan_tidak_dapat_dihitung,
            progres_kegiatan: values.progres_kegiatan, kendala_masalah: values.kendala_masalah, strategi_tindak_lanjut: values.strategi_tindak_lanjut,
            // Tanpa rekomendasi approved, field diomit (bukan null) agar isi tersimpan tetap utuh.
            ...(rekomendasi ? { tindak_lanjut_rekomendasi: values.tindak_lanjut_rekomendasi } : {}),
            // Rekomendasi yang dilihat; pengajuan ditolak bila sudah diganti sebelum dikirim.
            rekomendasi_sebelumnya_id: rekomendasi?.id ?? null,
            ...(bukti && activeMode && can.uploadEvidence ? { bukti: {
                jenis_berkas_id: values.jenis_berkas_id || null, mode: activeMode,
                ...(values.menggantikan_id ? { menggantikan_id: values.menggantikan_id, alasan_koreksi: values.alasan_koreksi } : {}),
                ...(activeMode === 'file' ? { file: bukti.file } : activeMode === 'tautan' ? { tautan: bukti.tautan } : { isi_teks: bukti.isi_teks }),
            } } : {}),
        }));
        post(`/pengukuran/${pengukuran.id}`, {
            preserveScroll: true,
            onError: () => requestAnimationFrame(() => errorSummary.current?.focus()),
            onCancel: () => { setRequestError('Permintaan dibatalkan. Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => { setRequestError('Koneksi terputus. Hasil penyimpanan belum diketahui; periksa status pengukuran sebelum mencoba kembali.'); return false; },
            onHttpException: (response) => { if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: `/pengukuran/${pengukuran.id}`, mutation: true })) return false; setRequestError(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); return false; },
        });
    };

    return <AuthenticatedLayout title="Pengisian Pengukuran Kinerja" breadcrumbs={[{ label: 'Pengukuran Kinerja', href: '/pengukuran' }, { label: indikator.kode }]}>
        <Head title={`Pengisian ${indikator.kode}`} />
        <div className="mx-auto max-w-5xl space-y-5">
            <div>
                <Link href="/pengukuran" className="inline-flex items-center gap-2 rounded text-sm text-primary focus:outline-none focus:ring-2 focus:ring-primary"><ArrowLeft className="h-4 w-4" />Kembali ke daftar pengukuran</Link>
            </div>
            {lastRejection && pengukuran.status === 'dikembalikan' && <div className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning-dark"><h2 className="font-semibold">Catatan perbaikan</h2><p className="mt-1 whitespace-pre-wrap">{lastRejection.catatan}</p><p className="mt-2 text-xs">Dikembalikan oleh {lastRejection.user?.nama || 'Verifikator'}</p></div>}
            {!can.update && <p className="rounded-lg border border-info/30 bg-info/10 p-4 text-sm text-info-dark">Formulir hanya dapat dibaca sesuai status dan izin akses Anda.</p>}
            {historical && <p className="rounded-lg border border-info/30 bg-info/10 p-4 text-sm text-info-dark">Nilai historis hanya dapat dikoreksi melalui alur backfill resmi.</p>}
            <Card>
                <CardHeader className="items-start gap-4"><CardTitle className="min-w-0 flex-1 break-words">{indikator.kode} · {indikator.nama}</CardTitle><Badge status={pengukuran.status} /></CardHeader>
                <CardContent className="space-y-3">
                    {indikator.definisi_operasional && <p className="text-sm text-muted">{indikator.definisi_operasional}</p>}
                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt className="text-muted">{labelUnit} penanggung jawab</dt><dd className="mt-1 font-medium">{pengukuran.penugasan_indikator.unit_kerja.nama}</dd></div>
                        <div><dt className="text-muted">PIC</dt><dd className="mt-1 font-medium">{pengukuran.penugasan_indikator.pic?.nama ?? 'Belum ditetapkan'}</dd></div>
                        <div><dt className="text-muted">Periode</dt><dd className="mt-1 font-medium">{`${pengukuran.periode_jadwal.nama_periode} ${pengukuran.periode_jadwal.tahun}`}</dd></div>
                        <div><dt className="text-muted">Target</dt><dd className="mt-1 font-medium">{pengukuran.target === null ? 'Belum tersedia' : `${formatNilai(pengukuran.target, indikator.desimal_tampilan)} ${indikator.satuan}`}</dd></div>
                        <div><dt className="text-muted">Cara hitung</dt><dd className="mt-1 font-medium">{tipePerhitunganLabel[indikator.tipe_perhitungan]}</dd></div>
                        <div><dt className="text-muted">Arah kinerja</dt><dd className="mt-1 font-medium">{arahLabel[indikator.arah]}</dd></div>
                    </dl>
                </CardContent>
            </Card>
            {!pengukuran.prasyarat.siap && <div className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning-dark"><h2 className="font-semibold">Prasyarat pengajuan belum lengkap</h2><ul className="mt-2 list-disc space-y-1 pl-5">{pengukuran.prasyarat.alasan.map((reason) => <li key={reason}>{reason}</li>)}</ul><p className="mt-2">Simpan draf untuk memperbarui hasil dan pemenuhan sebelum mengajukan.</p></div>}
            <form onSubmit={submit} className="space-y-6" aria-busy={processing}>
                {Object.keys(errors).length > 0 && <ul ref={errorSummary} tabIndex={-1} id="measurement-errors" role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">{Object.entries(errors).map(([field, message]) => <li key={field}>{message}</li>)}</ul>}
                <AuthRecoveryNotice recovery={recovery.recovery} pending={processing} />
                {requestError && !recovery.recovery && <p role="alert" className="text-sm text-danger">{requestError}</p>}
                <Card>
                    <CardHeader><CardTitle>Nilai pengukuran</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        {/* Pilihan dan kembali ke mode nilai sama-sama mengosongkan input dalam satu pembaruan agar angka lama tidak terkirim diam-diam. */}
                        {!pengukuran.periode_jadwal.is_nilai_akhir && <div className="flex items-center gap-3">
                            <Switch id="belum-dapat-diukur" checked={unmeasurable} disabled={disabled} aria-invalid={Boolean(errors.status_perhitungan)} aria-describedby={errors.status_perhitungan ? 'measurement-errors' : undefined} onChange={(checked) => setData((values) => ({ ...values, status_perhitungan: checked ? 'belum_dapat_diukur' : null, nilai: '', komponen: values.komponen.map((item) => ({ ...item, nilai: '' })), alasan_tidak_dapat_dihitung: '' }))} />
                            <label htmlFor="belum-dapat-diukur" className="cursor-pointer text-sm font-medium text-ink">Belum dapat diukur</label>
                        </div>}
                        {manual ? <Input name="nilai" label={`Nilai realisasi (${indikator.satuan})`} type="number" step="any" value={data.nilai} onChange={(event) => setData('nilai', event.target.value)} disabled={valueDisabled} error={errors.nilai} aria-invalid={Boolean(errors.nilai)} aria-describedby={errors.nilai ? 'measurement-errors' : undefined} /> : <div className="space-y-4">
                            <p className="text-sm text-muted">Isi nilai setiap komponen untuk periode ini. Hasilnya baru tersimpan setelah menekan Simpan Draft.</p>
                            {pengukuran.komponen.map((item, index) => <Input key={item.komponen_id} name={`komponen-${item.komponen_id}`} label={`${item.kode} · ${item.label}`} type="number" step="any" value={data.komponen[index]?.nilai ?? ''} onChange={(event) => setData('komponen', data.komponen.map((value, position) => position === index ? { ...value, nilai: event.target.value } : value))} disabled={valueDisabled} helperText={`${peranKomponenLabel[item.peran] ?? item.peran}${item.bobot === null ? '' : ` · Bobot ${item.bobot}`}`} error={fieldErrors[`komponen.${index}.nilai`]} aria-invalid={Boolean(fieldErrors[`komponen.${index}.nilai`])} aria-describedby={fieldErrors[`komponen.${index}.nilai`] ? 'measurement-errors' : undefined} />)}
                        </div>}
                        {/* Pratinjau dilepas saat BDD: cleanup membatalkan request dan respons basi diabaikan. */}
                        {!manual && can.update && !historical && !unmeasurable && <CalculationPreview paused={Boolean(recovery.recovery)} onRecovery={handlePreviewRecovery} id={pengukuran.id} komponen={data.komponen} satuan={indikator.satuan} desimalTampilan={indikator.desimal_tampilan} />}
                        <p className="break-words text-sm text-muted">Hasil terakhir tersimpan: <span className="font-semibold text-ink">{pengukuran.nilai === null ? statusPerhitungan[pengukuran.status_perhitungan] : `${formatNilai(pengukuran.nilai, indikator.desimal_tampilan)} ${indikator.satuan}`}</span></p>
                        {!manual && <Textarea name="alasan_tidak_dapat_dihitung" label="Alasan bila hasil tidak dapat dihitung" value={data.alasan_tidak_dapat_dihitung} onChange={(event) => setData('alasan_tidak_dapat_dihitung', event.target.value)} disabled={valueDisabled} helperText="Isi alasan jika penyebut faktual bernilai nol. Komponen kosong tetap harus dilengkapi sebelum pengajuan." error={errors.alasan_tidak_dapat_dihitung} aria-invalid={Boolean(errors.alasan_tidak_dapat_dihitung)} aria-describedby={errors.alasan_tidak_dapat_dihitung ? 'measurement-errors' : undefined} />}
                        <Textarea name="catatan" label="Catatan" value={data.catatan} onChange={(event) => setData('catatan', event.target.value)} disabled={disabled} error={errors.catatan} aria-invalid={Boolean(errors.catatan)} aria-describedby={errors.catatan ? 'measurement-errors' : undefined} />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Narasi</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        {narasi.map(([field, label]) => <Textarea key={field} name={field} label={label} value={data[field]} onChange={(event) => setData(field, event.target.value)} disabled={disabled} error={errors[field]} aria-invalid={Boolean(errors[field])} aria-describedby={errors[field] ? 'measurement-errors' : undefined} />)}
                        {rekomendasi && <div className="space-y-2">
                            <p id="rekomendasi-sebelumnya" className="rounded-lg border border-border bg-soft p-3 text-sm"><span className="font-medium">Rekomendasi:</span> {rekomendasi.isi}</p>
                            <Textarea name="tindak_lanjut_rekomendasi" label="Tindak lanjut rekomendasi" value={data.tindak_lanjut_rekomendasi} onChange={(event) => setData('tindak_lanjut_rekomendasi', event.target.value)} disabled={disabled} error={errors.tindak_lanjut_rekomendasi} aria-invalid={Boolean(errors.tindak_lanjut_rekomendasi)} aria-describedby={errors.tindak_lanjut_rekomendasi ? 'rekomendasi-sebelumnya measurement-errors' : 'rekomendasi-sebelumnya'} />
                        </div>}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Bukti dukung</CardTitle></CardHeader>
                    <CardContent className="space-y-5">
                        <EvidenceList pengukuran={pengukuran} />
                        {/* Menghapus lampiran ikut mengosongkan persyaratan dan koreksi agar tidak terkirim tanpa bukti. */}
                        {can.uploadEvidence && !historical && <LampiranDraftFields items={data.lampiran} errors={lampiranErrors} disabled={disabled} maxItems={1} modes={modes}
                            onChange={(update) => setData((values) => { const lampiran = update(values.lampiran); return lampiran.length > 0 ? { ...values, lampiran } : { ...values, lampiran, jenis_berkas_id: '', menggantikan_id: '', alasan_koreksi: '' }; })}
                            title="Bukti baru" description="Satu bukti ditambahkan setiap penyimpanan. Simpan kembali untuk menambahkan mode atau persyaratan lainnya." emptyText="Belum ada bukti baru yang akan disimpan."
                            modeDescriptions={{ file: requirement ? `Format: ${requirement.format_diizinkan}. Maksimum ${requirement.ukuran_maks_kb} KB.` : 'Unggah berkas bukti dukung.', tautan: 'Gunakan alamat http atau https.', teks: 'Tuliskan bukti dukung dalam bentuk teks.' }}
                            placeholders={{ tautan: 'https://', teks: '' }} accept={requirement?.format_diizinkan.split(',').map((format) => `.${format.trim()}`).join(',')}
                            renderItemFields={() => <>
                                <Select id="jenis-bukti" label="Persyaratan yang dipenuhi" value={data.jenis_berkas_id} disabled={disabled} error={fieldErrors['bukti.jenis_berkas_id']} onChange={(event) => {
                                    const selected = pengukuran.persyaratan_bukti.find((item) => item.id === event.target.value);
                                    const available = (['file', 'tautan', 'teks'] as const).filter((mode) => (!selected || selected[`izinkan_${mode}`]) && (mode !== 'file' || pengukuran.unggahan_aktif));
                                    setData((values) => ({ ...values, jenis_berkas_id: event.target.value, menggantikan_id: '', alasan_koreksi: '',
                                        lampiran: values.lampiran.map((item) => available.includes(item.mode) ? item : { ...item, mode: available[0] ?? item.mode, file: null, tautan: '', isi_teks: '' }) }));
                                }}><option value="">Lampiran tambahan</option>{pengukuran.persyaratan_bukti.map((item) => <option key={item.id} value={item.id}>{item.nama}</option>)}</Select>
                                {pengukuran.bukti_dukungs.some((item) => (item.jenis_berkas_id ?? '') === data.jenis_berkas_id) && <Select id="bukti-pendahulu" label="Bukti yang diganti (opsional)" value={data.menggantikan_id} disabled={disabled} onChange={(event) => setData('menggantikan_id', event.target.value)} helperText="Bukti lama tetap tersimpan pada riwayat. Pengajuan berikutnya menggunakan bukti pengganti." aria-invalid={Boolean(fieldErrors['bukti.menggantikan_id'])} aria-describedby={fieldErrors['bukti.menggantikan_id'] ? 'measurement-errors' : 'bukti-pendahulu-help'}>
                                    <option value="">Tambahkan tanpa mengganti bukti</option>
                                    {pengukuran.bukti_dukungs.filter((item) => (item.jenis_berkas_id ?? '') === data.jenis_berkas_id).map((item, index) => <option key={item.id} value={item.id}>{index + 1}. {item.nama_asli || item.isi_teks?.slice(0, 60) || item.tautan || 'Bukti tersimpan'} ({item.mode})</option>)}
                                </Select>}
                                {data.menggantikan_id && <Textarea name="alasan-koreksi-bukti" label="Alasan koreksi bukti" value={data.alasan_koreksi} disabled={disabled} required onChange={(event) => setData('alasan_koreksi', event.target.value)} error={fieldErrors['bukti.alasan_koreksi']} aria-invalid={Boolean(fieldErrors['bukti.alasan_koreksi'])} aria-describedby={fieldErrors['bukti.alasan_koreksi'] ? 'measurement-errors' : undefined} />}
                                {modes.length === 0 && <p className="text-sm text-warning-dark">Tidak ada mode tersedia. Persyaratan file akan dievaluasi sebagai pengecualian oleh server.</p>}
                            </>} />}
                    </CardContent>
                </Card>
                {can.update && !historical && <div className="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:justify-end">
                    <Button disabled={Boolean(recovery.recovery || requestError)} type="submit" name="action" value="draft" variant="outline" isLoading={processing}><Save className="h-4 w-4" />Simpan Draft</Button>
                    {can.submit && <Button disabled={Boolean(recovery.recovery || requestError)} type="submit" name="action" value="ajukan" isLoading={processing}><Send className="h-4 w-4" />Ajukan ke Tim Perencanaan</Button>}
                </div>}
            </form>
        </div>
    </AuthenticatedLayout>;
}
