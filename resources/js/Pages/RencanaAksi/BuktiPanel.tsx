import { useState, type FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Plus, Trash2, Upload } from 'lucide-react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import { Modal } from '@/Components/Modal';
import { Select } from '@/Components/Select';
import { Table, TableBody, TableCell, TableCellLabel, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Tooltip } from '@/Components/Tooltip';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { LampiranDraft } from '@/types/lampiran';
import JudulLangkah from './JudulLangkah';
import type { BuktiItem, BuktiMode, BuktiPersyaratan, RencanaAksiBukti } from './types';

interface BuktiPanelProps {
    rencanaAksiId: string;
    bukti: RencanaAksiBukti;
    /** Nomor langkah pada halaman detail RA; kosong bila halaman tidak bernomor. */
    nomor?: number;
}

const MODE: BuktiMode[] = ['file', 'tautan', 'teks'];
const LABEL_MODE: Record<BuktiMode, string> = { file: 'File', tautan: 'Tautan', teks: 'Teks' };

// Kolom tabel persyaratan baru muat di samping sidebar mulai 1024 px; di bawahnya setiap baris tampil sebagai kartu berlabel.
const selResponsif = 'min-w-0 p-0 lg:px-4 lg:py-3.5';
const labelResponsif = 'md:inline lg:hidden';

/** Galat validasi bisnis gerbang tulis (422) yang tidak terikat ke field form. */
function galatBisnis(errs: Record<string, string | undefined>): string | undefined {
    return errs.status_alur ?? errs.jendela ?? errs.unit_id ?? errs.indikator_id;
}

/** Mode yang boleh dipilih di UI mengikuti persyaratan; server memvalidasi ulang. */
function modeTersedia(req: BuktiPersyaratan | undefined): BuktiMode[] {
    return MODE.filter((mode) => {
        if (mode === 'file') return req ? req.izinkan_file : true;
        if (mode === 'tautan') return req ? req.izinkan_tautan : true;
        return req ? req.izinkan_teks : true;
    });
}

