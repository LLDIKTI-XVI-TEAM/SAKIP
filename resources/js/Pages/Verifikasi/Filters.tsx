import { useEffect, useId, useState, type FormEvent } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';

export interface PilihanFilter { id: string; label: string }
export interface FilterVerifikasi {
    /** Absen pada antrean yang statusnya sudah ditentukan server (mis. pengukuran disahkan). */
    status?: 'diajukan' | 'diverifikasi';
    renstra_id: string;
    tahun: string;
    periode_id: string;
    unit_id: string;
}
export interface PilihanFilterTerpilih {
    renstra_id: PilihanFilter | null;
    periode_id: PilihanFilter | null;
    unit_id: PilihanFilter | null;
}
interface HalamanPilihan { data: PilihanFilter[]; page: number; has_more: boolean }

function FilterLookup({ jenis, label, initialId, initialOption, disabled, error, lookupUrl, onChange }: {
    jenis: 'renstra' | 'periode' | 'unit'; label: string; initialOption: PilihanFilter | null;
    initialId: string;
    disabled: boolean; error?: string; lookupUrl: string; onChange: (id: string) => void;
}) {
    const id = useId();
    // ID pada URL tetap aktif meski labelnya sudah keluar dari scope antrean.
    const [selected, setSelected] = useState(initialOption ?? (initialId ? { id: initialId, label: 'Pilihan tidak tersedia' } : null));
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [retry, setRetry] = useState(0);
    const [result, setResult] = useState<HalamanPilihan | null>(null);
    const [loading, setLoading] = useState(false);
    const [failure, setFailure] = useState('');
    const searching = query.trim().length >= 1 && !selected;
    useEffect(() => {
        if (!searching) return;
        let active = true;
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            setFailure('');
            const params = new URLSearchParams({ jenis, q: query.trim(), page: String(page) });
            void fetch(`${lookupUrl}?${params}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then(async (response) => {
                    if (!response.ok || response.redirected) {
                        if (active) setFailure(response.status === 401 || response.redirected
                            ? 'Sesi berakhir. Muat ulang halaman untuk masuk kembali.'
                            : response.status === 403 ? 'Anda tidak lagi berhak membaca pilihan ini. Muat ulang halaman.' : 'Pilihan belum dapat dimuat. Coba lagi.');
                        return;
                    }
                    const data: HalamanPilihan = await response.json();
                    if (active) setResult(data);
                })
                .catch(() => {
                    if (active) setFailure('Pilihan belum dapat dimuat. Coba lagi.');
                })
                .finally(() => { if (active) setLoading(false); });
        }, 350);
        return () => { active = false; clearTimeout(timer); controller.abort(); };
    }, [jenis, lookupUrl, page, query, retry, searching]);
    const choose = (option: PilihanFilter | null) => {
        setSelected(option); setQuery(''); setResult(null); setFailure(''); setLoading(false); setPage(1);
        onChange(option?.id ?? '');
        if (!option) requestAnimationFrame(() => document.getElementById(id)?.focus());
    };
    return <div className="min-w-0 space-y-2">
        <Input id={id} label={`Cari ${label}`} value={selected?.label ?? query} disabled={disabled || Boolean(selected)}
            error={error} helperText={selected ? undefined : 'Ketik nama, lalu pilih hasil.'}
            onChange={(event) => { setQuery(event.target.value); setPage(1); setResult(null); setFailure(''); setLoading(false); }}
            onKeyDown={(event) => { if (event.key === 'Enter' && !selected) event.preventDefault(); }} />
        {selected && <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => choose(null)} aria-label={`Hapus filter ${label}`}>Hapus pilihan</Button>}
        {searching && <div className="space-y-2" aria-live="polite" aria-busy={loading}>
            {loading && <p className="text-xs text-muted">Memuat pilihan {label}…</p>}
            {failure && <><p role="alert" className="text-xs text-danger">{failure}</p><Button type="button" size="sm" variant="outline" disabled={disabled} aria-label={`Coba lagi ${label}`} onClick={() => setRetry(retry + 1)}>Coba lagi</Button></>}
            {result && !failure && <>
                <ul aria-label={`Pilihan ${label}`} className="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-border bg-surface p-1">
                    {result.data.map((option) => <li key={option.id}><button type="button" disabled={disabled} onClick={() => choose(option)} className="w-full rounded-md px-3 py-2 text-left text-sm text-ink hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/25 disabled:opacity-50 break-words">{option.label}</button></li>)}
                    {result.data.length === 0 && <li className="p-2 text-xs text-muted">Tidak ada pilihan yang sesuai.</li>}
                </ul>
                {(page > 1 || result.has_more) && <div className="flex flex-wrap items-center gap-2 text-xs text-muted">
                    <Button type="button" size="sm" variant="outline" disabled={disabled || loading || page <= 1} aria-label={`Pilihan ${label} sebelumnya`} onClick={() => { setResult(null); setPage(page - 1); }}>Sebelumnya</Button>
                    <span>Halaman {result.page}</span>
                    <Button type="button" size="sm" variant="outline" disabled={disabled || loading || !result.has_more} aria-label={`Pilihan ${label} berikutnya`} onClick={() => { setResult(null); setPage(page + 1); }}>Berikutnya</Button>
                </div>}
            </>}
        </div>}
    </div>;
}

export default function Filters({ filters, selected, lookupUrl = '/verifikasi/pilihan', actionUrl = '/verifikasi', showStatus = true }: {
    filters: FilterVerifikasi; selected: PilihanFilterTerpilih;
    lookupUrl?: string; actionUrl?: string; showStatus?: boolean;
}) {
    // Saat status antrean ditentukan server, kunci status tidak ikut dibuat agar tidak terkirim pada query.
    const initial: FilterVerifikasi = showStatus
        ? { ...filters, status: filters.status ?? 'diajukan' }
        : { renstra_id: filters.renstra_id, tahun: filters.tahun, periode_id: filters.periode_id, unit_id: filters.unit_id };
    const { data, setData, get, errors, processing } = useForm(initial);
    const [failure, setFailure] = useState('');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (processing) return;
        setFailure('');
        get(actionUrl, {
            preserveScroll: true, preserveState: false,
            onNetworkError: () => { setFailure('Filter belum dapat diterapkan. Periksa koneksi lalu coba lagi.'); return false; },
            onHttpException: (response) => { setFailure(response.status === 401 ? 'Sesi berakhir. Muat ulang halaman untuk masuk kembali.' : 'Filter belum dapat diterapkan. Muat ulang halaman dan periksa akses Anda.'); return false; },
        });
    };
    return <form onSubmit={submit} aria-label={showStatus ? 'Filter antrean verifikasi' : 'Filter antrean pengukuran disahkan'} aria-busy={processing} className="space-y-4 border-b border-border p-4 sm:p-6">
        <div className={`grid items-start gap-4 sm:grid-cols-2 ${showStatus ? 'xl:grid-cols-5' : 'xl:grid-cols-4'}`}>
            {showStatus && <Select label="Status antrean" value={data.status ?? 'diajukan'} disabled={processing} error={errors.status} onChange={(event) => setData('status', event.target.value === 'diverifikasi' ? 'diverifikasi' : 'diajukan')}
                options={[{ value: 'diajukan', label: 'Diajukan' }, { value: 'diverifikasi', label: 'Diverifikasi' }]}
                helperText={data.status === 'diverifikasi' ? 'Siap disahkan.' : 'Menunggu verifikasi.'} />}
            <FilterLookup jenis="renstra" label="Renstra" initialId={filters.renstra_id} initialOption={selected.renstra_id} disabled={processing} error={errors.renstra_id} lookupUrl={lookupUrl} onChange={(id) => setData('renstra_id', id)} />
            <Input label="Tahun" type="number" min={1} max={9999} value={data.tahun} disabled={processing} error={errors.tahun} placeholder="Semua tahun" onChange={(event) => setData('tahun', event.target.value)} />
            <FilterLookup jenis="periode" label="Periode" initialId={filters.periode_id} initialOption={selected.periode_id} disabled={processing} error={errors.periode_id} lookupUrl={lookupUrl} onChange={(id) => setData('periode_id', id)} />
            <FilterLookup jenis="unit" label="Unit" initialId={filters.unit_id} initialOption={selected.unit_id} disabled={processing} error={errors.unit_id} lookupUrl={lookupUrl} onChange={(id) => setData('unit_id', id)} />
        </div>
        {failure && <p role="alert" className="text-sm text-danger">{failure}</p>}
        <div className="flex flex-wrap items-center gap-3">
            <Button type="submit" isLoading={processing}>Terapkan filter</Button>
            <Link href={actionUrl} className="rounded px-2 py-2 text-sm text-primary underline focus:outline-none focus:ring-2 focus:ring-primary/25">Reset filter</Link>
        </div>
    </form>;
}
