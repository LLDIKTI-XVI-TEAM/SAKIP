import { useEffect, useState } from 'react';
import { http, HttpResponseError, type HttpExceptionResponse } from '@inertiajs/core';
import { useFormatNilai } from './formatNilai';
import { statusPerhitungan, type Pengukuran } from './types';

interface PreviewProps { id: string; komponen: { komponen_id: string; nilai: string }[]; satuan: string; desimalTampilan: number; paused: boolean; onRecovery: (response: HttpExceptionResponse) => void }
type Result = Pick<Pengukuran, 'nilai' | 'status_perhitungan'>;

export default function CalculationPreview({ id, komponen, satuan, desimalTampilan, paused, onRecovery }: PreviewProps) {
    const formatNilai = useFormatNilai();
    const payload = JSON.stringify({ komponen: komponen.map((item) => ({ ...item, nilai: item.nilai === '' ? null : item.nilai })) });
    const [preview, setPreview] = useState<{ payload: string; result?: Result; error?: string } | null>(null);
    useEffect(() => {
        if (paused) return;
        const controller = new AbortController();
        // Respons lama tidak boleh menggantikan pratinjau input terbaru.
        let current = true;
        const timer = window.setTimeout(async () => {
            try {
                const response = await http.getClient().request({ method: 'post', url: `/pengukuran/${id}/pratinjau`, data: payload,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, signal: controller.signal });
                if (response.status !== 200) throw new HttpResponseError('Pratinjau ditolak.', response);
                const result: Result = JSON.parse(response.data);
                if (current) setPreview({ payload, result });
            } catch (error) {
                const status = error instanceof HttpResponseError ? error.response.status : null;
                if (current && error instanceof HttpResponseError && (status === 401 || status === 419)) {
                    onRecovery(error.response);
                    return;
                }
                const message = status === 403 ? 'Akses pratinjau ditolak. Periksa kembali izin dan status pengukuran.'
                    : status === 422 ? 'Input pratinjau tidak valid. Periksa nilai komponen atau simpan draf untuk melihat rincian validasi.'
                    : 'Pratinjau belum tersedia. Periksa koneksi dan coba ubah input lagi setelah layanan pulih.';
                if (current) setPreview({ payload, error: message });
            }
        }, 300);
        return () => { current = false; window.clearTimeout(timer); controller.abort(); };
    }, [id, payload, paused, onRecovery]);
    const latest = preview?.payload === payload ? preview : null;
    return <div className="rounded-lg border border-primary/20 bg-primary/5 p-4" role="status" aria-live="polite" aria-busy={!paused && !latest}>
        <p className="text-xs font-medium text-muted">Pratinjau · belum disimpan</p>
        {paused ? <p className="mt-2 text-sm">Pratinjau dihentikan sampai halaman dipulihkan.</p> : latest?.error ? <p className="mt-2 text-sm text-warning-dark">{latest.error}</p> : <p className="mt-1 break-words text-2xl font-semibold text-primary">
            {!latest?.result ? 'Menghitung pratinjau…' : latest.result.nilai === null ? statusPerhitungan[latest.result.status_perhitungan] : `${formatNilai(latest.result.nilai, desimalTampilan)} ${satuan}`}
        </p>}
    </div>;
}
