import React, { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import {
    FileText,
    Link as LinkIcon,
    AlignLeft,
    Upload,
    Trash2,
    Download,
    ExternalLink,
    CheckCircle2,
    AlertCircle,
    Plus,
    Clock,
    FileCheck,
} from 'lucide-react';
import { Button } from '@/Components/Button';
import { Modal } from '@/Components/Modal';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Select } from '@/Components/Select';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import type {
    BuktiMode,
    BuktiRencanaAksiPageProps,
    ItemBuktiRencanaAksi,
    PersyaratanBuktiRencanaAksi,
} from '@/types/rencana-aksi';

export interface BuktiRencanaAksiPanelProps extends BuktiRencanaAksiPageProps {
    className?: string;
}

export function BuktiRencanaAksiPanel({
    rencanaAksi,
    persyaratan,
    summary,
    daftarBukti,
    storageSettings,
    can,
    className = '',
}: BuktiRencanaAksiPanelProps) {
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [selectedRequirementId, setSelectedRequirementId] = useState<string>('');
    const [selectedDeleteTarget, setSelectedDeleteTarget] = useState<ItemBuktiRencanaAksi | null>(null);
    const [deleteReason, setDeleteReason] = useState('');
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | undefined>();

    const activeRequirement = persyaratan.find((p) => p.id === selectedRequirementId);

    const defaultMode: BuktiMode = activeRequirement
        ? activeRequirement.izinkan_file && storageSettings.unggahan_aktif
            ? 'file'
            : activeRequirement.izinkan_tautan
            ? 'tautan'
            : 'teks'
        : 'file';

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<{
        jenis_berkas_id: string;
        mode: BuktiMode;
        file: File | null;
        tautan: string;
        isi_teks: string;
        menggantikan_id: string;
        alasan_koreksi: string;
    }>({
        jenis_berkas_id: '',
        mode: defaultMode,
        file: null,
        tautan: '',
        isi_teks: '',
        menggantikan_id: '',
        alasan_koreksi: '',
    });

    const openAddModal = (reqId?: string) => {
        clearErrors();
        const initialReqId = reqId || (persyaratan.length > 0 ? persyaratan[0].id : '');
        const targetReq = persyaratan.find((p) => p.id === initialReqId);

        let initialMode: BuktiMode = 'file';
        if (targetReq) {
            if (targetReq.izinkan_file && storageSettings.unggahan_aktif) {
                initialMode = 'file';
            } else if (targetReq.izinkan_tautan) {
                initialMode = 'tautan';
            } else if (targetReq.izinkan_teks) {
                initialMode = 'teks';
            }
        }

        setSelectedRequirementId(initialReqId);
        setData({
            jenis_berkas_id: initialReqId,
            mode: initialMode,
            file: null,
            tautan: '',
            isi_teks: '',
            menggantikan_id: '',
            alasan_koreksi: '',
        });
        setIsAddModalOpen(true);
    };

    const handleRequirementChange = (reqId: string) => {
        setSelectedRequirementId(reqId);
        setData('jenis_berkas_id', reqId);
        const req = persyaratan.find((p) => p.id === reqId);
        if (req) {
            if (data.mode === 'file' && (!req.izinkan_file || !storageSettings.unggahan_aktif)) {
                setData('mode', req.izinkan_tautan ? 'tautan' : 'teks');
            } else if (data.mode === 'tautan' && !req.izinkan_tautan) {
                setData('mode', req.izinkan_teks ? 'teks' : 'file');
            } else if (data.mode === 'teks' && !req.izinkan_teks) {
                setData('mode', req.izinkan_file && storageSettings.unggahan_aktif ? 'file' : 'tautan');
            }
        }
    };

    const handleSubmitEvidence = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/rencana-aksi/${rencanaAksi.id}/bukti`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsAddModalOpen(false);
                reset();
            },
        });
    };

    const confirmDelete = () => {
        if (!selectedDeleteTarget) return;
        if (!deleteReason.trim()) {
            setDeleteError('Alasan penghapusan wajib diisi.');
            return;
        }

        setIsDeleting(true);
        setDeleteError(undefined);

        router.delete(`/rencana-aksi/${rencanaAksi.id}/bukti/${selectedDeleteTarget.id}`, {
                data: { alasan: deleteReason },
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedDeleteTarget(null);
                    setDeleteReason('');
                    setIsDeleting(false);
                },
                onError: (errs) => {
                    setIsDeleting(false);
                    setDeleteError(errs.alasan || errs.rencana_aksi || 'Penghapusan bukti gagal.');
                },
            }
        );
    };

    const formatBytes = (bytes: number | null): string => {
        if (!bytes) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    };

    return (
        <div className={`space-y-6 ${className}`}>
            {/* Header Informasi Konteks */}
            <Card>
                <CardHeader>
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <CardTitle className="text-base sm:text-lg font-bold text-ink">
                                {rencanaAksi.indikator
                                    ? `${rencanaAksi.indikator.kode} · ${rencanaAksi.indikator.nama}`
                                    : 'Indikator Kinerja'}
                            </CardTitle>
                            <Badge status={rencanaAksi.status_alur} />
                            {rencanaAksi.is_disahkan && (
                                <Badge variant="muted" size="sm">Terkunci</Badge>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {summary.lengkap ? (
                            <Badge variant="success" size="md" className="gap-1.5 py-1 px-3">
                                <CheckCircle2 className="w-4 h-4" />
                                <span>Lengkap ({summary.terpenuhi_wajib}/{summary.total_wajib})</span>
                            </Badge>
                        ) : (
                            <Badge variant="warning" size="md" className="gap-1.5 py-1 px-3">
                                <AlertCircle className="w-4 h-4" />
                                <span>Belum Lengkap ({summary.terpenuhi_wajib}/{summary.total_wajib})</span>
                            </Badge>
                        )}

                        {can.upload && !rencanaAksi.is_disahkan && (
                            <Button
                                id="btn-tambah-bukti"
                                variant="primary"
                                size="sm"
                                onClick={() => openAddModal()}
                                className="gap-1.5"
                            >
                                <Plus className="w-4 h-4" />
                                <span>Tambah Bukti</span>
                            </Button>
                        )}
                    </div>
                </CardHeader>

                <CardContent className="bg-soft/30">
                    <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <dt className="text-xs font-semibold text-muted">Unit Pengelola</dt>
                            <dd className="mt-1 text-sm font-medium text-ink">{rencanaAksi.unit?.nama || '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold text-muted">Penanggung Jawab</dt>
                            <dd className="mt-1 text-sm font-medium text-ink">{rencanaAksi.penanggungJawab?.nama || '-'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold text-muted">Periode & Versi</dt>
                            <dd className="mt-1 text-sm font-medium text-ink">
                                Tahun {rencanaAksi.tahun} · Versi {rencanaAksi.versi}
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            {/* Daftar Persyaratan Bukti */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileCheck className="w-4 h-4 text-primary" />
                        <span>Persyaratan Bukti Dukung</span>
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    {persyaratan.length === 0 ? (
                        <div className="p-6 text-center text-sm text-muted">
                            Tidak ada persyaratan bukti bertahap rencana aksi yang dikonfigurasi.
                        </div>
                    ) : (
                        <div className="divide-y divide-border">
                            {persyaratan.map((req) => (
                                <div
                                    key={req.id}
                                    className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-soft/20 transition-colors"
                                >
                                    <div className="space-y-1.5 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="font-semibold text-sm text-ink">{req.nama}</span>
                                            {req.wajib ? (
                                                <Badge variant="danger" size="sm">Wajib</Badge>
                                            ) : (
                                                <Badge variant="muted" size="sm">Opsional</Badge>
                                            )}
                                            {req.semua_mode_wajib && (
                                                <Badge variant="info" size="sm">Semua Mode Wajib</Badge>
                                            )}
                                        </div>

                                        {req.keterangan && (
                                            <p className="text-xs text-muted">{req.keterangan}</p>
                                        )}

                                        <div className="flex items-center gap-3 text-xs text-ink-light pt-1">
                                            <span>Mode diizinkan:</span>
                                            <span className="flex items-center gap-2">
                                                {req.izinkan_file && (
                                                    <span className="inline-flex items-center gap-1 font-medium text-ink">
                                                        <FileText className="w-3.5 h-3.5 text-primary" /> File
                                                    </span>
                                                )}
                                                {req.izinkan_tautan && (
                                                    <span className="inline-flex items-center gap-1 font-medium text-ink">
                                                        <LinkIcon className="w-3.5 h-3.5 text-info" /> Tautan
                                                    </span>
                                                )}
                                                {req.izinkan_teks && (
                                                    <span className="inline-flex items-center gap-1 font-medium text-ink">
                                                        <AlignLeft className="w-3.5 h-3.5 text-warning-dark" /> Teks
                                                    </span>
                                                )}
                                            </span>
                                        </div>

                                        {req.pemenuhan.tidak_dapat_dipenuhi && (
                                            <p className="text-xs text-info font-medium">
                                                Persyaratan ditandai tidak dapat dipenuhi (unggahan dinonaktifkan).
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-3 shrink-0">
                                        {req.pemenuhan.terpenuhi ? (
                                            <Badge variant="success" size="sm" className="gap-1 py-1">
                                                <CheckCircle2 className="w-3.5 h-3.5" />
                                                <span>Terpenuhi</span>
                                            </Badge>
                                        ) : req.pemenuhan.tidak_dapat_dipenuhi ? (
                                            <Badge variant="info" size="sm" className="gap-1 py-1">
                                                <span>Dikecualikan</span>
                                            </Badge>
                                        ) : (
                                            <Badge variant="warning" size="sm" className="gap-1 py-1">
                                                <AlertCircle className="w-3.5 h-3.5" />
                                                <span>Kurang</span>
                                            </Badge>
                                        )}

                                        {can.upload && !rencanaAksi.is_disahkan && (
                                            <Button
                                                id={`btn-penuhi-${req.id}`}
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openAddModal(req.id)}
                                            >
                                                Penuhi
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Daftar Bukti Terunggah */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Upload className="w-4 h-4 text-primary" />
                        <span>Daftar Bukti Terlampir ({daftarBukti.length})</span>
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    {daftarBukti.length === 0 ? (
                        <div className="py-12 px-6 text-center text-muted">
                            <FileText className="w-8 h-8 mx-auto mb-2 opacity-40" />
                            <p className="text-sm font-medium">Belum ada bukti dukung yang dilampirkan.</p>
                        </div>
                    ) : (
                        <div className="divide-y divide-border">
                            {daftarBukti.map((bukti) => (
                                <div
                                    key={bukti.id}
                                    className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-start justify-between gap-4"
                                >
                                    <div className="space-y-2 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            {bukti.mode === 'file' && (
                                                <Badge variant="primary" size="sm" className="gap-1">
                                                    <FileText className="w-3 h-3" /> File
                                                </Badge>
                                            )}
                                            {bukti.mode === 'tautan' && (
                                                <Badge variant="info" size="sm" className="gap-1">
                                                    <LinkIcon className="w-3 h-3" /> Tautan
                                                </Badge>
                                            )}
                                            {bukti.mode === 'teks' && (
                                                <Badge variant="warning" size="sm" className="gap-1">
                                                    <AlignLeft className="w-3 h-3" /> Teks
                                                </Badge>
                                            )}

                                            <span className="font-semibold text-sm text-ink">
                                                {bukti.nama_persyaratan || 'Lampiran Bebas'}
                                            </span>

                                            {bukti.menggantikan_id && (
                                                <Badge variant="muted" size="sm">Koreksi Pengganti</Badge>
                                            )}
                                        </div>

                                        {/* Konten Berdasarkan Mode */}
                                        {bukti.mode === 'file' && (
                                             <div className="flex items-center gap-3 text-sm">
                                                <span className="font-medium text-ink">{bukti.nama_asli}</span>
                                                <span className="text-xs text-muted">({formatBytes(bukti.ukuran_bytes)})</span>
                                            </div>
                                        )}

                                        {bukti.mode === 'tautan' && bukti.tautan && (
                                            <a
                                                href={bukti.tautan}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1.5 text-sm text-primary hover:underline font-medium break-all"
                                            >
                                                <span>{bukti.tautan}</span>
                                                <ExternalLink className="w-3.5 h-3.5 shrink-0" />
                                            </a>
                                        )}

                                        {bukti.mode === 'teks' && bukti.isi_teks && (
                                            <div className="rounded-lg bg-soft/60 border border-border/70 p-3 text-sm text-ink whitespace-pre-wrap">
                                                {bukti.isi_teks}
                                            </div>
                                        )}

                                        {bukti.alasan_koreksi && (
                                            <p className="text-xs text-muted italic">
                                                Alasan koreksi: {bukti.alasan_koreksi}
                                            </p>
                                        )}

                                        <div className="flex items-center gap-2 text-xs text-muted pt-1">
                                            <Clock className="w-3.5 h-3.5" />
                                            <span>
                                                Oleh {bukti.pengunggah_nama || 'Pengguna'} pada{' '}
                                                {bukti.created_at ? new Date(bukti.created_at).toLocaleDateString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                }) : '-'}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Aksi Baris */}
                                    <div className="flex items-center gap-2 shrink-0">
                                        {bukti.mode === 'file' && (
                                            <a
                                                href={`/rencana-aksi/${rencanaAksi.id}/bukti/${bukti.id}/unduh`}
                                                download
                                                className="inline-flex items-center justify-center h-9 px-3 text-xs font-semibold rounded-lg border border-border bg-surface text-ink hover:bg-soft transition-colors gap-1.5"
                                            >
                                                <Download className="w-3.5 h-3.5" />
                                                <span>Unduh</span>
                                            </a>
                                        )}

                                        {can.delete && !rencanaAksi.is_disahkan && (
                                            <Button
                                                id={`btn-hapus-bukti-${bukti.id}`}
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    setSelectedDeleteTarget(bukti);
                                                    setDeleteReason('');
                                                    setDeleteError(undefined);
                                                }}
                                                className="text-danger hover:bg-danger/10 hover:text-danger"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                                <span className="sr-only">Hapus Bukti</span>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Modal Tambah Bukti */}
            <Modal
                isOpen={isAddModalOpen}
                onClose={() => {
                    setIsAddModalOpen(false);
                    reset();
                }}
                title="Tambah Bukti Dukung Rencana Aksi"
                size="lg"
            >
                <form onSubmit={handleSubmitEvidence} className="space-y-4">
                    {/* Pemilihan Persyaratan */}
                    <Select
                        id="jenis_berkas_id"
                        label="Persyaratan Bukti"
                        value={data.jenis_berkas_id}
                        onChange={(e) => handleRequirementChange(e.target.value)}
                        error={errors.jenis_berkas_id}
                    >
                        <option value="">Lampiran Bebas (Tanpa Persyaratan)</option>
                        {persyaratan.map((req) => (
                            <option key={req.id} value={req.id}>
                                {req.nama} {req.wajib ? '(Wajib)' : '(Opsional)'}
                            </option>
                        ))}
                    </Select>

                    {/* Mode Bukti */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-ink">
                            Pilih Mode Bukti
                        </label>
                        <div className="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                disabled={Boolean(activeRequirement && (!activeRequirement.izinkan_file || !storageSettings.unggahan_aktif))}
                                onClick={() => setData('mode', 'file')}
                                className={`h-10 rounded-lg border text-sm font-medium flex items-center justify-center gap-2 transition-colors ${
                                    data.mode === 'file'
                                        ? 'border-primary bg-primary/10 text-primary font-semibold'
                                        : 'border-border bg-surface text-ink hover:bg-soft'
                                } disabled:opacity-40 disabled:cursor-not-allowed`}
                            >
                                <FileText className="w-4 h-4" />
                                <span>File</span>
                            </button>

                            <button
                                type="button"
                                disabled={Boolean(activeRequirement && !activeRequirement.izinkan_tautan)}
                                onClick={() => setData('mode', 'tautan')}
                                className={`h-10 rounded-lg border text-sm font-medium flex items-center justify-center gap-2 transition-colors ${
                                    data.mode === 'tautan'
                                        ? 'border-primary bg-primary/10 text-primary font-semibold'
                                        : 'border-border bg-surface text-ink hover:bg-soft'
                                } disabled:opacity-40 disabled:cursor-not-allowed`}
                            >
                                <LinkIcon className="w-4 h-4" />
                                <span>Tautan</span>
                            </button>

                            <button
                                type="button"
                                disabled={Boolean(activeRequirement && !activeRequirement.izinkan_teks)}
                                onClick={() => setData('mode', 'teks')}
                                className={`h-10 rounded-lg border text-sm font-medium flex items-center justify-center gap-2 transition-colors ${
                                    data.mode === 'teks'
                                        ? 'border-primary bg-primary/10 text-primary font-semibold'
                                        : 'border-border bg-surface text-ink hover:bg-soft'
                                } disabled:opacity-40 disabled:cursor-not-allowed`}
                            >
                                <AlignLeft className="w-4 h-4" />
                                <span>Teks</span>
                            </button>
                        </div>
                        {errors.mode && <p className="mt-1 text-xs text-danger">{errors.mode}</p>}
                    </div>

                    {/* Input Mode: File */}
                    {data.mode === 'file' && (
                        <div>
                            <label htmlFor="file-input" className="mb-1.5 block text-sm font-medium text-ink">
                                Berkas Fisik
                            </label>
                            <input
                                id="file-input"
                                type="file"
                                onChange={(e) => setData('file', e.target.files ? e.target.files[0] : null)}
                                className="w-full text-sm text-ink file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90"
                            />
                            <p className="mt-1 text-xs text-muted">
                                Format: {activeRequirement?.format_diizinkan || storageSettings.format_diizinkan}.
                                Ukuran maksimal: {activeRequirement?.ukuran_maks_kb || storageSettings.ukuran_maks_kb} KB.
                            </p>
                            {errors.file && <p className="mt-1 text-xs text-danger">{errors.file}</p>}
                        </div>
                    )}

                    {/* Input Mode: Tautan */}
                    {data.mode === 'tautan' && (
                        <Input
                            id="tautan-input"
                            label="Tautan URL"
                            type="url"
                            placeholder="https://contoh.lldikti16.kemdikbud.go.id/dokumen"
                            value={data.tautan}
                            onChange={(e) => setData('tautan', e.target.value)}
                            error={errors.tautan}
                            helperText="Gunakan alamat http atau https."
                            required
                        />
                    )}

                    {/* Input Mode: Teks */}
                    {data.mode === 'teks' && (
                        <Textarea
                            id="teks-input"
                            label="Keterangan Teks"
                            rows={4}
                            placeholder="Tuliskan ringkasan dasar dokumen atau penjelasan bukti dukung..."
                            value={data.isi_teks}
                            onChange={(e) => setData('isi_teks', e.target.value)}
                            error={errors.isi_teks}
                            required
                        />
                    )}

                    {/* Opsi Koreksi Pengganti */}
                    {daftarBukti.length > 0 && (
                        <div className="pt-3 border-t border-border space-y-3">
                            <Select
                                id="menggantikan_id"
                                label="Gantikan Bukti Sebelumnya"
                                value={data.menggantikan_id}
                                onChange={(e) => setData('menggantikan_id', e.target.value)}
                                error={errors.menggantikan_id}
                                helperText="Pilih hanya jika ingin mengoreksi bukti sebelumnya."
                            >
                                <option value="">Bukti Baru (Tanpa Menggantikan)</option>
                                {daftarBukti.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        [{b.mode.toUpperCase()}] {b.nama_asli || b.tautan || (b.isi_teks ? b.isi_teks.substring(0, 40) + '...' : b.id)}
                                    </option>
                                ))}
                            </Select>

                            {data.menggantikan_id && (
                                <Textarea
                                    id="alasan_koreksi"
                                    label="Alasan Koreksi"
                                    rows={2}
                                    placeholder="Jelaskan alasan penggantian bukti sebelumnya..."
                                    value={data.alasan_koreksi}
                                    onChange={(e) => setData('alasan_koreksi', e.target.value)}
                                    error={errors.alasan_koreksi}
                                    required
                                />
                            )}
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-4 border-t border-border">
                        <Button
                            type="button"
                            variant="outline"
                            size="md"
                            disabled={processing}
                            onClick={() => {
                                setIsAddModalOpen(false);
                                reset();
                            }}
                        >
                            Batal
                        </Button>
                        <Button
                            id="btn-submit-bukti"
                            type="submit"
                            variant="primary"
                            size="md"
                            isLoading={processing}
                            disabled={processing}
                        >
                            Simpan Bukti
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Modal Alasan Penghapusan (Audit) */}
            <AuditReasonModal
                open={selectedDeleteTarget !== null}
                title="Hapus Bukti Dukung"
                description={`Apakah Anda yakin ingin menghapus bukti dukung ini? Tindakan ini akan dicatat dalam audit log.`}
                reason={deleteReason}
                error={deleteError}
                busy={isDeleting}
                confirmLabel="Hapus Bukti"
                destructive
                onReasonChange={setDeleteReason}
                onClose={() => {
                    setSelectedDeleteTarget(null);
                    setDeleteReason('');
                    setDeleteError(undefined);
                }}
                onConfirm={confirmDelete}
            />
        </div>
    );
}
