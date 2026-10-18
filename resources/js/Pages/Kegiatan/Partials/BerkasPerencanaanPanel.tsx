import { useState, type FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Download, Trash2 } from 'lucide-react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Textarea } from '@/Components/Textarea';
import type { BerkasKegiatanItem, BerkasKegiatanMode, BerkasPerencanaanKegiatan, PersyaratanPerencanaanKegiatan } from '@/types/kegiatan';

interface Props {
    kegiatanId: string;
    berkas: BerkasPerencanaanKegiatan;
}

const MODE: BerkasKegiatanMode[] = ['file', 'tautan', 'teks'];
const LABEL_MODE: Record<BerkasKegiatanMode, string> = { file: 'File', tautan: 'Tautan', teks: 'Teks' };
const modeBoleh = (req: PersyaratanPerencanaanKegiatan) => MODE.filter((mode) => req[`izinkan_${mode}`]);

/** Galat gerbang tulis (422) yang tidak terikat ke field form. */
const galatBisnis = (errs: Record<string, string | undefined>) => errs.berkas ?? errs.tahun ?? errs.unit_id ?? errs.jenis_berkas_id ?? errs.menggantikan_id;

/** Berkas tahap perencanaan kegiatan, mis. RAB (Q42 §42.3); diperiksa saat rencana aksi pengklaim diajukan. */
export function BerkasPerencanaanPanel({ kegiatanId, berkas }: Props) {
    const { persyaratan, alasan_tolak_unggah, alasan_tolak_hapus } = berkas;
    const [target, setTarget] = useState<{ req: PersyaratanPerencanaanKegiatan; ganti: BerkasKegiatanItem | null } | null>(null);
    const [hapus, setHapus] = useState<BerkasKegiatanItem | null>(null);
    const [alasanHapus, setAlasanHapus] = useState('');
    const [galatHapus, setGalatHapus] = useState<string | undefined>();
    const [menghapus, setMenghapus] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<{
        jenis_berkas_id: string; mode: BerkasKegiatanMode; file: File | null; tautan: string; isi_teks: string; menggantikan_id: string; alasan_koreksi: string;
    }>({ jenis_berkas_id: '', mode: 'file', file: null, tautan: '', isi_teks: '', menggantikan_id: '', alasan_koreksi: '' });
    const fieldErrors = errors as Record<string, string | undefined>;

    if (persyaratan.length === 0) return null;

    const buka = (req: PersyaratanPerencanaanKegiatan, ganti: BerkasKegiatanItem | null = null) => {
        clearErrors();
        setData({ jenis_berkas_id: req.id, mode: ganti && req[`izinkan_${ganti.mode}`] ? ganti.mode : (modeBoleh(req)[0] ?? 'file'),
            file: null, tautan: '', isi_teks: '', menggantikan_id: ganti?.id ?? '', alasan_koreksi: '' });
        setTarget({ req, ganti });
    };
    const tutup = () => { setTarget(null); reset(); clearErrors(); };

    const kirim = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (processing) return;
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

    return (
        <Card>
            <CardHeader>
                <CardTitle>Berkas perencanaan kegiatan</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-sm text-muted">Persyaratan wajib diperiksa saat rencana aksi yang mengklaim kegiatan ini diajukan.</p>
                {alasan_tolak_unggah && <p className="text-sm text-muted">{alasan_tolak_unggah}</p>}
                <ul className="space-y-4">
                    {persyaratan.map((req) => (
                        <li key={req.id} className="space-y-3 rounded-lg border border-border px-4 py-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0 space-y-1">
                                    <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink">
                                        <span>{req.nama}</span>
                                        <Badge variant={req.wajib ? 'danger' : 'muted'} size="sm">{req.wajib ? 'Wajib' : 'Opsional'}</Badge>
                                        {req.hanya_kegiatan_beranggaran && <Badge variant="info" size="sm">Kegiatan beranggaran</Badge>}
                                    </p>
                                    {req.keterangan && <p className="text-xs text-muted">{req.keterangan}</p>}
                                    <p className="text-xs text-muted">
                                        Mode diizinkan: {modeBoleh(req).map((mode) => LABEL_MODE[mode]).join(', ')}
                                        {req.izinkan_file && ` · ${req.format_diizinkan}, maks. ${req.ukuran_maks_kb} KB`}
                                    </p>
                                    {req.contoh.map((contoh) => (
                                        <a key={contoh.id} href={`/jenis-berkas/${req.id}/contoh/${contoh.id}`} className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                            <Download className="h-3.5 w-3.5" aria-hidden="true" />
                                            Contoh: {contoh.nama_asli ?? 'berkas'}
                                        </a>
                                    ))}
                                </div>
                                <div className="flex shrink-0 items-center gap-3">
                                    <Badge variant={req.pemenuhan.terpenuhi ? 'success' : 'warning'} size="sm">
                                        {req.pemenuhan.terpenuhi ? 'Terpenuhi' : `Kurang: ${req.pemenuhan.mode_kurang.map((mode) => LABEL_MODE[mode]).join(', ')}`}
                                    </Badge>
                                    {!alasan_tolak_unggah && (
                                        <Button type="button" variant="outline" size="sm" onClick={() => buka(req)}>Unggah</Button>
                                    )}
                                </div>
                            </div>
                            {req.berkas.length > 0 && (
                                <ul className="space-y-2">
                                    {req.berkas.map((item) => (
                                        <BerkasLampiranItem
                                            key={item.id}
                                            berkas={{ ...item, mime: null, download_url: item.mode === 'file' ? `/kegiatan/${kegiatanId}/berkas/${item.id}` : null }}
                                            action={<>
                                                {!alasan_tolak_unggah && (
                                                    <Button type="button" variant="ghost" size="sm" onClick={() => buka(req, item)}
                                                        aria-label={`Ganti berkas ${item.nama_asli ?? req.nama}`}>Ganti</Button>
                                                )}
                                                {!alasan_tolak_hapus && (
                                                    <Button type="button" variant="ghost" size="sm" className="text-danger hover:bg-danger/10 hover:text-danger"
                                                        aria-label={`Hapus berkas ${item.nama_asli ?? req.nama}`}
                                                        onClick={() => { setGalatHapus(undefined); setAlasanHapus(''); setHapus(item); }}>
                                                        <Trash2 className="h-4 w-4" aria-hidden="true" />
                                                    </Button>
                                                )}
                                            </>}
                                        />
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ul>
                {alasan_tolak_hapus && !alasan_tolak_unggah && persyaratan.some((req) => req.berkas.length > 0) && (
                    <p className="text-xs text-muted">{alasan_tolak_hapus}</p>
                )}
            </CardContent>

            {/* Selama unggahan berjalan modal tidak boleh ditutup agar hasil server tetap terlihat. */}
            <Modal isOpen={target !== null} onClose={() => { if (!processing) tutup(); }} title={target?.ganti ? `Ganti berkas ${target.req.nama}` : `Unggah ${target?.req.nama ?? 'berkas'}`} size="lg">
                {target && (
                    <form onSubmit={kirim} className="space-y-4" aria-busy={processing}>
                        <div>
                            <p id="berkas-kegiatan-mode-label" className="mb-1.5 block text-sm font-medium text-ink">Mode berkas</p>
                            <div role="group" aria-labelledby="berkas-kegiatan-mode-label" className="flex flex-wrap gap-2">
                                {modeBoleh(target.req).map((mode) => (
                                    <Button key={mode} type="button" size="sm" variant={data.mode === mode ? 'primary' : 'outline'} aria-pressed={data.mode === mode}
                                        disabled={processing} onClick={() => setData('mode', mode)}>{LABEL_MODE[mode]}</Button>
                                ))}
                            </div>
                            {fieldErrors.mode && <p className="mt-1 text-xs text-danger" role="alert">{fieldErrors.mode}</p>}
                        </div>
                        {data.mode === 'file' && (
                            <div>
                                <label htmlFor="berkas-kegiatan-file" className="mb-1.5 block text-sm font-medium text-ink">Berkas</label>
                                <input id="berkas-kegiatan-file" type="file" required disabled={processing}
                                    aria-invalid={fieldErrors.file ? true : undefined}
                                    aria-describedby={fieldErrors.file ? 'berkas-kegiatan-file-keterangan berkas-kegiatan-file-error' : 'berkas-kegiatan-file-keterangan'}
                                    onChange={(event) => setData('file', event.target.files?.[0] ?? null)}
                                    className="block w-full text-sm text-ink file:mr-4 file:rounded-lg file:border-0 file:bg-primary file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-dark" />
                                <p id="berkas-kegiatan-file-keterangan" className="mt-1 text-xs text-muted">
                                    Format: {target.req.format_diizinkan}. Ukuran maksimal {target.req.ukuran_maks_kb} KB.
                                </p>
                                {fieldErrors.file && <p id="berkas-kegiatan-file-error" className="mt-1 text-xs text-danger" role="alert">{fieldErrors.file}</p>}
                            </div>
                        )}
                        {data.mode === 'tautan' && (
                            <Input id="berkas-kegiatan-tautan" label="Tautan" type="url" required disabled={processing} placeholder="https://"
                                value={data.tautan} onChange={(event) => setData('tautan', event.target.value)} error={fieldErrors.tautan} helperText="Gunakan alamat http atau https." />
                        )}
                        {data.mode === 'teks' && (
                            <Textarea id="berkas-kegiatan-teks" label="Keterangan" rows={4} required disabled={processing}
                                value={data.isi_teks} onChange={(event) => setData('isi_teks', event.target.value)} error={fieldErrors.isi_teks} />
                        )}
                        {target.ganti && (
                            <Textarea id="berkas-kegiatan-alasan-koreksi" label="Alasan penggantian" rows={2} required disabled={processing}
                                value={data.alasan_koreksi} onChange={(event) => setData('alasan_koreksi', event.target.value)} error={fieldErrors.alasan_koreksi} />
                        )}
                        {galatBisnis(fieldErrors) && <p role="alert" className="text-sm text-danger">{galatBisnis(fieldErrors)}</p>}
                        <div className="flex justify-end gap-2 border-t border-border pt-4">
                            <Button type="button" variant="outline" disabled={processing} onClick={tutup}>Batal</Button>
                            <Button type="submit" variant="primary" isLoading={processing} disabled={processing}>Simpan berkas</Button>
                        </div>
                    </form>
                )}
            </Modal>

            <AuditReasonModal
                open={hapus !== null}
                title="Hapus berkas perencanaan"
                description={`Berkas "${hapus?.nama_asli ?? 'perencanaan kegiatan'}" akan dihapus dari kegiatan ini.`}
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
