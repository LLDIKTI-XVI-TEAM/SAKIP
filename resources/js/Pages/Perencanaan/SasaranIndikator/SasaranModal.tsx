import React, { useEffect, useRef } from 'react';
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
    const prevOpenRef = useRef(false);
    const prevSasaranIdRef = useRef<string | null>(null);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        renstra_id: renstraId,
        deskripsi: '',
        expected_updated_at: '' as string,
    });

    useEffect(() => {
        const wasOpen = prevOpenRef.current;
        const prevId = prevSasaranIdRef.current;
        const currentId = sasaran?.id ?? null;

        prevOpenRef.current = isOpen;
        prevSasaranIdRef.current = currentId;

        if (isOpen) {
            if (!wasOpen || prevId !== currentId) {
                clearErrors();
                if (sasaran) {
                    setData({
                        renstra_id: sasaran.renstra_id,
                        deskripsi: sasaran.deskripsi,
                        expected_updated_at: sasaran.updated_at ?? '',
                    });
                } else {
                    setData({
                        renstra_id: renstraId,
                        deskripsi: '',
                        expected_updated_at: '',
                    });
                }
            }
        } else if (wasOpen) {
            reset();
        }
    }, [isOpen, sasaran, renstraId, clearErrors, reset, setData]);

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
            size="lg"
            footer={
                <div className="flex items-center justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        form="sasaran-editor-form"
                        disabled={processing}
                    >
                        {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Tambah Sasaran'}
                    </Button>
                </div>
            }
        >
            <form id="sasaran-editor-form" onSubmit={handleSubmit} className="space-y-4">
                {((errors as Record<string, string | undefined>).konflik ?? errors.expected_updated_at) && (
                    <p role="alert" className="text-sm font-medium text-danger">
                        {(errors as Record<string, string | undefined>).konflik ?? errors.expected_updated_at}
                    </p>
                )}
                <div className="rounded-lg border border-border bg-soft p-3">
                    {isEdit && sasaran ? (
                        <Input
                            id="sasaran_kode"
                            label="Kode Sasaran"
                            value={sasaran.kode}
                            disabled
                            readOnly
                            helperText="Kode dibangkitkan otomatis oleh sistem dan tidak dapat diubah."
                        />
                    ) : (
                        <p className="text-sm text-muted">
                            Kode sasaran dibuat otomatis oleh sistem secara berurutan dengan format{' '}
                            <span className="font-mono font-medium text-ink">SS-01</span>,{' '}
                            <span className="font-mono font-medium text-ink">SS-02</span>, dan seterusnya — mengikuti kode tertinggi yang sudah ada.
                        </p>
                    )}
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
