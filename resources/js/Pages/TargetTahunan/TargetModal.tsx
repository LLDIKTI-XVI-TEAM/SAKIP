import { useEffect, useState } from 'react';
import { Modal } from '@/Components/Modal';
import { Button } from '@/Components/Button';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { classifyRecovery, type RecoveryState } from '@/lib/authRecovery';
import { Editor } from './Editor';
import type { TargetEditorData } from './types';

export interface TargetModalProps {
    indikatorId: string;
    initialYear: number;
    onClose: () => void;
    onSaved: (message: string) => void;
}

export function TargetModal(props: TargetModalProps) {
    return <LoadTargetModal key={props.indikatorId} {...props} />;
}
function record(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Jangan memasangkan respons lama/malformed dengan form yang sedang dipilih. */
function isEditor(value: unknown, id: string, year: number): value is TargetEditorData {
    if (!record(value) || !record(value.indikator) || !record(value.renstra) || !record(value.can)) return false;
    const { indikator, renstra } = value;
    return value.indikator_id === id && value.tahun === year && value.baseline_year === year - 1
        && indikator.id === id && typeof indikator.kode === 'string' && typeof indikator.nama === 'string'
        && typeof indikator.satuan === 'string' && typeof indikator.status === 'string'
        && Number.isInteger(indikator.presisi) && Number.isInteger(indikator.desimal_tampilan)
        && typeof indikator.tahun_mulai_berlaku === 'number' && Number.isInteger(indikator.tahun_mulai_berlaku)
        && typeof renstra.id === 'string' && typeof renstra.nama === 'string' && typeof renstra.status === 'string'
        && typeof renstra.tahun_mulai === 'number' && Number.isInteger(renstra.tahun_mulai)
        && typeof renstra.tahun_selesai === 'number' && Number.isInteger(renstra.tahun_selesai)
        && renstra.tahun_mulai <= year && year <= renstra.tahun_selesai && indikator.tahun_mulai_berlaku <= year
        && (value.baseline === null || typeof value.baseline === 'string')
        && (value.target_tahunan === null || typeof value.target_tahunan === 'string')
        && typeof value.expected_state === 'string' && /^[a-f0-9]{64}$/.test(value.expected_state)
        && typeof value.has_snapshot === 'boolean' && typeof value.can.update === 'boolean'
        && (value.read_only_reason === null || typeof value.read_only_reason === 'string');
}

function LoadTargetModal({ indikatorId, initialYear, onClose, onSaved }: TargetModalProps) {
    const [request, setRequest] = useState({ year: initialYear, serial: 0 });
    const [loaded, setLoaded] = useState<{ editor: TargetEditorData; serial: number } | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [recovery, setRecovery] = useState<RecoveryState | null>(null);
    const load = (year: number) => { setLoading(true); setRequest((old) => ({ year, serial: old.serial + 1 })); };

    useEffect(() => {
        const controller = new AbortController();
        const fetchEditor = async () => {
            setError(''); setRecovery(null);
            try {
                const response = await fetch(`/perencanaan/indikator/${indikatorId}/target-tahunan/${request.year}/editor`, {
                    credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal,
                });
                if (controller.signal.aborted) return;
                if (!response.ok) {
                    setRecovery(classifyRecovery({ status: response.status, data: {}, headers: {} }, { effectiveMethod: 'get', path: `/perencanaan/indikator/${indikatorId}/target-tahunan/${request.year}/editor`, mutation: false }));
                    setError(response.status === 403 ? 'Anda tidak memiliki akses membaca indikator ini.'
                        : response.status === 401 ? 'Sesi berakhir. Masuk ulang untuk melihat data.'
                            : response.status === 419 ? 'Verifikasi keamanan gagal. Muat ulang halaman setelah menyalin isian.'
                                : 'Data belum dapat dimuat. Coba lagi.');
                    return;
                }
                const value: unknown = await response.json();
                if (controller.signal.aborted) return;
                if (!isEditor(value, indikatorId, request.year)) { setError('Data belum dapat dimuat. Coba lagi.'); return; }
                setLoaded({ editor: value, serial: request.serial });
            } catch {
                if (!controller.signal.aborted) setError('Data belum dapat dimuat. Periksa koneksi dan coba lagi.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        };
        void fetchEditor();
        return () => controller.abort();
    }, [indikatorId, request]);

    if (loaded) return <Editor key={`${loaded.editor.tahun}:${loaded.serial}`} editor={loaded.editor} loading={loading} loadError={error} loadRecovery={recovery} retryYear={request.year} onLoad={load} onClose={onClose} onSaved={onSaved} />;
    return <Modal isOpen onClose={() => { if (!loading) onClose(); }} title="Baseline & target tahunan" size="xl" bodyClassName="p-4 sm:p-6">
        {loading ? <p role="status" className="rounded-lg bg-soft p-3 text-sm text-muted">Memuat data…</p> : <div className="space-y-5">
            {recovery ? <AuthRecoveryNotice recovery={recovery} /> : <p role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger">{error}</p>}
            <div className="flex justify-end gap-3 border-t border-border pt-4">
                <Button type="button" variant="outline" onClick={onClose}>Tutup</Button>
                {!recovery && <Button type="button" onClick={() => load(request.year)}>Coba lagi</Button>}
            </div>
        </div>}
    </Modal>;
}
