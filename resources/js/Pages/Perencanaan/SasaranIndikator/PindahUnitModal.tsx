import React, { useEffect, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Modal } from '@/Components/Modal';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { Button } from '@/Components/Button';
import type { IndikatorKinerjaItem, UnitOption } from '@/types/sasaran-indikator';

interface PindahUnitModalProps {
    isOpen: boolean;
    onClose: () => void;
    indikator: IndikatorKinerjaItem | null;
    units: UnitOption[];
}

export const PindahUnitModal: React.FC<PindahUnitModalProps> = ({
    isOpen,
    onClose,
    indikator,
    units,
}) => {
    const prevOpenRef = useRef(false);
    const prevIndikatorIdRef = useRef<string | null>(null);
    const [unitError, setUnitError] = useState<string | undefined>();
    const [alasanError, setAlasanError] = useState<string | undefined>();

    const { data, setData, patch, processing, errors, reset, clearErrors } = useForm({
        unit_id: '',
        alasan: '',
    });

    useEffect(() => {
        const wasOpen = prevOpenRef.current;
        const prevId = prevIndikatorIdRef.current;
        const currentId = indikator?.id ?? null;

        prevOpenRef.current = isOpen;
        prevIndikatorIdRef.current = currentId;

        if (isOpen && (!wasOpen || prevId !== currentId)) {
            clearErrors();
            setUnitError(undefined);
            setAlasanError(undefined);
            setData({ unit_id: '', alasan: '' });
        } else if (!isOpen && wasOpen) {
            reset();
            setUnitError(undefined);
            setAlasanError(undefined);
        }
    }, [isOpen, indikator, clearErrors, reset, setData]);

    const unitAsalNama =
        units.find((u) => u.id === indikator?.unit_id)?.nama
        ?? indikator?.unit_nama
        ?? indikator?.unit_id
        ?? '';

    // Daftar unit tujuan hanya berisi unit aktif selain unit asal.
    // Payload `units` halaman sudah difilter aktif oleh server.
    const unitTujuan = units.filter((u) => u.id !== indikator?.unit_id);

    const handleClose = () => {
        reset();
        clearErrors();
        setUnitError(undefined);
        setAlasanError(undefined);
        onClose();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!indikator) return;

        let valid = true;
        if (!data.unit_id) {
            setUnitError('Unit penanggung jawab tujuan wajib dipilih.');
            valid = false;
        } else {
            setUnitError(undefined);
        }
        if (data.alasan.trim().length < 10) {
            setAlasanError('Alasan pemindahan unit penanggung jawab minimal 10 karakter.');
            valid = false;
        } else {
            setAlasanError(undefined);
        }
        if (!valid) return;

        patch(`/perencanaan/indikator/${indikator.id}/pindah-unit`, {
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
            },
        });
    };

    if (!indikator) return null;

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title={`Pindah Unit — ${indikator.kode}`}
            description={`Pindahkan indikator "${indikator.nama}" dari unit "${unitAsalNama}" ke unit lain. Perpindahan dicatat pada audit log beserta alasan.`}
            size="lg"
            footer={
                <div className="flex items-center justify-end gap-3">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={handleClose}
                        disabled={processing}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        onClick={handleSubmit}
                        variant="primary"
                        isLoading={processing}
                        disabled={processing || unitTujuan.length === 0}
                    >
                        {processing ? 'Memindahkan...' : 'Pindahkan Unit'}
                    </Button>
                </div>
            }
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <Select
                        id="pindah_unit_tujuan"
                        label="Unit Tujuan"
                        value={data.unit_id}
                        onChange={(e) => {
                            setData('unit_id', e.target.value);
                            if (unitError && e.target.value) {
                                setUnitError(undefined);
                            }
                        }}
                        error={unitError ?? errors.unit_id}
                        helperText={
                            unitTujuan.length === 0
                                ? 'Tidak ada unit aktif lain yang dapat menjadi tujuan.'
                                : 'Hanya unit aktif yang dapat menjadi tujuan. Unit asal tidak tercantum.'
                        }
                        required
                    >
                        <option value="">-- Pilih Unit Tujuan --</option>
                        {unitTujuan.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.nama}
                            </option>
                        ))}
                    </Select>
                </div>

                <div>
                    <Textarea
                        id="pindah_unit_alasan"
                        label="Alasan Pemindahan"
                        placeholder="Jelaskan alasan pemindahan kepemilikan unit indikator ini (minimal 10 karakter)..."
                        rows={4}
                        value={data.alasan}
                        onChange={(e) => {
                            setData('alasan', e.target.value);
                            if (alasanError && e.target.value.trim().length >= 10) {
                                setAlasanError(undefined);
                            }
                        }}
                        error={alasanError ?? errors.alasan}
                        helperText="Alasan wajib diisi dan akan dicatat pada audit log perpindahan unit."
                        required
                    />
                </div>
            </form>
        </Modal>
    );
};
