import { Input } from '@/Components/Input';
import type { PeriodeIndexProps, PeriodeSummary } from '@/types/periode-jadwal';

export interface PeriodeFormValues {
    nama: string;
    urutan: string;
    aktif: boolean;
    is_nilai_akhir: boolean;
}

interface PeriodeFieldsProps {
    values: PeriodeFormValues;
    errors: Record<string, string | undefined>;
    editing: PeriodeSummary | null;
    currentFinal: PeriodeIndexProps['current_final'];
    disabled: boolean;
    onChange: <K extends keyof PeriodeFormValues>(field: K, value: PeriodeFormValues[K]) => void;
}

export function PeriodeFields({ values, errors, editing, currentFinal, disabled, onChange }: PeriodeFieldsProps) {
    return (
        <>
            {editing?.metadata_locked && (
                <p className="rounded-lg bg-soft p-3 text-sm">
                    {editing.metadata_locked_reason} Urutan dan penanda nilai akhir tetap. Nama masih dapat dikoreksi.
                </p>
            )}
            <Input
                name="nama"
                label="Nama periode"
                value={values.nama}
                onChange={(event) => onChange('nama', event.target.value)}
                required
                maxLength={255}
                autoComplete="off"
                error={errors.nama}
                disabled={disabled}
            />
            <Input
                name="urutan"
                label="Urutan"
                type="number"
                value={values.urutan}
                onChange={(event) => onChange('urutan', event.target.value)}
                required
                error={errors.urutan}
                disabled={disabled || editing?.metadata_locked}
            />
            <div>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        aria-invalid={Boolean(errors.aktif)}
                        aria-describedby={errors.aktif ? 'periode-aktif-error' : undefined}
                        checked={values.aktif}
                        onChange={(event) => onChange('aktif', event.target.checked)}
                        disabled={
                            disabled || Boolean(editing ? editing.is_nilai_akhir && editing.aktif : !currentFinal)
                        }
                        className="h-4 w-4 accent-primary"
                    />
                    Aktif
                </label>
                {errors.aktif && (
                    <p id="periode-aktif-error" className="mt-1 text-xs text-danger">
                        {errors.aktif}
                    </p>
                )}
                <p className="mt-2 text-xs text-muted">
                    Periode final aktif tidak dinonaktifkan sendiri. Gunakan pergantian nilai akhir. Penonaktifan tidak
                    menghapus referensi dan riwayat.
                </p>
            </div>
            <div>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        aria-invalid={Boolean(errors.is_nilai_akhir)}
                        aria-describedby={errors.is_nilai_akhir ? 'periode-final-error' : undefined}
                        checked={values.is_nilai_akhir}
                        onChange={(event) => onChange('is_nilai_akhir', event.target.checked)}
                        disabled={disabled || !editing || editing.metadata_locked || values.aktif}
                        className="h-4 w-4 accent-primary"
                    />
                    Nilai akhir · Diisi manual
                </label>
                {errors.is_nilai_akhir && (
                    <p id="periode-final-error" className="mt-1 text-xs text-danger">
                        {errors.is_nilai_akhir}
                    </p>
                )}
                <p className="mt-2 text-xs text-muted">
                    {currentFinal
                        ? 'Periode aktif menggunakan pergantian nilai akhir. Penanda periode nonaktif dapat dikoreksi selama metadata belum terkunci.'
                        : 'Periode pertama menjadi nilai akhir aktif untuk memulai konfigurasi.'}
                </p>
            </div>
        </>
    );
}
