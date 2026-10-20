import type { PilihanIzin } from '@/types/izinUnit';

const checkboxClass = 'h-4 w-4 shrink-0 accent-primary';

/** Grup checkbox izin unit dengan "Pilih semua"; nilai dikirim mengikuti urutan pilihan (katalog), bukan urutan klik. */
export function IzinUnitCheckboxes({ id, options, value, onChange, error, disabled = false }: {
    id: string;
    options: PilihanIzin[];
    value: string[];
    onChange: (ids: string[]) => void;
    error?: string;
    disabled?: boolean;
}) {
    const semua = options.length > 0 && options.every((option) => value.includes(option.id));
    const toggle = (target: string) => onChange(options
        .map((option) => option.id)
        .filter((pid) => (pid === target ? !value.includes(target) : value.includes(pid))));

    return <fieldset disabled={disabled} aria-describedby={error ? `${id}-error` : undefined}>
        <legend className="mb-1.5 text-sm font-semibold text-ink">Izin <span className="text-danger" aria-hidden="true">*</span></legend>
        <div className="rounded-lg border border-border p-3">
            <label className="flex items-center gap-2 border-b border-border pb-2 text-sm font-medium text-ink">
                <input type="checkbox" className={checkboxClass} checked={semua} onChange={() => onChange(semua ? [] : options.map((option) => option.id))} />
                Pilih semua izin
            </label>
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {options.map((option) => <label key={option.id} className="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" className={checkboxClass} checked={value.includes(option.id)} onChange={() => toggle(option.id)} />
                    {option.label}
                </label>)}
            </div>
        </div>
        {error && <p id={`${id}-error`} role="alert" className="mt-1.5 text-xs font-medium text-danger">{error}</p>}
    </fieldset>;
}
