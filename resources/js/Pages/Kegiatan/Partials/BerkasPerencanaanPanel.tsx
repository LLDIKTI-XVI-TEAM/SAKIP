import { useId, useState, type FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Download, RefreshCw, Trash2, Upload } from 'lucide-react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { LampiranDraftFields } from '@/Components/LampiranDraftFields';
import { Modal } from '@/Components/Modal';
import { Table, TableBody, TableCell, TableCellLabel, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Textarea } from '@/Components/Textarea';
import { Tooltip } from '@/Components/Tooltip';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { LampiranDraft } from '@/types/lampiran';
import type { BerkasKegiatanItem, BerkasKegiatanMode, BerkasPerencanaanKegiatan, PersyaratanPerencanaanKegiatan } from '@/types/kegiatan';

interface Props {
    kegiatanId: string;
    berkas: BerkasPerencanaanKegiatan;
    tahap?: 'perencanaan' | 'pelaksanaan';
}

const MODE: BerkasKegiatanMode[] = ['file', 'tautan', 'teks'];
const LABEL_MODE: Record<BerkasKegiatanMode, string> = { file: 'File', tautan: 'Tautan', teks: 'Teks' };
const modeBoleh = (req: PersyaratanPerencanaanKegiatan) => MODE.filter((mode) => req[`izinkan_${mode}`]);

// Sama dengan bukti dukung RA: kolom tabel muat mulai 1024 px; di bawahnya setiap baris tampil sebagai kartu berlabel.
const selResponsif = 'min-w-0 p-0 lg:px-4 lg:py-3.5 lg:align-top';
const labelResponsif = 'md:inline lg:hidden';

/** Galat gerbang tulis (422) yang tidak terikat ke field form. */
const galatBisnis = (errs: Record<string, string | undefined>) => errs.berkas ?? errs.tahun ?? errs.unit_id ?? errs.jenis_berkas_id ?? errs.menggantikan_id;

