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
                <p className="mb-1.5 text-sm font-medium">Periode nilai akhir saat ini</p>
                <div className="rounded-lg border border-border p-3">
                    <strong className="text-sm">{current.nama}</strong>
                    <p className="mt-1 text-xs text-muted">Akan dinonaktifkan dan tetap tercatat sebagai riwayat.</p>
                </div>
            </div>
            {candidates.length === 0 ? (
                <p role="status" className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                    Tidak ada periode pada daftar yang sedang tampil yang dapat menjadi pengganti. Pengganti harus
                    periode yang belum dipakai jadwal aktif atau ditutup; tambahkan periode baru atau ubah pencarian.
                </p>
            ) : (
                <>
                    <Select
                        id="periode-pengganti"
                        label="Periode pengganti"
                        value={value}
                        required
                        aria-invalid={Boolean(errors.periode_pengganti_id || errors.revisi_pengganti)}
                        aria-describedby={
                            errors.periode_pengganti_id || errors.revisi_pengganti
                                ? 'periode-pengganti-error'
                                : undefined
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
                        Pengganti otomatis menjadi aktif dan periode nilai akhir. Pilihan hanya dari daftar yang sedang
                        tampil; ubah pencarian atau halaman untuk periode lain.
                    </p>
                </>
            )}
        </>
    );
}
