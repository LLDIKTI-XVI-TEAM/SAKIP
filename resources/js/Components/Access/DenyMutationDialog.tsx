import type { HttpExceptionResponse } from '@inertiajs/core';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { Button } from '@/Components/Button';
import { primaryButton, secondaryButton } from '@/Pages/Auth/AuthShell';
import type { DenyRow, OptionPage, PermissionOption, UnitOption, UserOption } from '@/types/deny';

const fieldClass = 'mt-2 w-full rounded-lg border border-border bg-surface p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50';

function DenyLookup<T extends { id: string }>({ id, label, endpoint, value, onChange, describe, disabled, error, paused, onRecovery }: {
    id: string; label: string; endpoint: string; value: string; onChange: (id: string) => void;
    describe: (option: T) => string; disabled: boolean; error?: string; paused: boolean; onRecovery: (response: HttpExceptionResponse) => void;
}) {
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState({ q: '', page: 1 });
    const [result, setResult] = useState<OptionPage<T>>({ items: [], page: 1, hasMore: false });
    const [selected, setSelected] = useState<T | null>(null);
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState('');
    useEffect(() => {
        if (paused) { setLoading(false); return; }
        const controller = new AbortController();
        let current = true;
        setLoading(true); setFailure('');
        void fetch(`${endpoint}?${new URLSearchParams({ q: query.q, page: String(query.page) })}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then(async (response) => {
                if (!current) return;
                if (response.status === 401 || response.status === 419) {
                    const data = await response.text();
                    if (current) onRecovery({ status: response.status, data, headers: {} });
                    return;
                }
                if (!response.ok) throw new Error(response.status === 403 ? 'Izin pembacaan ditolak. Muat ulang daftar untuk memeriksa akses.' : 'Pilihan belum dapat dimuat. Coba cari kembali.');
                const data: OptionPage<T> = await response.json();
                if (current) setResult(data);
            })
            .catch((error: unknown) => { if (current) setFailure(error instanceof Error ? error.message : 'Pilihan belum dapat dimuat. Coba cari kembali.'); })
            .finally(() => { if (current) setLoading(false); });
        return () => { current = false; controller.abort(); };
    }, [endpoint, query, paused, onRecovery]);
    const options = selected && !result.items.some((item) => item.id === selected.id) ? [selected, ...result.items] : result.items;
    const applySearch = () => { if (paused) return; setQuery({ q: search, page: 1 }); };
    return <div className="space-y-2">
        <div className="flex items-end gap-2">
            <div className="min-w-0 flex-1"><label htmlFor={`${id}-search`} className="block text-sm font-medium">Cari {label.toLowerCase()}</label>
                <input id={`${id}-search`} type="search" maxLength={100} value={search} disabled={disabled || paused} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); applySearch(); } }} className={fieldClass} /></div>
            <Button type="button" variant="outline" className={secondaryButton} disabled={disabled || paused} aria-label={`Cari ${label.toLowerCase()}`} onClick={applySearch}>Cari</Button>
        </div>
        <label htmlFor={id} className="block text-sm font-medium">{label}</label>
        <select id={id} required value={value} disabled={disabled || paused || loading || Boolean(failure)} aria-invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : `${id}-status`} className={fieldClass} onChange={(event) => {
            setSelected(options.find((option) => option.id === event.target.value) ?? null); onChange(event.target.value);
        }}>
            <option value="" disabled>Pilih {label.toLowerCase()}</option>
            {options.map((option) => <option key={option.id} value={option.id}>{describe(option)}</option>)}
        </select>
        {error && <p id={`${id}-error`} role="alert" className="text-sm text-danger">{error}</p>}
        <p id={`${id}-status`} role={failure ? 'alert' : 'status'} className={`text-xs ${failure ? 'text-danger' : 'text-muted'}`}>
            {failure || (loading ? 'Memuat pilihan…' : result.items.length === 0 ? 'Tidak ada pilihan yang sesuai.' : `Halaman ${result.page}`)}
        </p>
        {(result.page > 1 || result.hasMore) && <div className="flex gap-3">
            <button type="button" disabled={disabled || paused || loading || result.page <= 1} onClick={() => setQuery({ ...query, page: result.page - 1 })} className="rounded p-2 text-sm text-primary underline focus:ring-2 focus:ring-primary disabled:opacity-50">{label} sebelumnya</button>
            <button type="button" disabled={disabled || paused || loading || !result.hasMore} onClick={() => setQuery({ ...query, page: result.page + 1 })} className="rounded p-2 text-sm text-primary underline focus:ring-2 focus:ring-primary disabled:opacity-50">{label} berikutnya</button>
        </div>}
    </div>;
}

type DenyForm = { user_id: string; permission_id: string; unit_id: string | null; alasan: string; deny_id?: string };
export function DenyMutationDialog({ deny, actorId, onClose, onSaved }: { deny: DenyRow | null; actorId?: string; onClose: () => void; onSaved: () => void }) {
    const dialog = useRef<HTMLDialogElement>(null);
    const reason = useRef<HTMLTextAreaElement>(null);
    const alert = useRef<HTMLParagraphElement>(null);
    const [scope, setScope] = useState<'global' | 'unit'>('global');
    const [message, setMessage] = useState('');
    const recovery = useAuthRecovery();
    const handleLookupRecovery = useCallback((response: HttpExceptionResponse) => { recovery.handleHttpException(response, { effectiveMethod: 'get', path: '/akses/deny/opsi', mutation: false }); }, [recovery.handleHttpException]);
    const form = useForm<DenyForm>({ user_id: '', permission_id: '', unit_id: null, alasan: '' });
    const needsReload = Boolean(form.errors.deny_id || message);
    const missingSelection = !deny && (!form.data.user_id || !form.data.permission_id || (scope === 'unit' && !form.data.unit_id));
    const self = !deny && form.data.user_id === actorId;
    useEffect(() => {
        const element = dialog.current; element?.showModal();
        return () => element?.close();
    }, []);
    useEffect(() => {
        if (form.processing) return;
        if (needsReload) alert.current?.focus();
        else {
            const field = ['user_id', 'permission_id', 'unit_id'].find((key) => form.errors[key as keyof DenyForm]);
            if (field) dialog.current?.querySelector<HTMLSelectElement>(`#deny-${field}`)?.focus();
            else if (form.errors.alasan) reason.current?.focus();
        }
    }, [form.processing, form.errors, needsReload, message]);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || recovery.recovery || needsReload || missingSelection) return;
        form.transform((data) => deny ? { alasan: data.alasan } : { user_id: data.user_id, permission_id: data.permission_id, unit_id: scope === 'global' ? null : data.unit_id, alasan: data.alasan });
        form.post(deny ? `/akses/deny/${deny.id}/cabut` : '/akses/deny', {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.component === 'Access/DenyIndex' && ['created', 'revoked'].includes(String(page.flash.denyStatus))) onSaved();
                else if (page.component !== 'Access/DenyResult' || !['created', 'revoked'].includes(String(page.props.status))) setMessage('Hasil pembatasan izin belum diketahui. Muat ulang daftar sebelum mencoba kembali.');
            },
            onCancel: () => { setMessage('Permintaan dibatalkan. Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); },
            onNetworkError: () => { setMessage('Koneksi terputus. Hasil pembatasan izin belum diketahui. Muat ulang daftar sebelum mencoba kembali.'); return false; },
            onHttpException: (response) => { if (recovery.handleHttpException(response, { effectiveMethod: 'post', path: deny ? `/akses/deny/${deny.id}/cabut` : '/akses/deny', mutation: true })) return false; setMessage(response.status === 403 ? 'Izin tindakan ditolak. Periksa akses sebelum mencoba kembali.' : 'Hasil tindakan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.'); return false; },
        });
    };
    return <dialog ref={dialog} aria-labelledby="deny-title" aria-describedby={self ? 'deny-description deny-self-warning' : 'deny-description'} onCancel={(event) => { if (form.processing || message) event.preventDefault(); }} onClose={onClose} className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl border border-border bg-surface p-5 text-ink shadow-xl backdrop:bg-ink/50 sm:p-6">
        <h2 id="deny-title" className="text-lg font-semibold">{deny ? 'Cabut deny' : 'Tambah deny'}</h2>
        <p id="deny-description" className="mt-2 text-sm text-muted">{deny ? 'Mencabut deny tidak otomatis memberikan izin. Akses mengikuti peran, grant, dan pembatasan lain yang masih berlaku.' : 'Global membatasi izin di seluruh konteks yang relevan. Unit tertentu hanya membatasi pemeriksaan izin pada unit tersebut.'}</p>
        <form onSubmit={submit} className="mt-5 space-y-5" aria-busy={form.processing}>
            {deny ? <dl className="space-y-2 rounded-lg bg-soft p-4 text-sm">
                <div><dt className="font-medium">Pengguna</dt><dd className="break-words">{deny.user.nama} ({deny.user.email})</dd></div>
                <div><dt className="font-medium">Izin dan cakupan</dt><dd className="break-words">{deny.permission.kode} · {deny.unit?.nama ?? 'Global'}</dd></div>
                <div><dt className="font-medium">Alasan awal</dt><dd className="whitespace-pre-wrap break-words">{deny.alasan}</dd></div>
            </dl> : <>
                <DenyLookup<UserOption> id="deny-user_id" label="Pengguna" endpoint="/akses/deny/opsi/pengguna" value={form.data.user_id} onChange={(id) => form.setData('user_id', id)} describe={(user) => `${user.nama} (${user.email})${user.is_active ? '' : ' — nonaktif'}`} paused={Boolean(recovery.recovery)} onRecovery={handleLookupRecovery} disabled={form.processing} error={form.errors.user_id} />
                <DenyLookup<PermissionOption> id="deny-permission_id" label="Izin" endpoint="/akses/deny/opsi/izin" value={form.data.permission_id} onChange={(id) => form.setData('permission_id', id)} describe={(permission) => `${permission.kode}${permission.keterangan ? ` — ${permission.keterangan}` : ''}`} paused={Boolean(recovery.recovery)} onRecovery={handleLookupRecovery} disabled={form.processing} error={form.errors.permission_id} />
                <fieldset disabled={form.processing} className="space-y-2"><legend className="text-sm font-medium">Cakupan</legend><div className="flex flex-wrap gap-5 text-sm">
                    <label className="flex items-center gap-2"><input type="radio" name="scope" checked={scope === 'global'} onChange={() => { setScope('global'); form.setData('unit_id', null); }} />Global</label>
                    <label className="flex items-center gap-2"><input type="radio" name="scope" checked={scope === 'unit'} onChange={() => { setScope('unit'); form.setData('unit_id', ''); }} />Unit tertentu</label>
                </div></fieldset>
                {scope === 'unit' && <DenyLookup<UnitOption> id="deny-unit_id" label="Unit" endpoint="/akses/deny/opsi/unit" value={form.data.unit_id ?? ''} onChange={(id) => form.setData('unit_id', id)} describe={(unit) => `${unit.nama}${unit.status === 'nonaktif' ? ' — nonaktif' : ''}`} paused={Boolean(recovery.recovery)} onRecovery={handleLookupRecovery} disabled={form.processing} error={form.errors.unit_id} />}
                {self && <p id="deny-self-warning" className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">Anda membatasi izin akun sendiri. Akses dan menu dapat hilang setelah disimpan; Anda mungkin memerlukan pengelola lain untuk memulihkannya.</p>}
            </>}
            <div><label htmlFor="deny-reason" className="block text-sm font-medium">{deny ? 'Alasan pencabutan deny' : 'Alasan pembatasan'} <span className="text-danger">*</span></label>
                <textarea ref={reason} autoFocus={Boolean(deny)} id="deny-reason" required maxLength={2000} rows={3} value={form.data.alasan} disabled={form.processing} onChange={(event) => form.setData('alasan', event.target.value)} aria-invalid={Boolean(form.errors.alasan)} aria-describedby={form.errors.alasan ? 'deny-reason-error' : 'deny-reason-help'} className={fieldClass} />
                {form.errors.alasan ? <p id="deny-reason-error" role="alert" className="mt-1 text-sm text-danger">{form.errors.alasan}</p> : <p id="deny-reason-help" className="mt-1 text-xs text-muted">Wajib diisi, maksimal 2.000 karakter. Dicatat dalam jejak audit.</p>}
            </div>
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {needsReload && !recovery.recovery && <div className="space-y-2 rounded-lg bg-soft p-3"><p ref={alert} tabIndex={-1} role="alert" className="text-sm text-danger">{form.errors.deny_id || message}</p><Button type="button" variant="outline" className={secondaryButton} disabled={form.processing} onClick={() => router.get('/akses/deny', {}, { replace: true, preserveState: false })}>Muat ulang daftar</Button></div>}
            <div className="flex flex-wrap justify-end gap-3"><Button type="button" variant="outline" className={secondaryButton} disabled={form.processing || Boolean(message)} onClick={onClose}>Batal</Button><Button type="submit" className={primaryButton} isLoading={form.processing} disabled={Boolean(recovery.recovery) || needsReload || missingSelection}>{deny ? 'Konfirmasi cabut deny' : 'Simpan deny'}</Button></div>
        </form>
    </dialog>;
}
