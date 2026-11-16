import { useEffect, useState } from 'react';
import { http, HttpResponseError } from '@inertiajs/core';
import { Input } from '@/Components/Input';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import { statusPerhitungan } from '@/Pages/Pengukuran/types';
import type { DefinitionEditor } from './definition';

interface Result {
    indikator_id: string;
    revision: string;
    nilai: string | null;
    status_perhitungan: keyof typeof statusPerhitungan;
    sumber_nilai: string;
}

export function DefinitionPreview({ editor, paused }: { editor: DefinitionEditor; paused: boolean }) {
    const formatNilai = useFormatNilai();
    const [stale, setStale] = useState(false);
    const [values, setValues] = useState<Record<string, string>>({});
    const [preview, setPreview] = useState<{ payload: string; result?: Result; error?: string } | null>(null);
    const { recovery, handleHttpException } = useAuthRecovery();
    const path = `/perencanaan/indikator/${editor.indikator.id}/komponen/preview`;
    const active = (editor.komponen ?? []).filter((row) => row.aktif);
    const payload = JSON.stringify({
        expected_updated_at: editor.revision,
        values: Object.fromEntries(active.map((row) => [row.id, values[row.id] || null])),
    });
    const blocked = stale || paused || !editor.pagination.complete || Boolean(recovery);

    useEffect(() => {
        if (blocked) return;
        const controller = new AbortController();
        let current = true;
        const timer = window.setTimeout(async () => {
            try {
                const response = await http.getClient().request({
                    method: 'post', url: path, data: payload,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, signal: controller.signal,
                });
                if (response.status !== 200) throw new HttpResponseError('Simulasi ditolak.', response);
                const result: Result = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                if (result.indikator_id !== editor.indikator.id || result.revision !== editor.revision
                    || (result.nilai !== null && typeof result.nilai !== 'string')
                    || !Object.hasOwn(statusPerhitungan, result.status_perhitungan)) {
                    throw new Error('Respons simulasi tidak sesuai.');
                }
                if (current) setPreview({ payload, result });
            } catch (error) {
                if (!current) return;
                if (error instanceof HttpResponseError && handleHttpException(error.response, { effectiveMethod: 'post', path, mutation: false })) return;
                const status = error instanceof HttpResponseError ? error.response.status : null;
                if (status === 409) setStale(true);
                setPreview({
                    payload,
                    error: status === 409 ? 'Definisi telah berubah. Muat ulang data sebelum melanjutkan simulasi.'
                        : status === 422 ? 'Nilai simulasi tidak valid. Periksa isian komponen.'
                            : 'Simulasi belum tersedia. Periksa koneksi dan akses Anda.',
                });
            }
        }, 300);
        return () => { current = false; controller.abort(); window.clearTimeout(timer); };
    }, [blocked, payload, path, editor.indikator.id, editor.revision, handleHttpException]);

    const latest = preview?.payload === payload ? preview : null;
    const result = latest?.result;
    const message = stale ? 'Definisi telah berubah. Muat ulang data sebelum melanjutkan simulasi.'
        : blocked ? 'Simpan atau tutup editor sebelum menjalankan simulasi definisi tersimpan.'
            : latest?.error ?? (!result ? 'Menghitung simulasi…'
                : result.nilai === null ? `Belum ada nilai — ${statusPerhitungan[result.status_perhitungan]}`
                    : `${formatNilai(result.nilai, editor.indikator.desimal_tampilan)} ${editor.indikator.satuan} · ${statusPerhitungan[result.status_perhitungan]}`);

    return (
        <div className="space-y-4">
            <p className="text-sm text-muted">Menggunakan definisi tersimpan. Nilai simulasi tidak disimpan.</p>
            <AuthRecoveryNotice recovery={recovery} pending={false} />
            <div className="grid gap-3 sm:grid-cols-2">
                {active.map((row) => (
                    <Input
                        key={row.id} id={`preview-${row.id}`} label={`${row.kode} — ${row.label}`} inputMode="decimal"
                        value={values[row.id] ?? ''} disabled={blocked}
                        onChange={(event) => setValues((previous) => ({ ...previous, [row.id]: event.target.value }))}
                    />
                ))}
            </div>
            <div role="status" aria-live="polite" className="rounded-lg bg-soft p-4 text-sm text-ink">{message}</div>
        </div>
    );
}
