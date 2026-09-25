import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Button } from '@/Components/Button';
import type { SasaranStrategisItem } from '@/types/sasaran-indikator';

interface SasaranModalProps {
    isOpen: boolean;
    onClose: () => void;
    renstraId: string;
    sasaran: SasaranStrategisItem | null;
}

export const SasaranModal: React.FC<SasaranModalProps> = ({
    isOpen,
    onClose,
    renstraId,
    sasaran,
}) => {
    const isEdit = Boolean(sasaran);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        renstra_id: renstraId,
        kode: '',
        deskripsi: '',
        urutan: 1,
    });

    useEffect(() => {
        if (isOpen) {
            clearErrors();
            if (sasaran) {
                setData({
                    renstra_id: sasaran.renstra_id,
                    kode: sasaran.kode,
                    deskripsi: sasaran.deskripsi,
                    urutan: sasaran.urutan,
                });
            } else {
                setData({
                    renstra_id: renstraId,
                    kode: '',
                    deskripsi: '',
                    urutan: 1,
                });
            }
        } else {
            reset();
        }
    }, [isOpen, sasaran, renstraId]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && sasaran) {
            put(`/perencanaan/sasaran/${sasaran.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    onClose();
                    reset();
                },
            });
        } else {
            post('/perencanaan/sasaran', {
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
            title={isEdit ? 'Ubah Sasaran Strategis' : 'Tambah Sasaran Strategis'}
            description="Sasaran strategis merupakan target jangka menengah yang hendak dicapai organisasi."
            size="lg"
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
                        {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Sasaran'}
                    </Button>
                </div>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="md:col-span-2">
                        <Input
                            id="sasaran_kode"
                            label="Kode Sasaran"
                            placeholder="Contoh: SS-01"
                            value={data.kode}
                            onChange={(e) => setData('kode', e.target.value)}
                            error={errors.kode}
                            required
                        />
                    </div>
                    <div>
                        <Input
                            id="sasaran_urutan"
                            label="Urutan Tampil"
                            type="number"
                            min={1}
                            value={data.urutan}
                            onChange={(e) => setData('urutan', parseInt(e.target.value, 10) || 1)}
                            error={errors.urutan}
                            required
                        />
                    </div>
                </div>

                <Textarea
                    id="sasaran_deskripsi"
                    label="Deskripsi Sasaran Strategis"
                    placeholder="Tuliskan pernyataan sasaran strategis secara jelas dan terukur..."
                    rows={4}
                    value={data.deskripsi}
                    onChange={(e) => setData('deskripsi', e.target.value)}
                    error={errors.deskripsi}
                    required
                />
            </form>
        </Modal>
    );
};
