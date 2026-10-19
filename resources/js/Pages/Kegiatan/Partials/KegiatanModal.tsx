import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { bantuanAnggaran } from '@/lib/rupiah';
import type { KegiatanOption } from '@/types/kegiatan';

export interface KegiatanModalProps {
    isOpen: boolean;
    onClose: () => void;
    unitOptions: KegiatanOption[];
    periodeOptions: KegiatanOption[];
    tahunDefault?: number;
}

export function KegiatanModal({
    isOpen,
    onClose,
    unitOptions,
    periodeOptions,
    tahunDefault = new Date().getFullYear(),
}: KegiatanModalProps) {
    const initialUnitId = unitOptions[0]?.id ?? '';
    const initialPeriodeId = periodeOptions[0]?.id ?? '';
    const initialized = useRef(false);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
        transform,
    } = useForm({
        unit_id: '',
        tahun: String(tahunDefault),
        periode_id: '',
        nama: '',
        tujuan: '',
        sasaran_peserta: '',
        keterangan_peserta: '',
        lokasi: '',
        tanggal_rencana: '',
        anggaran: '',
    });

    const [failure, setFailure] = useState('');
    const summaryRef = useRef<HTMLDivElement>(null);
    const recovery = useAuthRecovery();
    const blocked = Boolean(failure || recovery.recovery);
    const hasErrors = Object.keys(errors).length > 0;

    useEffect(() => {
        if (!isOpen) {
            initialized.current = false;
            return;
        }
        // Refresh opsi/validasi bukan sesi form baru: koreksi dan error pengguna tetap utuh.
        if (initialized.current) return;
        initialized.current = true;

        setFailure('');
        clearErrors();
        setData({
            unit_id: initialUnitId,
            tahun: String(tahunDefault),
            periode_id: initialPeriodeId,
            nama: '',
            tujuan: '',
            sasaran_peserta: '',
            keterangan_peserta: '',
            lokasi: '',
            tanggal_rencana: '',
            anggaran: '',
        });
    }, [isOpen, initialUnitId, initialPeriodeId, tahunDefault, setData, clearErrors]);

    const handleClose = () => {
        if (processing || blocked) return;
        setFailure('');
        clearErrors();
        reset();
        onClose();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (processing || blocked) return;

        transform((formData) => ({
            ...formData,
            tahun: Number(formData.tahun),
            sasaran_peserta: formData.sasaran_peserta === '' ? null : Number(formData.sasaran_peserta),
            anggaran: formData.anggaran === '' ? null : formData.anggaran,
        }));

        const endpoint = '/kegiatan';

        post(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                clearErrors();
                onClose();
            },
            onError: () => {
                requestAnimationFrame(() => summaryRef.current?.focus());
            },
            onHttpException: (response) => {
                if (
                    !recovery.handleHttpException(response, {
                        effectiveMethod: 'post',
                        path: endpoint,
                        mutation: true,
                    })
                ) {
                    setFailure(
                        response.status === 403
                            ? 'Hasil penyimpanan belum terkonfirmasi karena akses ditolak. Periksa izin dan data terbaru sebelum mencoba kembali.'
                            : 'Hasil penyimpanan belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.'
                    );
                }
                return false;
            },
            onNetworkError: () => {
                setFailure('Koneksi terputus. Hasil penyimpanan belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.');
                return false;
            },
            onCancel: () => setFailure('Permintaan dibatalkan di browser. Hasil penyimpanan belum terkonfirmasi. Periksa data terbaru sebelum mencoba kembali.'),
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Buat rencana kegiatan"
            size="2xl"
            bodyClassName="p-4 sm:p-6"
            showCloseButton={!processing && !blocked}
        >
            <AuthRecoveryNotice recovery={recovery.recovery} pending={processing} />

            <form onSubmit={submit} className="space-y-5">
                {(hasErrors || failure) && (
                    <div
                        ref={summaryRef}
                        tabIndex={-1}
                        role="alert"
                        className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark focus:outline-none focus:ring-2 focus:ring-danger/30"
                    >
                         {failure || 'Periksa kembali isian yang ditandai sebelum menyimpan.'}
                         {failure && <Link href="/kegiatan" className="mt-2 block font-medium underline">Muat ulang data kegiatan</Link>}
                    </div>
                )}

                <fieldset disabled={processing || blocked} className="grid gap-4 sm:grid-cols-2">
                    <Select
                        id="modal_unit_id"
                        label="Unit"
                        required
                        hoverScroll
                        value={data.unit_id}
                        onChange={(e) => setData('unit_id', e.target.value)}
                        error={errors.unit_id}
                        options={[
                            { value: '', label: 'Pilih unit' },
                            ...unitOptions.map((o) => ({ value: o.id, label: o.nama })),
                        ]}
                    />

                    <Input
                        id="modal_tahun"
                        label="Tahun"
                        type="number"
                        required
                        min={1}
                        max={9999}
                        value={data.tahun}
                        onChange={(e) => setData('tahun', e.target.value)}
                        error={errors.tahun}
                    />

                    <Select
                        id="modal_periode_id"
                        label="Periode"
                        required
                        value={data.periode_id}
                        onChange={(e) => setData('periode_id', e.target.value)}
                        error={errors.periode_id}
                        options={[
                            { value: '', label: 'Pilih periode' },
                            ...periodeOptions.map((o) => ({ value: o.id, label: o.nama })),
                        ]}
                    />

                    <Input
                        id="modal_tanggal_rencana"
                        label="Tanggal rencana"
                        type="date"
                        value={data.tanggal_rencana}
                        onChange={(e) => setData('tanggal_rencana', e.target.value)}
                        error={errors.tanggal_rencana}
                    />

                    <div className="sm:col-span-2">
                        <Input
                            id="modal_nama"
                            label="Nama kegiatan"
                            required
                            maxLength={500}
                            placeholder="Contoh: Rapat Koordinasi Penyusunan Laporan Kinerja"
                            value={data.nama}
                            onChange={(e) => setData('nama', e.target.value)}
                            error={errors.nama}
                        />
                    </div>

                    <div className="sm:col-span-2">
                        <Textarea
                            id="modal_tujuan"
                            label="Tujuan"
                            maxLength={10000}
                            placeholder="Jelaskan tujuan pelaksanaan kegiatan secara ringkas..."
                            value={data.tujuan}
                            onChange={(e) => setData('tujuan', e.target.value)}
                            error={errors.tujuan}
                        />
                    </div>

                    <Input
                        id="modal_anggaran"
                        label="Anggaran (Rp)"
                        type="number"
                        inputMode="numeric"
                        min={0}
                        step={1}
                        placeholder="Contoh: 5000000"
                        value={data.anggaran}
                        onChange={(e) => setData('anggaran', e.target.value)}
                        error={errors.anggaran}
                        helperText={bantuanAnggaran(data.anggaran)}
                    />

                    <Input
                        id="modal_sasaran_peserta"
                        label="Sasaran peserta"
                        type="number"
                        min={0}
                        max={2147483647}
                        placeholder="Contoh: 30"
                        value={data.sasaran_peserta}
                        onChange={(e) => setData('sasaran_peserta', e.target.value)}
                        error={errors.sasaran_peserta}
                    />

                    <Input
                        id="modal_keterangan_peserta"
                        label="Keterangan peserta"
                        maxLength={255}
                        placeholder="Contoh: Tim Pokja dan perwakilan pimpinan perguruan tinggi"
                        value={data.keterangan_peserta}
                        onChange={(e) => setData('keterangan_peserta', e.target.value)}
                        error={errors.keterangan_peserta}
                    />

                    <div className="sm:col-span-2">
                        <Input
                            id="modal_lokasi"
                            label="Lokasi"
                            maxLength={255}
                            placeholder="Contoh: Ruang Rapat LLDIKTI XVI / Daring via Zoom"
                            value={data.lokasi}
                            onChange={(e) => setData('lokasi', e.target.value)}
                            error={errors.lokasi}
                        />
                    </div>
                </fieldset>

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleClose}
                        disabled={processing || blocked}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        disabled={blocked}
                        isLoading={processing}
                    >
                        {processing ? 'Menyimpan…' : 'Simpan kegiatan'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
