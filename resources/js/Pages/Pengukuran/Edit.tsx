import { useRef, useState, type FormEvent } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowLeft, Save, Send } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { statusPerhitungan, type Pengukuran, type BuktiPengukuran } from './types';
import EvidenceList from './EvidenceList';
import { formatNilai } from './formatNilai';
import CalculationPreview from './CalculationPreview';

interface PengukuranEditProps { pengukuran: Pengukuran }

export default function PengukuranEdit(props: PengukuranEditProps) {
    return <PengukuranForm key={props.pengukuran.id} {...props} />;
}

function PengukuranForm({ pengukuran }: PengukuranEditProps) {
    const { can } = pengukuran;
    const indikator = pengukuran.penugasan_indikator.indikator_kinerja;
    const historical = pengukuran.sumber_nilai === 'historis';
    const manual = pengukuran.sumber_nilai !== 'komponen';
    const errorSummary = useRef<HTMLUListElement>(null);
    const [requestError, setRequestError] = useState('');
    const { data, setData, transform, post, processing, errors } = useForm({
        versi: pengukuran.versi,
        nilai: pengukuran.nilai === null ? '' : String(pengukuran.nilai),
        komponen: pengukuran.komponen.map((item) => ({ komponen_id: item.komponen_id, nilai: item.nilai === null ? '' : String(item.nilai) })),
        catatan: pengukuran.catatan || '',
        alasan_tidak_dapat_dihitung: pengukuran.alasan_tidak_dapat_dihitung || '',
        tambah_bukti: false,
        jenis_berkas_id: '',
        menggantikan_id: '',
        alasan_koreksi: '',
        mode: 'tautan' as BuktiPengukuran['mode'],
        file: null as File | null,
        tautan: '',
        isi_teks: '',
        action: 'draft' as 'draft' | 'ajukan',
    });
    const fieldErrors: Record<string, string | undefined> = errors;
    const disabled = !can.update || historical || processing;
    const requirement = pengukuran.persyaratan_bukti.find((item) => item.id === data.jenis_berkas_id);
    const modes = (['file', 'tautan', 'teks'] as const).filter((mode) => (!requirement || requirement[`izinkan_${mode}`]) && (mode !== 'file' || pengukuran.unggahan_aktif));
    const activeMode = modes.includes(data.mode) ? data.mode : (modes[0] ?? null);
    const lastRejection = pengukuran.riwayats?.find((item) => item.status_ke === 'dikembalikan');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (disabled) return;
        const submitter = (event.nativeEvent as SubmitEvent).submitter;
        const intent = submitter instanceof HTMLButtonElement && submitter.value === 'ajukan' ? 'ajukan' : 'draft';
        if (intent === 'ajukan' && !can.submit) return;
        setRequestError('');
        transform((values) => ({
            versi: values.versi, action: intent,
            ...(manual ? { nilai: values.nilai === '' ? null : values.nilai } : { komponen: values.komponen.map((item) => ({ ...item, nilai: item.nilai === '' ? null : item.nilai })) }),
            catatan: values.catatan, alasan_tidak_dapat_dihitung: values.alasan_tidak_dapat_dihitung,
            ...(values.tambah_bukti && can.uploadEvidence && modes.length > 0 ? { bukti: {
                jenis_berkas_id: values.jenis_berkas_id || null, mode: activeMode,
                ...(values.menggantikan_id ? { menggantikan_id: values.menggantikan_id, alasan_koreksi: values.alasan_koreksi } : {}),
                ...(activeMode === 'file' ? { file: values.file } : activeMode === 'tautan' ? { tautan: values.tautan } : { isi_teks: values.isi_teks }),
            } } : {}),
        }));
        post(`/pengukuran/${pengukuran.id}`, {
            preserveScroll: true,
            onError: () => requestAnimationFrame(() => errorSummary.current?.focus()),
            onNetworkError: () => { setRequestError('Koneksi terputus. Hasil penyimpanan belum diketahui; periksa status pengukuran sebelum mencoba kembali.'); return false; },
            onHttpException: () => { setRequestError('Penyimpanan belum dapat dipastikan. Sesi atau izin mungkin berubah. Periksa status pengukuran sebelum mencoba kembali.'); return false; },
        });
    };

    return <AuthenticatedLayout title="Pengisian Pengukuran Kinerja" breadcrumbs={[{ label: 'Pengukuran Kinerja', href: '/pengukuran' }, { label: indikator.kode }]}>
        <Head title={`Pengisian ${indikator.kode}`} />
        <div className="mx-auto max-w-4xl space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Link href="/pengukuran" className="inline-flex items-center gap-2 rounded text-sm text-primary focus:outline-none focus:ring-2 focus:ring-primary"><ArrowLeft className="h-4 w-4" />Kembali ke daftar pengukuran</Link>
                <Badge status={pengukuran.status} />
            </div>
            {lastRejection && pengukuran.status === 'dikembalikan' && <div className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning-dark"><h2 className="font-semibold">Catatan perbaikan</h2><p className="mt-1 whitespace-pre-wrap">{lastRejection.catatan}</p><p className="mt-2 text-xs">Dikembalikan oleh {lastRejection.user?.nama || 'Verifikator'}</p></div>}
            {!can.update && <p className="rounded-lg border border-info/30 bg-info/10 p-4 text-sm text-info-dark">Formulir hanya dapat dibaca sesuai status dan izin akses Anda.</p>}
            {historical && <p className="rounded-lg border border-info/30 bg-info/10 p-4 text-sm text-info-dark">Nilai historis hanya dapat dikoreksi melalui alur backfill resmi.</p>}
            <Card>
                <CardHeader><CardTitle>{indikator.kode} · {indikator.nama}</CardTitle></CardHeader>
                <CardContent className="space-y-3">
                    {indikator.definisi_operasional && <p className="text-sm text-muted">{indikator.definisi_operasional}</p>}
                    <dl className="grid gap-4 text-sm sm:grid-cols-3">
                        <div><dt className="text-muted">Unit penanggung jawab</dt><dd className="mt-1 font-medium">{pengukuran.penugasan_indikator.unit_kerja.nama}</dd></div>
                        <div><dt className="text-muted">Cara hitung</dt><dd className="mt-1 font-medium">{indikator.tipe_perhitungan.replaceAll('_', ' ')}</dd><dd className="text-xs text-muted">{indikator.arah === 'turun_baik' ? 'Nilai lebih kecil lebih baik' : 'Nilai lebih besar lebih baik'}</dd></div>
                        <div><dt className="text-muted">Target {pengukuran.periode_jadwal.nama_periode}</dt><dd className="mt-1 font-medium">{pengukuran.target === null ? 'Belum tersedia' : `${formatNilai(pengukuran.target, indikator.desimal_tampilan)} ${indikator.satuan}`}</dd></div>
                    </dl>
                </CardContent>
            </Card>
            {!pengukuran.prasyarat.siap && <div className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm text-warning-dark"><h2 className="font-semibold">Prasyarat pengajuan belum lengkap</h2><ul className="mt-2 list-disc space-y-1 pl-5">{pengukuran.prasyarat.alasan.map((reason) => <li key={reason}>{reason}</li>)}</ul><p className="mt-2">Simpan draf untuk memperbarui hasil dan pemenuhan sebelum mengajukan.</p></div>}
            <form onSubmit={submit} className="space-y-6" aria-busy={processing}>
                {Object.keys(errors).length > 0 && <ul ref={errorSummary} tabIndex={-1} id="measurement-errors" role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">{Object.entries(errors).map(([field, message]) => <li key={field}>{message}</li>)}</ul>}
                {requestError && <p role="alert" className="text-sm text-danger">{requestError}</p>}
                <Card>
                    <CardHeader><CardTitle>Nilai pengukuran</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        {manual ? <Input name="nilai" label={`Nilai realisasi (${indikator.satuan})`} type="number" step="any" value={data.nilai} onChange={(event) => setData('nilai', event.target.value)} disabled={disabled} error={errors.nilai} aria-invalid={Boolean(errors.nilai)} aria-describedby={errors.nilai ? 'measurement-errors' : undefined} /> : <div className="space-y-4">
                            <p className="text-sm text-muted">Isi setiap komponen sesuai periode pengukuran. Pratinjau diperbarui dari hasil perhitungan server; simpan draf untuk menyimpan perubahan.</p>
                            {pengukuran.komponen.map((item, index) => <Input key={item.komponen_id} name={`komponen-${item.komponen_id}`} label={`${item.kode} · ${item.label}`} type="number" step="any" value={data.komponen[index]?.nilai ?? ''} onChange={(event) => setData('komponen', data.komponen.map((value, position) => position === index ? { ...value, nilai: event.target.value } : value))} disabled={disabled} helperText={`${item.peran}${item.bobot === null ? '' : ` · Bobot ${item.bobot}`}`} error={fieldErrors[`komponen.${index}.nilai`]} aria-invalid={Boolean(fieldErrors[`komponen.${index}.nilai`])} aria-describedby={fieldErrors[`komponen.${index}.nilai`] ? 'measurement-errors' : undefined} />)}
                        </div>}
                        {!manual && can.update && !historical && <CalculationPreview id={pengukuran.id} komponen={data.komponen} satuan={indikator.satuan} desimalTampilan={indikator.desimal_tampilan} />}
                        <div className="rounded-lg border border-border bg-soft p-4"><p className="text-xs font-medium text-muted">Hasil terakhir tersimpan</p><p className="mt-1 break-words text-2xl font-semibold text-primary">{pengukuran.nilai === null ? statusPerhitungan[pengukuran.status_perhitungan] : `${formatNilai(pengukuran.nilai, indikator.desimal_tampilan)} ${indikator.satuan}`}</p><p className="mt-2 text-xs text-muted">Perubahan input belum mengubah hasil ini.</p></div>
                        {!manual && <Textarea name="alasan_tidak_dapat_dihitung" label="Alasan bila hasil tidak dapat dihitung" value={data.alasan_tidak_dapat_dihitung} onChange={(event) => setData('alasan_tidak_dapat_dihitung', event.target.value)} disabled={disabled} helperText="Isi alasan jika penyebut faktual bernilai nol. Komponen kosong tetap harus dilengkapi sebelum pengajuan." error={errors.alasan_tidak_dapat_dihitung} aria-invalid={Boolean(errors.alasan_tidak_dapat_dihitung)} aria-describedby={errors.alasan_tidak_dapat_dihitung ? 'measurement-errors' : undefined} />}
                        <Textarea name="catatan" label="Catatan pengukuran" value={data.catatan} onChange={(event) => setData('catatan', event.target.value)} disabled={disabled} helperText="Catatan wajib mengikuti ketentuan indikator dan perubahan nilai terhadap pengukuran sah sebelumnya. Server memeriksanya saat pengajuan." error={errors.catatan} aria-invalid={Boolean(errors.catatan)} aria-describedby={errors.catatan ? 'measurement-errors' : undefined} />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Bukti dukung</CardTitle></CardHeader>
                    <CardContent className="space-y-5">
                        <EvidenceList pengukuran={pengukuran} />
                        {can.uploadEvidence && !historical && <div className="space-y-4 rounded-lg border border-border bg-soft p-4">
                            <label className="flex items-center gap-2 text-sm font-medium"><input type="checkbox" checked={data.tambah_bukti} disabled={disabled} onChange={(event) => setData('tambah_bukti', event.target.checked)} />Tambahkan bukti dukung</label>
                            {data.tambah_bukti && <>
                                <div><label htmlFor="jenis-bukti" className="block text-sm font-medium">Persyaratan yang dipenuhi</label><select id="jenis-bukti" value={data.jenis_berkas_id} disabled={disabled} onChange={(event) => {
                                    const selected = pengukuran.persyaratan_bukti.find((item) => item.id === event.target.value);
                                    const available = (['file', 'tautan', 'teks'] as const).filter((mode) => (!selected || selected[`izinkan_${mode}`]) && (mode !== 'file' || pengukuran.unggahan_aktif));
                                    setData((values) => ({ ...values, jenis_berkas_id: event.target.value, menggantikan_id: '', alasan_koreksi: '', mode: available.includes(values.mode) ? values.mode : (available[0] ?? 'file') }));
                                }} className="mt-2 w-full rounded-lg border border-border bg-surface p-2 text-sm focus:ring-2 focus:ring-primary"><option value="">Lampiran tambahan</option>{pengukuran.persyaratan_bukti.map((item) => <option key={item.id} value={item.id}>{item.nama}</option>)}</select></div>
                                {pengukuran.bukti_dukungs.some((item) => (item.jenis_berkas_id ?? '') === data.jenis_berkas_id) && <div>
                                    <label htmlFor="bukti-pendahulu" className="block text-sm font-medium">Bukti yang diganti (opsional)</label>
                                    <select id="bukti-pendahulu" value={data.menggantikan_id} disabled={disabled} onChange={(event) => setData('menggantikan_id', event.target.value)} aria-invalid={Boolean(fieldErrors['bukti.menggantikan_id'])} aria-describedby={fieldErrors['bukti.menggantikan_id'] ? 'measurement-errors' : 'bukti-koreksi-help'} className="mt-2 w-full rounded-lg border border-border bg-surface p-2 text-sm focus:ring-2 focus:ring-primary">
                                        <option value="">Tambahkan tanpa mengganti bukti</option>
                                        {pengukuran.bukti_dukungs.filter((item) => (item.jenis_berkas_id ?? '') === data.jenis_berkas_id).map((item, index) => <option key={item.id} value={item.id}>{index + 1}. {item.nama_asli || item.isi_teks?.slice(0, 60) || item.tautan || 'Bukti tersimpan'} ({item.mode})</option>)}
                                    </select>
                                    <p id="bukti-koreksi-help" className="mt-1 text-xs text-muted">Bukti lama tetap tersimpan pada riwayat. Pengajuan berikutnya menggunakan bukti pengganti.</p>
                                </div>}
                                {data.menggantikan_id && <Textarea name="alasan-koreksi-bukti" label="Alasan koreksi bukti" value={data.alasan_koreksi} disabled={disabled} required onChange={(event) => setData('alasan_koreksi', event.target.value)} error={fieldErrors['bukti.alasan_koreksi']} aria-invalid={Boolean(fieldErrors['bukti.alasan_koreksi'])} aria-describedby={fieldErrors['bukti.alasan_koreksi'] ? 'measurement-errors' : undefined} />}
                                {modes.length === 0 ? <p className="text-sm text-warning-dark">Tidak ada mode tersedia. Persyaratan file akan dievaluasi sebagai pengecualian oleh server.</p> : <>
                                    <div><label htmlFor="mode-bukti" className="block text-sm font-medium">Mode bukti</label><select id="mode-bukti" value={activeMode ?? ''} disabled={disabled} onChange={(event) => setData('mode', event.target.value as BuktiPengukuran['mode'])} className="mt-2 w-full rounded-lg border border-border bg-surface p-2 text-sm focus:ring-2 focus:ring-primary">{modes.map((mode) => <option key={mode} value={mode}>{mode === 'file' ? 'Unggahan file' : mode === 'tautan' ? 'Tautan' : 'Teks'}</option>)}</select></div>
                                    {activeMode === 'file' && <Input name="bukti-file" label="File bukti" type="file" disabled={disabled} onChange={(event) => setData('file', event.target.files?.[0] ?? null)} accept={requirement?.format_diizinkan.split(',').map((format) => `.${format.trim()}`).join(',')} helperText={requirement ? `Format: ${requirement.format_diizinkan}. Maksimum ${requirement.ukuran_maks_kb} KB.` : undefined} error={fieldErrors['bukti.file']} aria-describedby={fieldErrors['bukti.file'] ? 'measurement-errors' : undefined} />}
                                    {activeMode === 'tautan' && <Input name="bukti-tautan" label="Tautan bukti" type="url" value={data.tautan} disabled={disabled} onChange={(event) => setData('tautan', event.target.value)} error={fieldErrors['bukti.tautan']} helperText="Gunakan alamat http atau https." aria-describedby={fieldErrors['bukti.tautan'] ? 'measurement-errors' : undefined} />}
                                    {activeMode === 'teks' && <Textarea name="bukti-teks" label="Isi bukti teks" value={data.isi_teks} disabled={disabled} onChange={(event) => setData('isi_teks', event.target.value)} error={fieldErrors['bukti.isi_teks']} aria-describedby={fieldErrors['bukti.isi_teks'] ? 'measurement-errors' : undefined} />}
                                </>}
                                <p className="text-xs text-muted">Satu bukti ditambahkan setiap penyimpanan. Simpan kembali untuk menambahkan mode atau persyaratan lainnya.</p>
                            </>}
                        </div>}
                    </CardContent>
                </Card>
                {can.update && !historical && <div className="flex flex-wrap justify-between gap-3 border-t border-border pt-4">
                    <Button type="submit" name="action" value="draft" variant="outline" isLoading={processing} className="border-border bg-surface text-ink hover:bg-soft focus:ring-primary"><Save className="mr-2 h-4 w-4" />Simpan Sebagai Draft</Button>
                    {can.submit && <Button type="submit" name="action" value="ajukan" isLoading={processing} className="bg-primary text-white hover:bg-primary/90 focus:ring-primary"><Send className="mr-2 h-4 w-4" />Ajukan ke Tim Perencanaan</Button>}
                </div>}
            </form>
        </div>
    </AuthenticatedLayout>;
}
