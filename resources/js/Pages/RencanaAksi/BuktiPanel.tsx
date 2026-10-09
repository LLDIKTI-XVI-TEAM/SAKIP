import { useState, type FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { BuktiItem, BuktiMode, BuktiPersyaratan, RencanaAksiBukti } from './types';

interface BuktiPanelProps {
    rencanaAksiId: string;
    bukti: RencanaAksiBukti;
}

const MODE: BuktiMode[] = ['file', 'tautan', 'teks'];
const LABEL_MODE: Record<BuktiMode, string> = { file: 'File', tautan: 'Tautan', teks: 'Teks' };

/** Galat validasi bisnis gerbang tulis (422) yang tidak terikat ke field form. */
function galatBisnis(errs: Record<string, string | undefined>): string | undefined {
    return errs.status_alur ?? errs.jendela ?? errs.unit_id ?? errs.indikator_id;
}

/** Mode yang boleh dipilih di UI mengikuti persyaratan + saklar unggahan; server memvalidasi ulang. */
function modeTersedia(req: BuktiPersyaratan | undefined, unggahanAktif: boolean): BuktiMode[] {
    return MODE.filter((mode) => {
        if (mode === 'file') return (req ? req.izinkan_file : true) && unggahanAktif;
        if (mode === 'tautan') return req ? req.izinkan_tautan : true;
        return req ? req.izinkan_teks : true;
    });
}

/** Panel pemenuhan bukti tahap rencana aksi (PRD §18, Workflow §10.3) pada halaman kerja induknya. */
export default function BuktiPanel({ rencanaAksiId, bukti }: BuktiPanelProps) {
    const { persyaratan, ringkasan, daftar, unggahan, can } = bukti;
    const formatTanggal = useFormatTanggal();
    const [modalTambah, setModalTambah] = useState(false);
    const [requestError, setRequestError] = useState('');
    const [hapusTarget, setHapusTarget] = useState<BuktiItem | null>(null);
    const [alasanHapus, setAlasanHapus] = useState('');
    const [menghapus, setMenghapus] = useState(false);
    const [galatHapus, setGalatHapus] = useState<string | undefined>();

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<{
        jenis_berkas_id: string;
        mode: BuktiMode;
        file: File | null;
        tautan: string;
        isi_teks: string;
    }>({ jenis_berkas_id: '', mode: 'file', file: null, tautan: '', isi_teks: '' });

    const fieldErrors = errors as Record<string, string | undefined>;
    const persyaratanAktif = persyaratan.find((item) => item.id === data.jenis_berkas_id);
    const modeBoleh = modeTersedia(persyaratanAktif, unggahan.unggahan_aktif);

    const bukaTambah = (jenisBerkasId = ''): void => {
        const req = persyaratan.find((item) => item.id === jenisBerkasId);
        clearErrors();
        setRequestError('');
        setData({ jenis_berkas_id: jenisBerkasId, mode: modeTersedia(req, unggahan.unggahan_aktif)[0] ?? 'tautan', file: null, tautan: '', isi_teks: '' });
        setModalTambah(true);
    };

    const tutupTambah = (): void => {
        setModalTambah(false);
        reset();
        clearErrors();
    };

    const gantiPersyaratan = (jenisBerkasId: string): void => {
        const boleh = modeTersedia(persyaratan.find((item) => item.id === jenisBerkasId), unggahan.unggahan_aktif);
        setData({ ...data, jenis_berkas_id: jenisBerkasId, mode: boleh.includes(data.mode) ? data.mode : (boleh[0] ?? 'tautan') });
    };

    const kirim = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (processing || requestError !== '') {
            return;
        }
        post(`/rencana-aksi/${rencanaAksiId}/bukti`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => tutupTambah(),
            onCancel: () => {
                setRequestError('Permintaan dibatalkan. Periksa daftar bukti sebelum mengirim ulang.');
            },
            onNetworkError: () => {
                setRequestError('Koneksi terputus. Hasil penyimpanan belum diketahui; muat ulang halaman sebelum mengirim ulang.');
                return false;
            },
            onHttpException: (response) => {
                setRequestError(response.status === 403
                    ? 'Izin pengiriman bukti ditolak. Periksa akses sebelum mencoba kembali.'
                    : 'Hasil penyimpanan belum dapat dipastikan. Muat ulang halaman sebelum mencoba kembali.');
                return false;
            },
        });
    };

    const tutupHapus = (): void => {
        setHapusTarget(null);
        setAlasanHapus('');
        setGalatHapus(undefined);
    };

    const konfirmasiHapus = (): void => {
        if (!hapusTarget || menghapus) {
            return;
        }
        setMenghapus(true);
        setGalatHapus(undefined);
        router.delete(`/rencana-aksi/${rencanaAksiId}/bukti/${hapusTarget.id}`, {
            data: { alasan: alasanHapus },
            preserveScroll: true,
            onSuccess: () => tutupHapus(),
            onError: (errs) => setGalatHapus(errs.alasan ?? galatBisnis(errs) ?? 'Penghapusan bukti gagal.'),
            onNetworkError: () => {
                setGalatHapus('Koneksi terputus. Muat ulang halaman untuk memastikan status bukti.');
                return false;
            },
            onHttpException: (response) => {
                setGalatHapus(response.status === 403
                    ? 'Izin penghapusan bukti ditolak. Muat ulang halaman untuk melihat akses terbaru.'
                    : 'Hasil penghapusan belum dapat dipastikan. Muat ulang halaman sebelum mencoba kembali.');
                return false;
            },
            onFinish: () => setMenghapus(false),
        });
    };

    const judulBukti = (item: BuktiItem): string => item.nama_persyaratan ?? 'Lampiran bebas';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Bukti dukung</CardTitle>
                <div className="flex flex-wrap items-center gap-3">
                    <Badge variant={ringkasan.lengkap ? 'success' : 'warning'} size="sm">
                        {ringkasan.lengkap ? 'Lengkap' : 'Belum lengkap'} ({ringkasan.terpenuhi_wajib}/{ringkasan.total_wajib} wajib)
                    </Badge>
                    {can.upload && (
                        <Button type="button" variant="primary" size="sm" onClick={() => bukaTambah()}>
                            <Plus className="mr-1.5 h-4 w-4" aria-hidden="true" />
                            Tambah bukti
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                <section aria-labelledby="persyaratan-bukti-ra" className="space-y-3">
                    <h4 id="persyaratan-bukti-ra" className="text-sm font-semibold text-ink">Persyaratan tahap rencana aksi</h4>
                    {persyaratan.length === 0 ? (
                        <p className="text-sm text-muted">Tidak ada persyaratan bukti tahap rencana aksi untuk indikator ini; lampiran bebas tetap dapat ditambahkan.</p>
                    ) : (
                        <ul className="divide-y divide-border rounded-lg border border-border">
                            {persyaratan.map((req) => (
                                <li key={req.id} className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink">
                                            <span>{req.nama}</span>
                                            <Badge variant={req.wajib ? 'danger' : 'muted'} size="sm">{req.wajib ? 'Wajib' : 'Opsional'}</Badge>
                                            {req.semua_mode_wajib && <Badge variant="info" size="sm">Semua mode wajib</Badge>}
                                        </p>
                                        {req.keterangan && <p className="text-xs text-muted">{req.keterangan}</p>}
                                        <p className="text-xs text-muted">
                                            Mode diizinkan: {MODE.filter((mode) => modeTersedia(req, true).includes(mode)).map((mode) => LABEL_MODE[mode]).join(', ')}
                                            {req.izinkan_file && ` · ${req.format_diizinkan}, maks. ${req.ukuran_maks_kb} KB`}
                                        </p>
                                        {req.pemenuhan.tidak_dapat_dipenuhi && (
                                            <p className="text-xs font-medium text-info-dark">Ditandai tidak dapat dipenuhi: {req.pemenuhan.alasan_pengecualian}</p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-3">
                                        {req.pemenuhan.terpenuhi ? (
                                            <Badge variant={req.pemenuhan.tidak_dapat_dipenuhi ? 'info' : 'success'} size="sm">
                                                {req.pemenuhan.tidak_dapat_dipenuhi ? 'Dikecualikan' : 'Terpenuhi'}
                                            </Badge>
                                        ) : (
                                            <Badge variant="warning" size="sm">
                                                Kurang: {req.pemenuhan.mode_kurang.map((mode) => LABEL_MODE[mode]).join(', ')}
                                            </Badge>
                                        )}
                                        {/* Tanpa mode yang tersedia (mis. file-only saat unggahan nonaktif) form pasti ditolak server. */}
                                        {can.upload && modeTersedia(req, unggahan.unggahan_aktif).length > 0 && (
                                            <Button type="button" variant="outline" size="sm" onClick={() => bukaTambah(req.id)}>
                                                Penuhi
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="daftar-bukti-ra" className="space-y-3">
                    <h4 id="daftar-bukti-ra" className="text-sm font-semibold text-ink">Bukti terlampir ({daftar.length})</h4>
                    {daftar.length === 0 ? (
                        <p className="text-sm text-muted">Belum ada bukti dukung yang dilampirkan.</p>
                    ) : (
                        <ul className="space-y-3">
                            {daftar.map((item) => (
                                <BerkasLampiranItem
                                    key={item.id}
                                    berkas={item}
                                    meta={`${judulBukti(item)} · ${item.pengunggah ?? 'Pengguna'} · ${formatTanggal(item.created_at, { withTime: true })}`}
                                    action={can.delete && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-danger hover:bg-danger/10 hover:text-danger"
                                            aria-label={`Hapus bukti ${item.nama_asli ?? judulBukti(item)}`}
                                            onClick={() => { setGalatHapus(undefined); setAlasanHapus(''); setHapusTarget(item); }}
                                        >
                                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                                        </Button>
                                    )}
                                />
                            ))}
                        </ul>
                    )}
                </section>
            </CardContent>

            <Modal isOpen={modalTambah} onClose={tutupTambah} title="Tambah bukti dukung" size="lg">
                <form onSubmit={kirim} className="space-y-4" aria-busy={processing}>
                    <Select
                        id="bukti-jenis-berkas"
                        label="Persyaratan"
                        value={data.jenis_berkas_id}
                        onChange={(event) => gantiPersyaratan(event.target.value)}
                        error={fieldErrors.jenis_berkas_id}
                        disabled={processing}
                    >
                        <option value="">Lampiran bebas (tanpa persyaratan)</option>
                        {persyaratan.map((req) => (
                            <option key={req.id} value={req.id}>
                                {req.nama} ({req.wajib ? 'wajib' : 'opsional'})
                            </option>
                        ))}
                    </Select>

                    <div>
                        <p id="bukti-mode-label" className="mb-1.5 block text-sm font-medium text-ink">Mode bukti</p>
                        <div role="group" aria-labelledby="bukti-mode-label" className="flex flex-wrap gap-2">
                            {MODE.map((mode) => (
                                <Button
                                    key={mode}
                                    type="button"
                                    size="sm"
                                    variant={data.mode === mode ? 'primary' : 'outline'}
                                    aria-pressed={data.mode === mode}
                                    disabled={processing || !modeBoleh.includes(mode)}
                                    onClick={() => setData('mode', mode)}
                                >
                                    {LABEL_MODE[mode]}
                                </Button>
                            ))}
                        </div>
                        {!unggahan.unggahan_aktif && (
                            <p className="mt-1 text-xs text-muted">Unggahan file sedang dinonaktifkan pada setelan aplikasi; gunakan tautan atau teks.</p>
                        )}
                        {fieldErrors.mode && <p className="mt-1 text-xs text-danger" role="alert">{fieldErrors.mode}</p>}
                    </div>

                    {data.mode === 'file' && (
                        <div>
                            <label htmlFor="bukti-file" className="mb-1.5 block text-sm font-medium text-ink">Berkas</label>
                            <input
                                id="bukti-file"
                                type="file"
                                required
                                disabled={processing}
                                aria-invalid={fieldErrors.file ? true : undefined}
                                aria-describedby="bukti-file-keterangan"
                                onChange={(event) => setData('file', event.target.files?.[0] ?? null)}
                                className="block w-full text-sm text-ink file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-dark"
                            />
                            <p id="bukti-file-keterangan" className="mt-1 text-xs text-muted">
                                Format: {persyaratanAktif?.format_diizinkan ?? unggahan.format_diizinkan}. Ukuran maksimal {persyaratanAktif?.ukuran_maks_kb ?? unggahan.ukuran_maks_kb} KB.
                            </p>
                            {fieldErrors.file && <p className="mt-1 text-xs text-danger" role="alert">{fieldErrors.file}</p>}
                        </div>
                    )}
                    {data.mode === 'tautan' && (
                        <Input
                            id="bukti-tautan"
                            label="Tautan"
                            type="url"
                            required
                            disabled={processing}
                            placeholder="https://"
                            value={data.tautan}
                            onChange={(event) => setData('tautan', event.target.value)}
                            error={fieldErrors.tautan}
                            helperText="Gunakan alamat http atau https."
                        />
                    )}
                    {data.mode === 'teks' && (
                        <Textarea
                            id="bukti-teks"
                            label="Keterangan"
                            rows={4}
                            required
                            disabled={processing}
                            value={data.isi_teks}
                            onChange={(event) => setData('isi_teks', event.target.value)}
                            error={fieldErrors.isi_teks}
                        />
                    )}

                    {galatBisnis(fieldErrors) && (
                        <p role="alert" className="text-sm text-danger">{galatBisnis(fieldErrors)}</p>
                    )}
                    {requestError !== '' && <p role="alert" className="text-sm text-danger">{requestError}</p>}

                    <div className="flex justify-end gap-2 border-t border-border pt-4">
                        <Button type="button" variant="outline" disabled={processing} onClick={tutupTambah}>Batal</Button>
                        <Button type="submit" variant="primary" isLoading={processing} disabled={processing || requestError !== ''}>Simpan bukti</Button>
                    </div>
                </form>
            </Modal>

            <AuditReasonModal
                open={hapusTarget !== null}
                title="Hapus bukti dukung"
                description={`Bukti "${hapusTarget ? (hapusTarget.nama_asli ?? judulBukti(hapusTarget)) : ''}" akan dihapus dari rencana aksi ini.`}
                reason={alasanHapus}
                error={galatHapus}
                busy={menghapus}
                submitDisabled={alasanHapus.trim().length < 3}
                confirmLabel="Hapus"
                destructive
                onReasonChange={setAlasanHapus}
                onClose={tutupHapus}
                onConfirm={konfirmasiHapus}
            />
        </Card>
    );
}
