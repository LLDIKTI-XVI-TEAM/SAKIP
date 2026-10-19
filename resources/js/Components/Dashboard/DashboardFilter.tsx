import { router } from '@inertiajs/react';
import { Select } from '@/Components/Select';
import type { DashboardFilterValue, DashboardPilihan } from '@/types/dashboard';

// Filter hilir bergantung pada jadwal terpilih; dikosongkan agar server tidak menolak nilai dari jadwal lain.
const HILIR: Record<keyof DashboardFilterValue, (keyof DashboardFilterValue)[]> = {
    renstra_id: ['tahun', 'periode_id', 'sasaran_id', 'unit_id'],
    tahun: ['periode_id', 'sasaran_id', 'unit_id'],
    periode_id: [],
    sasaran_id: [],
    unit_id: [],
};

export function DashboardFilter({ filter, pilihan, errors = {} }: { filter: DashboardFilterValue; pilihan: DashboardPilihan; errors?: Partial<Record<string, string>> }) {
    const ubah = (key: keyof DashboardFilterValue, value: string) => {
        const next: Record<string, string | number | null> = { ...filter, [key]: value === '' ? null : key === 'tahun' ? Number(value) : value };
        HILIR[key].forEach((hilir) => (next[hilir] = null));
        const query = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== null && v !== ''));
        router.get('/dashboard', query, { preserveState: true, preserveScroll: true, replace: true });
    };
    const select = (key: keyof DashboardFilterValue, label: string, options: { value: string | number; label: string }[], semua?: string) => (
        <Select
            key={key}
            label={label}
            name={key}
            value={filter[key] ?? ''}
            error={errors[key]}
            options={[...(semua ? [{ value: '', label: semua }] : []), ...options]}
            onChange={(event) => ubah(key, event.target.value)}
        />
    );

    return (
        <form role="search" aria-label="Filter dashboard" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" onSubmit={(event) => event.preventDefault()}>
            {select('renstra_id', 'Renstra', pilihan.renstra.map((r) => ({ value: r.id, label: r.nama })))}
            {select('tahun', 'Tahun', pilihan.tahun.map((t) => ({ value: t, label: String(t) })))}
            {select('periode_id', 'Periode', pilihan.periode.map((p) => ({ value: p.id, label: p.nama })))}
            {select('sasaran_id', 'Sasaran', pilihan.sasaran.map((s) => ({ value: s.id, label: `${s.kode} — ${s.deskripsi}` })), 'Semua sasaran')}
            {select('unit_id', 'Unit', pilihan.unit.map((u) => ({ value: u.id, label: u.nama })), 'Semua unit')}
        </form>
    );
}
