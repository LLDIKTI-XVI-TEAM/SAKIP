import { useState, type FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Modal } from '@/Components/Modal';
import { Textarea } from '@/Components/Textarea';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { IzinUnitCheckboxes } from '@/Components/Access/IzinUnitCheckboxes';
import { belumDiketahui, failureClass, galatIzin, pesanGagal } from '@/Components/Access/izinUnitFeedback';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import type { KelompokIzinUnit, PilihanIzin } from '@/types/izinUnit';

interface Props {
    kelompok: KelompokIzinUnit | null;
    options: PilihanIzin[];
    onClose: () => void;
}

/** Modal "Kelola izin" (Q61): daftar akhir izin satu pengguna pada satu unit; server menurunkan pemberian dan pencabutan. */
export function KelolaIzinUnitModal({ kelompok, options, onClose }: Props) {
    if (!kelompok) return null;

    return <KelolaIzinUnitDialog key={kelompok.key} kelompok={kelompok} options={options} onClose={onClose} />;
}

function KelolaIzinUnitDialog({ kelompok, options, onClose }: Props & { kelompok: KelompokIzinUnit }) {
    const recovery = useAuthRecovery();
    const [failure, setFailure] = useState<string | null>(null);
    const dimiliki = kelompok.izin.map((izin) => izin.permission_id);
    // Izin nonaktif yang masih dimiliki tetap tampil agar tidak ikut tercabut tanpa sengaja.
    const pilihan: PilihanIzin[] = [
        ...options,
        ...kelompok.izin
            .filter((izin) => !options.some((option) => option.id === izin.permission_id))
            .map((izin) => ({ id: izin.permission_id, kode: '', label: `${izin.label} (nonaktif)` })),
    ];
    const form = useForm<{ user_id: string; unit_id: string; permission_ids: string[]; expected_permission_ids: string[]; alasan: string }>({
        user_id: kelompok.user_id,
        unit_id: kelompok.unit_id,
        permission_ids: pilihan.map((option) => option.id).filter((id) => dimiliki.includes(id)),
        expected_permission_ids: dimiliki,
        alasan: '',
    });
    const ditambah = form.data.permission_ids.filter((id) => !dimiliki.includes(id)).length;
    const dicabut = dimiliki.filter((id) => !form.data.permission_ids.includes(id)).length;
    const errors = form.errors as Record<string, string | undefined>;
    const konflik = errors.expected_permission_ids;
    const ringkasan = ditambah === 0 && dicabut === 0
        ? 'Belum ada perubahan.'
        : [ditambah > 0 && `${ditambah} izin diberikan`, dicabut > 0 && `${dicabut} izin dicabut`].filter(Boolean).join(', ') + '.';
    const bisaSimpan = (ditambah > 0 || dicabut > 0) && !(dicabut > 0 && form.data.alasan.trim() === '') && !recovery.recovery && !konflik;

    const close = () => { if (!form.processing) onClose(); };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!bisaSimpan || form.processing) return;
        setFailure(null);
        form.put('/akses/grant/kelola', {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onCancel: () => setFailure(belumDiketahui('perubahan', 'Permintaan dibatalkan.')),
            onNetworkError: () => {
                setFailure(belumDiketahui('perubahan', 'Koneksi terputus.'));
                return false;
            },
            onHttpException: (response) => {
                if (recovery.handleHttpException(response, { effectiveMethod: 'put', path: '/akses/grant/kelola', mutation: true })) return false;
                setFailure(pesanGagal(response));
                return false;
            },
        });
    };

    return <Modal
        isOpen
        onClose={close}
        size="lg"
        title={`Kelola izin unit ${kelompok.user_name}`}
        description={`Centang izin yang tetap atau baru diberikan pada ${kelompok.unit_nama ?? 'unit ini'}. Izin yang dilepas akan dicabut.`}
        footer={<>
            <Button type="button" variant="outline" disabled={form.processing} onClick={close}>Batal</Button>
            <Button type="submit" form="kelola-izin-unit-form" isLoading={form.processing} disabled={!bisaSimpan}>Simpan perubahan</Button>
        </>}
    >
        <form id="kelola-izin-unit-form" onSubmit={submit} className="space-y-4" aria-busy={form.processing}>
            {failure && <p role="alert" className={failureClass}>{failure}</p>}
            {konflik && <div role="alert" className={`space-y-2 ${failureClass}`}>
                <p>{konflik}</p>
                <Button type="button" variant="outline" size="sm" onClick={() => { onClose(); router.reload(); }}>Muat ulang data</Button>
            </div>}
            <IzinUnitCheckboxes
                id="kelola-izin"
                options={pilihan}
                value={form.data.permission_ids}
                onChange={(ids) => form.setData('permission_ids', ids)}
                disabled={form.processing}
                error={galatIzin(errors)}
            />
            <p role="status" className="text-sm text-muted">{ringkasan}</p>
            <Textarea
                id="kelola-izin-alasan"
                label={dicabut > 0 ? 'Alasan perubahan' : 'Alasan perubahan (opsional)'}
                required={dicabut > 0}
                maxLength={1000}
                rows={3}
                placeholder="Contoh: penyesuaian tugas Penanggung Jawab triwulan ini."
                value={form.data.alasan}
                onChange={(event) => form.setData('alasan', event.target.value)}
                disabled={form.processing}
                error={errors.alasan}
                helperText={dicabut > 0 ? 'Wajib karena ada izin yang dicabut. Maksimal 1.000 karakter.' : 'Boleh dikosongkan bila hanya menambah izin. Maksimal 1.000 karakter.'}
            />
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
        </form>
    </Modal>;
}
