import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { NAMA_BULAN } from '@/lib/rentangPeriode';
import type { PeriodeIndexProps, PeriodeSummary } from '@/types/periode-jadwal';

export interface PeriodeFormValues {
    nama: string;
    urutan: string;
    aktif: boolean;
    is_nilai_akhir: boolean;
    hari_mulai: string;
    bulan_mulai: string;
    hari_selesai: string;
    bulan_selesai: string;
}

const pilihanHari = [{ value: '', label: 'Pilih' }, ...Array.from({ length: 31 }, (_, index) => ({ value: String(index + 1), label: String(index + 1) }))];
const pilihanBulan = [{ value: '', label: 'Pilih' }, ...NAMA_BULAN.map((nama, index) => ({ value: String(index + 1), label: nama }))];

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
                <p className="rounded-lg bg-soft p-3 text-sm">{editing.metadata_locked_reason}</p>
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
            <fieldset className="space-y-3">
                <legend className="text-sm font-medium text-ink">Rentang tanggal</legend>
                <p className="text-xs text-muted">
                    Berlaku setiap tahun dan wajib untuk periode aktif. Kosongkan keduanya bila periode belum dipakai.
                </p>
                {(['mulai', 'selesai'] as const).map((ujung) => (
                    <div key={ujung} className="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                        <Select
                            name={`hari_${ujung}`}
                            label={`Tanggal ${ujung}`}
                            value={values[`hari_${ujung}`]}
                            onChange={(event) => onChange(`hari_${ujung}`, event.target.value)}
                            options={pilihanHari}
                            error={errors[`hari_${ujung}`]}
                            disabled={disabled}
                        />
                        <Select
                            name={`bulan_${ujung}`}
                            label={`Bulan ${ujung}`}
                            value={values[`bulan_${ujung}`]}
                            onChange={(event) => onChange(`bulan_${ujung}`, event.target.value)}
                            options={pilihanBulan}
                            error={errors[`bulan_${ujung}`]}
                            disabled={disabled}
                        />
                    </div>
                ))}
            </fieldset>
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
                    Penonaktifan tidak menghapus riwayat. Periode nilai akhir yang aktif diganti lewat tombol Ganti
                    periode nilai akhir.
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
                    Periode nilai akhir
                </label>
                {errors.is_nilai_akhir && (
                    <p id="periode-final-error" className="mt-1 text-xs text-danger">
                        {errors.is_nilai_akhir}
                    </p>
                )}
                <p className="mt-2 text-xs text-muted">
                    {currentFinal
                        ? 'Untuk periode aktif, gunakan tombol Ganti periode nilai akhir.'
                        : 'Periode pertama otomatis menjadi periode nilai akhir.'}{' '}
                    Nilainya diisi manual, bukan dijumlahkan dari periode lain.
                </p>
            </div>
        </>
    );
}