/** Panel pemenuhan bukti tahap rencana aksi (PRD §18, Workflow §10.3) pada halaman kerja induknya. */
export default function BuktiPanel({ rencanaAksiId, bukti, nomor }: BuktiPanelProps) {
    const { persyaratan, ringkasan, daftar, unggahan, can, dikecualikan } = bukti;
    const formatTanggal = useFormatTanggal();
    const [modalTambah, setModalTambah] = useState(false);
    const [requestError, setRequestError] = useState('');
    const [hapusTarget, setHapusTarget] = useState<BuktiItem | null>(null);
    const [alasanHapus, setAlasanHapus] = useState('');
    const [menghapus, setMenghapus] = useState(false);
    const [galatHapus, setGalatHapus] = useState<string | undefined>();

    // Satu bukti per penyimpanan: editor lampiran bersama dibatasi satu item, lalu didatarkan saat dikirim.
    const { data, setData, transform, post, processing, errors, reset, clearErrors } = useForm<{
        jenis_berkas_id: string;
        lampiran: LampiranDraft[];
    }>({ jenis_berkas_id: '', lampiran: [] });

    const fieldErrors = errors as Record<string, string | undefined>;
    const persyaratanAktif = persyaratan.find((item) => item.id === data.jenis_berkas_id);
    const modeBoleh = modeTersedia(persyaratanAktif);
    const formatUnggah = persyaratanAktif?.format_diizinkan ?? unggahan.format_diizinkan;
    const ukuranUnggahKb = persyaratanAktif?.ukuran_maks_kb ?? unggahan.ukuran_maks_kb;

    const bukaTambah = (jenisBerkasId = ''): void => {
        const req = persyaratan.find((item) => item.id === jenisBerkasId);
        clearErrors();
        setRequestError('');
        setData({ jenis_berkas_id: jenisBerkasId, lampiran: [{ clientId: 'bukti', mode: modeTersedia(req)[0] ?? 'tautan', file: null, tautan: '', isi_teks: '' }] });
        setModalTambah(true);
    };

    const tutupTambah = (): void => {
        setModalTambah(false);
        reset();
        clearErrors();
    };

    const gantiPersyaratan = (jenisBerkasId: string): void => {
        const boleh = modeTersedia(persyaratan.find((item) => item.id === jenisBerkasId));
        setData({ jenis_berkas_id: jenisBerkasId, lampiran: data.lampiran.map((item) => (
            boleh.includes(item.mode) ? item : { ...item, mode: boleh[0] ?? 'tautan', file: null, tautan: '', isi_teks: '' }
        )) });
    };

    const kirim = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const [lampiran] = data.lampiran;
        if (processing || requestError !== '' || !lampiran) {
            return;
        }
        transform(({ jenis_berkas_id }) => ({ jenis_berkas_id, mode: lampiran.mode, file: lampiran.file, tautan: lampiran.tautan, isi_teks: lampiran.isi_teks }));
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
        <Card role="region" aria-labelledby="ra-langkah-bukti">
            <CardHeader>
                <JudulLangkah id="ra-langkah-bukti" nomor={nomor}>Bukti dukung</JudulLangkah>
                <div className="flex flex-wrap items-center gap-3">
                    {dikecualikan ? (
                        <Badge variant="info">Dikecualikan (periode lampau/data awal)</Badge>
                    ) : (
                        <Badge variant={ringkasan.lengkap ? 'success' : 'warning'}>
                            {ringkasan.lengkap ? 'Lengkap' : 'Belum lengkap'} ({ringkasan.terpenuhi_wajib}/{ringkasan.total_wajib} wajib)
                        </Badge>
                    )}
                    {can.upload && (
                        <Button type="button" variant="primary" size="sm" onClick={() => bukaTambah()}>
                            <Plus className="mr-1.5 h-4 w-4" aria-hidden="true" />
                            Tambah bukti
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="divide-y divide-border p-0">
                <section aria-labelledby="persyaratan-bukti-ra">
                    <h4 id="persyaratan-bukti-ra" className="px-6 py-3 text-sm font-semibold text-ink">Persyaratan tahap rencana aksi</h4>
                    {persyaratan.length === 0 ? (
                        <p className="px-6 pb-4 text-sm text-muted">Tidak ada persyaratan bukti tahap rencana aksi untuk indikator ini; lampiran bebas tetap dapat ditambahkan.</p>
                    ) : (
                        <Table aria-labelledby="persyaratan-bukti-ra" wrapperClassName="overflow-x-visible" className="block lg:table lg:table-fixed">
                            <TableHeader className="max-lg:sr-only">
                                <TableRow>
                                    <TableHead className="whitespace-normal">Persyaratan</TableHead>
                                    <TableHead className="w-56">Mode diizinkan</TableHead>
                                    <TableHead className="w-40">Status</TableHead>
                                    {can.upload && <TableHead className="w-20 text-right">Aksi</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block lg:table-row-group">
                                {persyaratan.map((req) => (
                                    <TableRow key={req.id} className="grid min-w-0 grid-cols-2 gap-3 px-6 py-4 lg:table-row">
                                        <TableCell className={`col-span-2 ${selResponsif}`}>
                                            <p className="break-words text-sm font-medium text-ink">{req.nama}</p>
                                            {req.keterangan && <p className="mt-0.5 break-words text-xs text-muted">{req.keterangan}</p>}
                                            <span className="mt-1.5 flex flex-wrap gap-1">
                                                <Badge variant={req.wajib ? 'danger' : 'muted'}>{req.wajib ? 'Wajib' : 'Opsional'}</Badge>
                                                {req.semua_mode_wajib && <Badge variant="info">Semua mode wajib</Badge>}
                                            </span>
                                            {req.pemenuhan.tidak_dapat_dipenuhi && (
                                                <p className="mt-1.5 text-xs font-medium text-info-dark">Ditandai tidak dapat dipenuhi: {req.pemenuhan.alasan_pengecualian}</p>
                                            )}
                                        </TableCell>
                                        <TableCell className={`col-span-2 ${selResponsif}`}>
                                            <TableCellLabel className={labelResponsif}>Mode diizinkan</TableCellLabel>
                                            <span className="break-words">{modeTersedia(req).map((mode) => LABEL_MODE[mode]).join(', ') || '—'}{req.izinkan_file ? ` · ${req.format_diizinkan}, maks. ${req.ukuran_maks_kb} KB` : ''}</span>
                                        </TableCell>
                                        <TableCell className={selResponsif}>
                                            <TableCellLabel className={labelResponsif}>Status</TableCellLabel>
                                            {dikecualikan && req.wajib && !req.pemenuhan.terpenuhi ? (
                                                <Badge variant="info">Dikecualikan</Badge>
                                            ) : req.pemenuhan.terpenuhi ? (
                                                <Badge variant={req.pemenuhan.tidak_dapat_dipenuhi ? 'info' : 'success'}>
                                                    {req.pemenuhan.tidak_dapat_dipenuhi ? 'Dikecualikan' : 'Terpenuhi'}
                                                </Badge>
                                            ) : (
                                                <Badge variant="warning">
                                                    Kurang: {req.pemenuhan.mode_kurang.map((mode) => LABEL_MODE[mode]).join(', ')}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        {can.upload && (
                                            <TableCell className={`${selResponsif} text-right`}>
                                                {/* Persyaratan tanpa mode yang diizinkan tidak dapat dipenuhi lewat form. */}
                                                {modeTersedia(req).length > 0 && (
                                                    <Tooltip content="Penuhi persyaratan" align="right">
                                                        <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0" aria-label={`Penuhi ${req.nama}`} onClick={() => bukaTambah(req.id)}>
                                                            <Upload className="h-4 w-4" aria-hidden="true" />
                                                        </Button>
                                                    </Tooltip>
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </section>

                <section aria-labelledby="daftar-bukti-ra">
                    <h4 id="daftar-bukti-ra" className="px-6 py-3 text-sm font-semibold text-ink">Bukti terlampir ({daftar.length})</h4>
                    {daftar.length === 0 ? (
                        <p className="px-6 pb-4 text-sm text-muted">Belum ada bukti dukung yang dilampirkan.</p>
                    ) : (
                        <ul className="space-y-3 px-6 pb-6">
                            {daftar.map((item) => (
                                <BerkasLampiranItem
                                    key={item.id}
                                    berkas={item}
                                    meta={`${judulBukti(item)} · ${item.pengunggah ?? 'Pengguna'} · ${formatTanggal(item.created_at, { withTime: true })}`}
                                    action={can.delete && (
                                        <Tooltip content="Hapus bukti" align="right">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="h-8 w-8 p-0 text-danger hover:bg-danger/10"
                                                aria-label={`Hapus bukti ${item.nama_asli ?? judulBukti(item)}`}
                                                onClick={() => { setGalatHapus(undefined); setAlasanHapus(''); setHapusTarget(item); }}
                                            >
                                                <Trash2 className="h-4 w-4" aria-hidden="true" />
                                            </Button>
                                        </Tooltip>
                                    )}
                                />
                            ))}
                        </ul>
                    )}
                </section>
            </CardContent>

            {/* Selama unggahan berjalan modal tidak boleh ditutup agar outcome server tetap terlihat. */}
            <Modal
                isOpen={modalTambah}
                onClose={() => { if (!processing) tutupTambah(); }}
                title="Tambah bukti dukung"
                size="lg"
                footer={
                    <div className="flex w-full items-center justify-end gap-3">
                        <Button type="button" variant="outline" disabled={processing} onClick={tutupTambah}>Batal</Button>
                        <Button type="submit" form="bukti-ra-form" variant="primary" isLoading={processing} disabled={processing || requestError !== '' || data.lampiran.length === 0}>Simpan bukti</Button>
                    </div>
                }
            >
                <form id="bukti-ra-form" onSubmit={kirim} className="space-y-4" aria-busy={processing}>
                    <LampiranDraftFields
                        items={data.lampiran}
                        errors={{
                            'lampiran.0.mode': fieldErrors.mode,
                            'lampiran.0.file': fieldErrors.file,
                            'lampiran.0.tautan': fieldErrors.tautan,
                            'lampiran.0.isi_teks': fieldErrors.isi_teks,
                        }}
                        // Menghapus lampiran ikut mengosongkan persyaratan, seperti bukti baru pada Pengukuran.
                        onChange={(update) => {
                            const lampiran = update(data.lampiran);
                            setData({ jenis_berkas_id: lampiran.length > 0 ? data.jenis_berkas_id : '', lampiran });
                        }}
                        title="Lampiran Dokumen"
                        modeDescriptions={{
                            file: `Format ${formatUnggah}; maksimal ${ukuranUnggahKb % 1024 === 0 ? `${ukuranUnggahKb / 1024} MB` : `${ukuranUnggahKb} KB`}.`,
                            tautan: 'Gunakan alamat http atau https.',
                            teks: 'Tuliskan bukti dukung dalam bentuk teks.',
                        }}
                        placeholders={{ tautan: 'https://', teks: '' }}
                        emptyText="Belum ada lampiran yang ditambahkan"
                        accept={formatUnggah.split(',').map((format) => `.${format.trim()}`).join(',')}
                        disabled={processing}
                        maxItems={1}
                        modes={modeBoleh}
                        renderItemFields={() => (
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
                                    <option key={req.id} value={req.id} disabled={modeTersedia(req).length === 0}>
                                        {req.nama} ({req.wajib ? 'wajib' : 'opsional'})
                                    </option>
                                ))}
                            </Select>
                        )}
                    />

                    {galatBisnis(fieldErrors) && (
                        <p role="alert" className="text-sm text-danger">{galatBisnis(fieldErrors)}</p>
                    )}
                    {requestError !== '' && <p role="alert" className="text-sm text-danger">{requestError}</p>}
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
