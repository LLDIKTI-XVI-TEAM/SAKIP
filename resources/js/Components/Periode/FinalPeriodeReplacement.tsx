import { Select } from '@/Components/Select';
import type { PeriodeIndexProps, PeriodeSummary } from '@/types/periode-jadwal';

interface FinalPeriodeReplacementProps {
    current: NonNullable<PeriodeIndexProps['current_final']>;
    candidates: PeriodeSummary[];
    value: string;
    errors: Record<string, string | undefined>;
    disabled: boolean;
    onChange: (periodeId: string) => void;
}

export function FinalPeriodeReplacement({
    current,
    candidates,
    value,
    errors,
    disabled,
    onChange,
}: FinalPeriodeReplacementProps) {
    return (
        <>
            <div>
                <p className="mb-1.5 text-sm font-medium">Periode final saat ini</p>
                <div className="rounded-lg border border-border p-3">
                    <strong className="text-sm">{current.nama}</strong>
                    <p className="mt-1 text-xs text-muted">
                        Dinonaktifkan dalam pergantian; penanda nilai akhir lama tetap untuk riwayat.
                    </p>
                </div>
            </div>
            <Select
                id="periode-pengganti"
                label="Periode pengganti"
                value={value}
                required
                aria-invalid={Boolean(errors.periode_pengganti_id || errors.revisi_pengganti)}
                aria-describedby={
                    errors.periode_pengganti_id || errors.revisi_pengganti ? 'periode-pengganti-error' : undefined
                }
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                options={candidates.map((item) => ({
                    value: item.id,
                    label: `${item.nama} · ${item.aktif ? 'Aktif' : 'Nonaktif'}`,
                }))}
            />
            {(errors.periode_pengganti_id || errors.revisi_pengganti) && (
                <p id="periode-pengganti-error" className="text-xs text-danger">
                    {errors.periode_pengganti_id || errors.revisi_pengganti}
                </p>
            )}
            <p className="text-xs text-muted">
                Kandidat diambil dari halaman dan hasil pencarian master saat ini. Tutup dialog lalu ubah pencarian atau
                halaman untuk memilih periode lainnya.
            </p>
            {candidates.length === 0 && (
                <p role="status" className="text-sm text-warning-dark">
                    Tidak ada kandidat yang dapat dipilih pada halaman ini.
                </p>
            )}
            <p className="rounded-lg bg-soft p-3 text-sm">
                Periode lama dan pengganti berubah sekaligus. Pengganti menjadi aktif dan nilai akhir. Riwayat tetap
                dipertahankan.
            </p>
        </>
    );
}
