import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { Switch } from '@/Components/Switch';
import { Button } from '@/Components/Button';
import type {
    IndikatorArah,
    IndikatorKinerjaItem,
    IndikatorTipePerhitungan,
    RegulasiOption,
    SasaranStrategisItem,
    UnitOption,
} from '@/types/sasaran-indikator';

interface IndikatorModalProps {
    isOpen: boolean;
    onClose: () => void;
    sasarans: SasaranStrategisItem[];
    defaultSasaranId?: string;
    units: UnitOption[];
    regulasis: RegulasiOption[];
    indikator: IndikatorKinerjaItem | null;
}

export const IndikatorModal: React.FC<IndikatorModalProps> = ({
    isOpen,
    onClose,
    sasarans,
    defaultSasaranId,
    units,
    regulasis,
    indikator,
}) => {
    const isEdit = Boolean(indikator);

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm({
        sasaran_strategis_id: defaultSasaranId || (sasarans[0]?.id ?? ''),
        kode: '',
        nama: '',
        definisi_operasional: '',
        satuan: '%',
        unit_id: units[0]?.id ?? '',
        arah: 'naik_baik' as IndikatorArah,
        tipe_perhitungan: 'manual' as IndikatorTipePerhitungan,
        presisi: 2,
        desimal_tampilan: 2,
        wajib_catatan: false,
        jenis_agregasi: 'terakhir',
        is_aktif: true,
        regulasi_id: '' as string,
    });

    useEffect(() => {
        if (isOpen) {
            clearErrors();
            if (indikator) {
                setData({
                    sasaran_strategis_id: indikator.sasaran_strategis_id,
                    kode: indikator.kode,
                    nama: indikator.nama,
                    definisi_operasional: indikator.definisi_operasional ?? '',
                    satuan: indikator.satuan,
                    unit_id: indikator.unit_id,
                    arah: indikator.arah,
                    tipe_perhitungan: indikator.tipe_perhitungan,
                    presisi: indikator.presisi ?? 2,
                    desimal_tampilan: indikator.desimal_tampilan ?? 2,
                    wajib_catatan: Boolean(indikator.wajib_catatan),
                    jenis_agregasi: indikator.jenis_agregasi ?? 'terakhir',
                    is_aktif: Boolean(indikator.is_aktif),
                    regulasi_id: indikator.regulasi_id ?? '',
                });
            } else {
                setData({
                    sasaran_strategis_id: defaultSasaranId || (sasarans[0]?.id ?? ''),
                    kode: '',
                    nama: '',
                    definisi_operasional: '',
                    satuan: '%',
                    unit_id: units[0]?.id ?? '',
                    arah: 'naik_baik',
                    tipe_perhitungan: 'manual',
                    presisi: 2,
                    desimal_tampilan: 2,
                    wajib_catatan: false,
                    jenis_agregasi: 'terakhir',
                    is_aktif: true,
                    regulasi_id: '',
                });
            }
        } else {
            reset();
        }
    }, [isOpen, indikator, defaultSasaranId, sasarans, units]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            regulasi_id: formData.regulasi_id || null,
            definisi_operasional: formData.definisi_operasional || null,
        }));

        if (isEdit && indikator) {
            put(`/perencanaan/indikator/${indikator.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        } else {
            post('/perencanaan/indikator', {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={isEdit ? 'Ubah Indikator Kinerja' : 'Tambah Indikator Kinerja'}
            description="Indikator kinerja mengukur ketercapaian sasaran strategis dengan rumus, satuan, dan unit penanggung jawab."
            size="2xl"
            footer={
                <div className="flex items-center justify-end gap-3">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        onClick={handleSubmit}
                        disabled={processing}
                    >
                        {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Indikator'}
                    </Button>
                </div>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <Select
                        id="sasaran_strategis_id"
                        label="Sasaran Strategis"
                        value={data.sasaran_strategis_id}
                        onChange={(e) => setData('sasaran_strategis_id', e.target.value)}
                        error={errors.sasaran_strategis_id}
                        required
                    >
                        {sasarans.map((s) => (
                            <option key={s.id} value={s.id}>
                                [{s.kode}] {s.deskripsi.length > 80 ? `${s.deskripsi.substring(0, 80)}...` : s.deskripsi}
                            </option>
                        ))}
                    </Select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Input
                            id="indikator_kode"
                            label="Kode IKU"
                            placeholder="Contoh: IKU-01"
                            value={data.kode}
                            onChange={(e) => setData('kode', e.target.value)}
                            error={errors.kode}
                            required
                        />
                    </div>
                    <div className="md:col-span-2">
                        <Input
                            id="indikator_nama"
                            label="Nama Indikator Kinerja"
                            placeholder="Nama indikator secara lengkap..."
                            value={data.nama}
                            onChange={(e) => setData('nama', e.target.value)}
                            error={errors.nama}
                            required
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Input
                            id="indikator_satuan"
                            label="Satuan"
                            placeholder="Contoh: %, Dokumen, Skor"
                            value={data.satuan}
                            onChange={(e) => setData('satuan', e.target.value)}
                            error={errors.satuan}
                            required
                        />
                    </div>
                    <div className="md:col-span-2">
                        <Select
                            id="indikator_unit_id"
                            label="Unit Penanggung Jawab"
                            value={data.unit_id}
                            onChange={(e) => setData('unit_id', e.target.value)}
                            error={errors.unit_id}
                            required
                        >
                            {units.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.nama}
                                </option>
                            ))}
                        </Select>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Select
                            id="indikator_arah"
                            label="Arah Kinerja"
                            value={data.arah}
                            onChange={(e) => setData('arah', e.target.value as IndikatorArah)}
                            error={errors.arah}
                            required
                        >
                            <option value="naik_baik">Makin Tinggi Makin Baik (naik_baik)</option>
                            <option value="turun_baik">Makin Rendah Makin Baik (turun_baik)</option>
                        </Select>
                    </div>
                    <div>
                        <Select
                            id="indikator_tipe"
                            label="Tipe Perhitungan"
                            value={data.tipe_perhitungan}
                            onChange={(e) => setData('tipe_perhitungan', e.target.value as IndikatorTipePerhitungan)}
                            error={errors.tipe_perhitungan}
                            required
                        >
                            <option value="manual">Manual</option>
                            <option value="rasio_persen">Rasio Persen (%)</option>
                            <option value="penjumlahan">Penjumlahan</option>
                        </Select>
                    </div>
                    <div>
                        <Input
                            id="indikator_presisi"
                            label="Presisi Desimal"
                            type="number"
                            min={0}
                            max={6}
                            value={data.presisi}
                            onChange={(e) => setData('presisi', parseInt(e.target.value, 10) || 0)}
                            error={errors.presisi}
                            required
                        />
                    </div>
                </div>

                <div>
                    <Select
                        id="indikator_regulasi_id"
                        label="Regulasi Rujukan (Opsional)"
                        value={data.regulasi_id}
                        onChange={(e) => setData('regulasi_id', e.target.value)}
                        error={errors.regulasi_id}
                    >
                        <option value="">-- Tanpa Regulasi Rujukan --</option>
                        {regulasis.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.jenis.toUpperCase()} No. {r.nomor}/{r.tahun} - {r.tentang.length > 60 ? `${r.tentang.substring(0, 60)}...` : r.tentang}
                            </option>
                        ))}
                    </Select>
                </div>

                <Textarea
                    id="indikator_definisi"
                    label="Definisi Operasional (Opsional)"
                    placeholder="Penjelasan formula, batasan, sumber data, atau metodologi penghitungan..."
                    rows={3}
                    value={data.definisi_operasional}
                    onChange={(e) => setData('definisi_operasional', e.target.value)}
                    error={errors.definisi_operasional}
                />

                <div className="flex flex-col gap-3 pt-2">
                    <div className="flex items-center gap-3">
                        <Switch
                            id="indikator_wajib_catatan"
                            checked={data.wajib_catatan}
                            onChange={(val) => setData('wajib_catatan', val)}
                        />
                        <label htmlFor="indikator_wajib_catatan" className="text-sm font-medium text-ink cursor-pointer">
                            Wajib Melampirkan Catatan Penjelasan saat Pengisian Realisasi
                        </label>
                    </div>

                    {isEdit && (
                        <div className="flex items-center gap-3">
                            <Switch
                                id="indikator_is_aktif"
                                checked={data.is_aktif}
                                onChange={(val) => setData('is_aktif', val)}
                            />
                            <label htmlFor="indikator_is_aktif" className="text-sm font-medium text-ink cursor-pointer">
                                Status Indikator Aktif
                            </label>
                        </div>
                    )}
                </div>
            </form>
        </Modal>
    );
};
