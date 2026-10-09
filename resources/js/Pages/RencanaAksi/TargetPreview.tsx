import { useEffect, useState } from 'react';
import { http, HttpResponseError } from '@inertiajs/core';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { RencanaAksiKomponen, RencanaAksiPreview } from './types';

interface TargetPreviewProps {
    id: string;
    targets: { periode_id: string; komponen_id: string | null; nilai: string | null }[];
    alasanDeviasi: string;
    satuan: string;
    desimalTampilan: number;
    komponen: RencanaAksiKomponen[];
    namaPeriode: (periodeId: string) => string;
    /** F1: versi header halaman — dikirim ke preview agar konteks usang ditolak 409, konsisten dengan simpan. */
    expectedVersi: number;
    /** F2: token snapshot halaman — dikirim ke preview agar konteks usang ditolak 409, konsisten dengan simpan. */
    expectedSnapshotId: string | null;
    expectedSnapshotVersi: number | null;
    disabled?: boolean;
}

type PreviewState = { payload: string; result?: RencanaAksiPreview; error?: string };

/**
 * Pratinjau target server-side tanpa persistensi (F5 + F2).
 *
 * Mengikuti pola `CalculationPreview` pengukuran: debounce 300ms,
 * `POST /rencana-aksi/{id}/preview` memakai `CalculatePengukuran` yang
 * sama di server, respons lama dibatalkan/diabaikan, tanpa formula di
 * React. Skor/peringatan/deviasi reaktif terhadap input yang diedit;
 * hasil tersimpan tetap ditampilkan terpisah di halaman.
 * F1+F2: versi header + token snapshot halaman ikut dikirim; konteks usang
 * ditolak 409 agar yang ditampilkan = yang dipakai simpan.
 */
export default function TargetPreview({
    id,
    targets,
    alasanDeviasi,
    satuan,
    desimalTampilan,
    komponen,
    namaPeriode,
    expectedVersi,
    expectedSnapshotId,
    expectedSnapshotVersi,
    disabled = false,
}: TargetPreviewProps) {
    const formatNilai = useFormatNilai();
    const payload = JSON.stringify({
        expected_versi: expectedVersi,
        expected_snapshot_id: expectedSnapshotId,
        expected_snapshot_versi: expectedSnapshotVersi,
        targets: targets.map((item) => ({ ...item, keterangan: null })),
        alasan_deviasi_pk: alasanDeviasi === '' ? null : alasanDeviasi,
    });
    const [preview, setPreview] = useState<PreviewState | null>(null);

    useEffect(() => {
        if (disabled) {
            return;
        }
        const controller = new AbortController();
        // Respons lama tidak boleh menggantikan pratinjau input terbaru.
        let current = true;
        const timer = window.setTimeout(async () => {
            try {
                const response = await http.getClient().request({
                    method: 'post',
                    url: `/rencana-aksi/${id}/preview`,
                    data: payload,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    signal: controller.signal,
                });
                if (response.status !== 200) {
                    throw new HttpResponseError('Pratinjau ditolak.', response);
                }
                const result: RencanaAksiPreview = JSON.parse(response.data);
                if (current) {
                    setPreview({ payload, result });
                }
            } catch (error) {
                const status = error instanceof HttpResponseError ? error.response.status : null;
                const message =
                    status === 403
                        ? 'Akses pratinjau ditolak. Periksa kembali izin dan status rencana aksi.'
                        : status === 409
                          ? 'Data telah berubah. Muat ulang halaman agar pratinjau memakai data terbaru.'
                          : status === 422
                            ? 'Input pratinjau tidak valid. Periksa nilai target atau simpan untuk melihat rincian validasi.'
                            : status === 401 || status === 419
                              ? 'Sesi berakhir. Muat ulang halaman untuk melanjutkan pratinjau.'
                              : 'Pratinjau belum tersedia. Periksa koneksi dan coba ubah input lagi setelah layanan pulih.';
                if (current) {
                    setPreview({ payload, error: message });
                }
            }
        }, 300);
        return () => {
            current = false;
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [id, payload, disabled]);

    const latest = preview?.payload === payload ? preview : null;

    const kodeKomponen = (komponenId: string | null): string => {
        if (komponenId === null) {
            return 'target';
        }
        return komponen.find((item) => item.komponen_id === komponenId)?.kode ?? 'Komponen';
    };

    return (
        <div className="rounded-lg border border-primary/20 bg-primary/5 p-4" role="status" aria-live="polite" aria-busy={!disabled && !latest}>
            <p className="text-xs font-medium text-muted">Pratinjau · belum disimpan</p>
            {disabled ? (
                <p className="mt-2 text-sm">Pratinjau tidak tersedia untuk formulir hanya-baca.</p>
            ) : latest?.error ? (
                <p className="mt-2 text-sm text-warning-dark">{latest.error}</p>
            ) : (
                <>
                    <ul className="mt-2 space-y-2 text-sm">
                        {(latest?.result?.periode ?? []).filter((baris) => baris.efektif).map((baris) => (
                            <li key={baris.id} className="flex flex-wrap items-baseline justify-between gap-2">
                                <span>
                                    {namaPeriode(baris.id)}
                                    {baris.peringatan_turun && (
                                        <span className="ml-2 text-xs font-medium text-warning-dark" data-testid={`preview-peringatan-${baris.id}`}>
                                            turun
                                            {baris.komponen_turun.length > 0
                                                ? `: ${baris.komponen_turun.map((cid) => kodeKomponen(cid)).join(', ')}`
                                                : ''}
                                        </span>
                                    )}
                                </span>
                                <span className="font-mono font-semibold text-primary" data-testid={`preview-skor-${baris.id}`}>
                                    {!latest?.result
                                        ? 'Menghitung pratinjau…'
                                        : baris.skor.nilai === null
                                          ? (baris.skor.status_perhitungan === 'tidak_dapat_dihitung' ? 'Tidak dapat dihitung' : 'Belum diisi')
                                          : `${formatNilai(baris.skor.nilai, desimalTampilan)} ${satuan}`}
                                </span>
                            </li>
                        ))}
                    </ul>
                    {!latest?.result && <p className="mt-2 text-sm">Menghitung pratinjau…</p>}
                    {latest?.result && (
                        <p className="mt-3 text-xs text-muted" data-testid="preview-deviasi">
                            {latest.result.deviasi_pk.dapat_dinilai
                                ? latest.result.deviasi_pk.ada
                                    ? `Deviasi pratinjau: skor ${latest.result.deviasi_pk.skor_periode_terakhir !== null ? `${formatNilai(latest.result.deviasi_pk.skor_periode_terakhir, desimalTampilan)} ${satuan}` : '—'} vs target PK ${latest.result.deviasi_pk.target_pk !== null ? `${formatNilai(latest.result.deviasi_pk.target_pk, desimalTampilan)} ${satuan}` : '—'}.`
                                    : 'Pratinjau setara target PK; alasan deviasi tidak diperlukan.'
                                : 'Deviasi pratinjau belum dapat dinilai.'}
                        </p>
                    )}
                </>
            )}
        </div>
    );
}