/** Panel bukti dua tahap kegiatan; capability dan pemenuhan selalu berasal dari gerbang server. */
export function BerkasPerencanaanPanel({ kegiatanId, berkas, tahap = 'perencanaan' }: Props) {
    const { persyaratan, alasan_tolak_unggah, alasan_tolak_ganti, alasan_tolak_hapus } = berkas;
    const id = useId();
    const formatTanggal = useFormatTanggal();
    const [target, setTarget] = useState<{ req: PersyaratanPerencanaanKegiatan; ganti: BerkasKegiatanItem | null } | null>(null);
    const [hapus, setHapus] = useState<BerkasKegiatanItem | null>(null);
    const [alasanHapus, setAlasanHapus] = useState('');
    const [galatHapus, setGalatHapus] = useState<string | undefined>();
    const [menghapus, setMenghapus] = useState(false);
    // Satu berkas per penyimpanan: editor lampiran bersama dibatasi satu item, lalu didatarkan saat dikirim.
    const { data, setData, transform, post, processing, errors, reset, clearErrors } = useForm<{
        jenis_berkas_id: string; menggantikan_id: string; alasan_koreksi: string; lampiran: LampiranDraft[];
    }>({ jenis_berkas_id: '', menggantikan_id: '', alasan_koreksi: '', lampiran: [] });
    const fieldErrors = errors as Record<string, string | undefined>;

    if (persyaratan.length === 0) return null;

    const terlampir = persyaratan.flatMap((req) => req.berkas.map((item) => ({ req, item })));
    const unggahBoleh = !alasan_tolak_unggah;

    const buka = (req: PersyaratanPerencanaanKegiatan, ganti: BerkasKegiatanItem | null = null) => {
        clearErrors();
        const mode = ganti && req[`izinkan_${ganti.mode}`] ? ganti.mode : (modeBoleh(req)[0] ?? 'file');
        setData({ jenis_berkas_id: req.id, menggantikan_id: ganti?.id ?? '', alasan_koreksi: '',
            lampiran: [{ clientId: 'berkas', mode, file: null, tautan: '', isi_teks: '' }] });
        setTarget({ req, ganti });
    };
    const tutup = () => { setTarget(null); reset(); clearErrors(); };

    const kirim = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const [lampiran] = data.lampiran;
        if (processing || !lampiran) return;
        transform(({ jenis_berkas_id, menggantikan_id, alasan_koreksi }) => ({ jenis_berkas_id, mode: lampiran.mode, file: lampiran.file,
            tautan: lampiran.tautan, isi_teks: lampiran.isi_teks, menggantikan_id, alasan_koreksi }));
        post(`/kegiatan/${kegiatanId}/berkas`, { forceFormData: true, preserveScroll: true, onSuccess: tutup });
    };

    const konfirmasiHapus = () => {
        if (!hapus || menghapus) return;
        setMenghapus(true);
        router.delete(`/kegiatan/${kegiatanId}/berkas/${hapus.id}`, {
            data: { alasan: alasanHapus },
            preserveScroll: true,
            onSuccess: () => { setHapus(null); setAlasanHapus(''); setGalatHapus(undefined); },
            onError: (errs) => setGalatHapus(errs.alasan ?? galatBisnis(errs) ?? 'Penghapusan berkas gagal.'),
            onFinish: () => setMenghapus(false),
        });
    };

    const ukuran = (kb: number) => (kb % 1024 === 0 ? `${kb / 1024} MB` : `${kb} KB`);
    const namaBerkas = (req: PersyaratanPerencanaanKegiatan, item: BerkasKegiatanItem) => item.nama_asli ?? req.nama;

    return (
        <Card role="region" aria-labelledby={`${id}-judul`}>
            <CardHeader>
                <div className="min-w-0 flex-1 space-y-1.5">
                    <CardTitle id={`${id}-judul`}>Berkas {tahap} kegiatan</CardTitle>
                    <p className="text-sm text-muted">{tahap === 'perencanaan'
                        ? 'Persyaratan wajib diperiksa saat rencana aksi yang mengklaim kegiatan ini diajukan.'
                        : 'Bukti wajib gabungan dari persyaratan global dan indikator yang diklaim diperiksa saat kegiatan ditandai terlaksana.'}</p>
                    {alasan_tolak_unggah && <p className="text-sm text-muted">{alasan_tolak_unggah}</p>}
                </div>
            </CardHeader>
            <CardContent className="divide-y divide-border p-0">
                <section aria-labelledby={`${id}-persyaratan`}>
                    <h4 id={`${id}-persyaratan`} className="px-6 py-3 text-sm font-semibold text-ink">Persyaratan {tahap} kegiatan</h4>
                    <Table aria-labelledby={`${id}-persyaratan`} wrapperClassName="overflow-x-visible" className="block lg:table lg:table-fixed">
                        <TableHeader className="max-lg:sr-only">
                            <TableRow>
                                <TableHead className="whitespace-normal">Persyaratan</TableHead>
                                <TableHead className="w-56">Mode diizinkan</TableHead>
                                <TableHead className="w-40">Status</TableHead>
                                {unggahBoleh && <TableHead className="w-20 text-right">Aksi</TableHead>}
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
                                            {req.hanya_kegiatan_beranggaran && <Badge variant="info">Kegiatan beranggaran</Badge>}
                                        </span>
                                        {req.contoh.map((contoh) => (
                                            <a key={contoh.id} href={`/jenis-berkas/${req.id}/contoh/${contoh.id}`} className="mt-1.5 flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                                <Download className="h-3.5 w-3.5" aria-hidden="true" />
                                                Contoh: {contoh.nama_asli ?? 'berkas'}
                                            </a>
                                        ))}
                                    </TableCell>
                                    <TableCell className={`col-span-2 ${selResponsif}`}>
                                        <TableCellLabel className={labelResponsif}>Mode diizinkan</TableCellLabel>
                                        <p className="text-ink">{modeBoleh(req).map((mode) => LABEL_MODE[mode]).join(', ') || '—'}</p>
                                        {req.izinkan_file && (
                                            <p className="mt-0.5 break-words text-xs text-muted">
                                                {req.format_diizinkan.split(',').map((format) => format.trim()).join(', ')} · maks. {ukuran(req.ukuran_maks_kb)}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className={selResponsif}>
                                        <TableCellLabel className={labelResponsif}>Status</TableCellLabel>
                                        {/* Badge tidak membungkus baris; rincian mode kurang ditulis di bawahnya agar tidak meluber ke kolom aksi. */}
                                        <Badge variant={req.pemenuhan.terpenuhi ? 'success' : 'warning'}>{req.pemenuhan.terpenuhi ? 'Terpenuhi' : 'Belum terpenuhi'}</Badge>
                                        {!req.pemenuhan.terpenuhi && req.pemenuhan.mode_kurang.length > 0 && (
                                            <p className="mt-1 break-words text-xs text-muted">Kurang: {req.pemenuhan.mode_kurang.map((mode) => LABEL_MODE[mode]).join(', ')}</p>
                                        )}
                                    </TableCell>
                                    {unggahBoleh && (
                                        <TableCell className={`${selResponsif} text-right`}>
                                            {/* Persyaratan tanpa mode yang diizinkan tidak dapat dipenuhi lewat form. */}
                                            {modeBoleh(req).length > 0 && (
                                                <Tooltip content="Penuhi persyaratan" align="right">
                                                    <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0" aria-label={`Penuhi ${req.nama}`} onClick={() => buka(req)}>
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
                </section>

                <section aria-labelledby={`${id}-terlampir`}>
                    <h4 id={`${id}-terlampir`} className="px-6 py-3 text-sm font-semibold text-ink">Berkas terlampir ({terlampir.length})</h4>
                    {terlampir.length === 0 ? (
                        <p className="px-6 pb-4 text-sm text-muted">Belum ada berkas yang dilampirkan.</p>
                    ) : (
                        <ul aria-labelledby={`${id}-terlampir`} className="space-y-3 px-6 pb-6">
                            {terlampir.map(({ req, item }) => (
                                <BerkasLampiranItem
                                    key={item.id}
                                    berkas={{ ...item, mime: null, download_url: item.mode === 'file' ? `/kegiatan/${kegiatanId}/${tahap === 'pelaksanaan' ? 'pelaksanaan/' : ''}berkas/${item.id}` : null }}
                                    meta={item.created_at ? `${req.nama} · ${formatTanggal(item.created_at, { withTime: true })}` : req.nama}
                                    action={(!alasan_tolak_ganti || !alasan_tolak_hapus) && <div className="flex gap-2">
                                        {!alasan_tolak_ganti && (
                                            <Tooltip content="Ganti berkas" align="right">
                                                <Button type="button" variant="outline" size="sm" className="h-8 w-8 p-0" aria-label={`Ganti berkas ${namaBerkas(req, item)}`} onClick={() => buka(req, item)}>
                                                    <RefreshCw className="h-4 w-4" aria-hidden="true" />
                                                </Button>
                                            </Tooltip>
                                        )}
                                        {!alasan_tolak_hapus && (
                                            <Tooltip content="Hapus berkas" align="right">
                                                <Button type="button" variant="outline" size="sm" className="h-8 w-8 p-0 text-danger hover:bg-danger/10"
                                                    aria-label={`Hapus berkas ${namaBerkas(req, item)}`}
                                                    onClick={() => { setGalatHapus(undefined); setAlasanHapus(''); setHapus(item); }}>
                                                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                </Button>
                                            </Tooltip>
                                        )}
                                    </div>}
                                />
                            ))}
                        </ul>
                    )}
                    {alasan_tolak_hapus && !alasan_tolak_unggah && terlampir.length > 0 && (
                        <p className="px-6 pb-4 text-xs text-muted">{alasan_tolak_hapus}</p>
                    )}
                </section>
            </CardContent>

            {/* Selama unggahan berjalan modal tidak boleh ditutup agar hasil server tetap terlihat. */}
            <Modal
                isOpen={target !== null}
                onClose={() => { if (!processing) tutup(); }}
                title={target?.ganti ? `Ganti berkas ${target.req.nama}` : `Penuhi ${target?.req.nama ?? 'persyaratan'}`}
                size="lg"
                footer={
                    <div className="flex w-full items-center justify-end gap-3">
                        <Button type="button" variant="outline" disabled={processing} onClick={tutup}>Batal</Button>
                        <Button type="submit" form="berkas-kegiatan-form" variant="primary" isLoading={processing} disabled={processing || data.lampiran.length === 0}>Simpan berkas</Button>
                    </div>
                }
            >
                {target && (
                    <form id="berkas-kegiatan-form" onSubmit={kirim} className="space-y-4" aria-busy={processing}>
                        <LampiranDraftFields
                            items={data.lampiran}
                            errors={{
                                'lampiran.0.mode': fieldErrors.mode,
                                'lampiran.0.file': fieldErrors.file,
                                'lampiran.0.tautan': fieldErrors.tautan,
                                'lampiran.0.isi_teks': fieldErrors.isi_teks,
                            }}
                            onChange={(update) => setData('lampiran', update(data.lampiran))}
                            title="Lampiran Dokumen"
                            modeDescriptions={{
                                file: `Format ${target.req.format_diizinkan}; maksimal ${ukuran(target.req.ukuran_maks_kb)}.`,
                                tautan: 'Gunakan alamat http atau https.',
                                teks: 'Tuliskan berkas dalam bentuk teks.',
                            }}
                            placeholders={{ tautan: 'https://', teks: '' }}
                            emptyText="Belum ada lampiran yang ditambahkan"
                            accept={target.req.format_diizinkan.split(',').map((format) => `.${format.trim()}`).join(',')}
                            disabled={processing}
                            maxItems={1}
                            modes={modeBoleh(target.req)}
                        />
                        {target.ganti && (
                            <Textarea id="berkas-kegiatan-alasan-koreksi" label="Alasan penggantian" rows={2} required disabled={processing}
                                value={data.alasan_koreksi} onChange={(event) => setData('alasan_koreksi', event.target.value)} error={fieldErrors.alasan_koreksi} />
                        )}
                        {galatBisnis(fieldErrors) && <p role="alert" className="text-sm text-danger">{galatBisnis(fieldErrors)}</p>}
                    </form>
                )}
            </Modal>

            <AuditReasonModal
                open={hapus !== null}
                title={`Hapus berkas ${tahap}`}
                description={`Berkas "${hapus?.nama_asli ?? `${tahap} kegiatan`}" akan dihapus dari kegiatan ini.`}
                reason={alasanHapus}
                error={galatHapus}
                busy={menghapus}
                submitDisabled={alasanHapus.trim().length < 3}
                confirmLabel="Hapus"
                destructive
                onReasonChange={setAlasanHapus}
                onClose={() => { if (!menghapus) { setHapus(null); setAlasanHapus(''); } }}
                onConfirm={konfirmasiHapus}
            />
        </Card>
    );
}
