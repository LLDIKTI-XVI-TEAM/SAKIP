import { useForm } from '@inertiajs/react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Select } from '@/Components/Select';
import { Textarea } from '@/Components/Textarea';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { arahLabel, peranKomponenLabel, tipePerhitunganLabel } from '@/lib/indikator';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { KomponenSnapshot, SelisihKolom, SnapshotIndikatorRow, SnapshotPeriode } from '@/types/snapshot-indikator';

interface KoreksiSnapshotModalProps {
    jadwalId: string;
    periode: SnapshotPeriode[];
    /** Baris terkini dari props halaman; pemanggil memasang modal dengan `key` indikator agar isian indikator lain tidak terbawa. */
    baris: SnapshotIndikatorRow;
    onClose: () => void;
    /** Hasil koreksi tidak terkonfirmasi (jaringan/sesi); halaman meminta pengguna memuat ulang. */
    onUnknown: () => void;
}

const labelArah: Record<string, string> = arahLabel;
const labelTipe: Record<string, string> = tipePerhitunganLabel;

function nilaiTampil(baris: SelisihKolom, nilai: SelisihKolom['lama'], formatDesimal: (nilai: string) => string): string {
    if (nilai === null || nilai === '') return '—';
    if (baris.kolom === 'dinilai_tahunan') return nilai ? 'Ya' : 'Tidak';
    if (baris.kolom === 'arah') return labelArah[String(nilai)] ?? String(nilai);
    if (baris.kolom === 'tipe_perhitungan') return labelTipe[String(nilai)] ?? String(nilai);
    if (baris.kolom === 'target' || baris.kolom === 'baseline') return formatDesimal(String(nilai));
    return String(nilai);
}

function DaftarKomponen({ judul, komponen }: { judul: string; komponen: KomponenSnapshot[] }) {
    return (
        <div className="min-w-0">
            <p className="text-xs font-semibold text-muted">{judul}</p>
            {komponen.length === 0 ? (
                <p className="text-sm">Tidak ada komponen</p>
            ) : (
                <ul className="space-y-1 text-sm">
                    {komponen.map((item) => (
                        <li key={item.komponen_id} className="break-words">
                            <span className="font-semibold">{item.kode}</span> {item.label} · {peranKomponenLabel[item.peran] ?? item.peran} · bobot {item.bobot}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Koreksi snapshot menerbitkan versi baru dari data master terkini (Q79 §79.1). Klien hanya memilih periode mulai serta
 * mengisi alasan dan rujukan; selisih, izin per kolom, dan kelayakan dinilai ulang server di bawah lock.
 */
export function KoreksiSnapshotModal({ jadwalId, periode, baris, onClose, onUnknown }: KoreksiSnapshotModalProps) {
    const recovery = useAuthRecovery();
    const formatNilai = useFormatNilai();
    const formatDesimal = (nilai: string) => formatNilai(nilai, baris.desimal_tampilan);
    const form = useForm({ periode_mulai_id: baris.periode_mulai?.id ?? '', alasan_koreksi: '', rujukan_koreksi: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const serverErrors = Object.entries(errors).filter(([field]) => !['alasan_koreksi', 'rujukan_koreksi', 'periode_mulai_id'].includes(field));
    const urutanMinimum = baris.periode_mulai?.urutan ?? 0;
    const pilihan = [...periode].sort((a, b) => a.urutan - b.urutan).filter((item) => item.urutan >= urutanMinimum);
    const terpilih = pilihan.find((item) => item.id === form.data.periode_mulai_id);
    const adaPeriodeSebelumnya = terpilih !== undefined && periode.some((item) => item.urutan < terpilih.urutan);
    // Props terbaru setelah penolakan usang bisa sudah sama dengan master; server tetap menolak koreksi tanpa selisih.
    const adaSelisih = baris.selisih.kolom.length > 0 || baris.selisih.komponen !== null;
    const tidakDapatDikirim = !adaSelisih || form.data.alasan_koreksi.trim() === '' || form.data.rujukan_koreksi.trim() === ''
        || form.data.periode_mulai_id === '' || Boolean(recovery.recovery);
    const path = `/jadwal/${jadwalId}/snapshot/${baris.snapshot_id}/koreksi`;

    const confirm = () => {
        if (form.processing || tidakDapatDikirim) return;
        // Token diambil dari versi yang sedang ditampilkan: setelah penolakan usang, props terbaru membawa selisih dan versi baru.
        form.transform((data) => ({ ...data, expected_snapshot_id: baris.snapshot_id, expected_snapshot_versi: baris.nomor_versi }));
        form.post(path, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            // Hasil ambigu tidak diulang otomatis: koreksi yang sudah tersimpan akan ditolak sebagai versi usang.
            // Pemulihan sesi menjaga modal terbuka agar alasan dan rujukan dapat disalin sebelum masuk ulang.
            onHttpException: (response) => {
                if (!recovery.handleHttpException(response, { effectiveMethod: 'post', path, mutation: true })) onUnknown();
                return false;
            },
            onNetworkError: () => {
                onUnknown();
                return false;
            },
        });
    };

    return (
        <AuditReasonModal
            open
            title={`Koreksi snapshot ${baris.kode ?? baris.nama}`}
            description="Versi baru disalin dari data master terkini. Versi lama tetap utuh untuk data yang sudah merujuknya."
            reason={form.data.alasan_koreksi}
            error={errors.alasan_koreksi}
            busy={form.processing}
            submitDisabled={tidakDapatDikirim}
            reasonRequired
            confirmLabel="Terbitkan versi koreksi"
            onReasonChange={(value) => form.setData('alasan_koreksi', value)}
            onClose={() => !form.processing && onClose()}
            onConfirm={confirm}
            notice={
                (
                    <div className="mb-4 space-y-4">
                        <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
                        {serverErrors.length > 0 && (
                            <div role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm">
                                {serverErrors.map(([field, message]) => (
                                    <p key={field}>{message}</p>
                                ))}
                            </div>
                        )}
                        <div>
                            <p className="mb-2 text-sm font-semibold">Perubahan yang akan disalin</p>
                            {!adaSelisih && (
                                <p className="rounded-lg border border-border p-3 text-sm">
                                    Data master sudah sama dengan versi {baris.nomor_versi}; koreksi tidak diperlukan.
                                </p>
                            )}
                            {baris.selisih.kolom.length > 0 && (
                                <dl className="divide-y divide-border rounded-lg border border-border text-sm">
                                    {baris.selisih.kolom.map((item) => (
                                        <div key={item.kolom} className="grid gap-1 p-3 sm:grid-cols-3 sm:gap-3">
                                            <dt className="font-semibold">{item.label}</dt>
                                            <dd className="min-w-0 break-words">
                                                <span className="text-xs text-muted">Versi {baris.nomor_versi}: </span>
                                                {nilaiTampil(item, item.lama, formatDesimal)}
                                            </dd>
                                            <dd className="min-w-0 break-words">
                                                <span className="text-xs text-muted">Data master: </span>
                                                {nilaiTampil(item, item.baru, formatDesimal)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                            {baris.selisih.komponen && (
                                <div className="mt-3 grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-2">
                                    <DaftarKomponen judul={`Komponen versi ${baris.nomor_versi}`} komponen={baris.selisih.komponen.lama} />
                                    <DaftarKomponen judul="Komponen data master" komponen={baris.selisih.komponen.baru} />
                                </div>
                            )}
                        </div>
                        <Select
                            id="koreksi-snapshot-periode-mulai"
                            label="Periode mulai berlaku"
                            required
                            value={form.data.periode_mulai_id}
                            error={errors.periode_mulai_id}
                            onChange={(event) => form.setData('periode_mulai_id', event.target.value)}
                            options={pilihan.map((item) => ({ value: item.id, label: item.nama }))}
                        />
                        <Textarea
                            id="koreksi-snapshot-rujukan"
                            label="Rujukan resmi koreksi"
                            required
                            rows={2}
                            maxLength={1000}
                            helperText="Contoh: nomor surat persetujuan atau nota dinas yang menjadi dasar koreksi."
                            value={form.data.rujukan_koreksi}
                            error={errors.rujukan_koreksi}
                            onChange={(event) => form.setData('rujukan_koreksi', event.target.value)}
                        />
                        {terpilih && (
                            <div className="space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
                                <p>
                                    {adaPeriodeSebelumnya && `Periode sebelum ${terpilih.nama} tetap memakai versi ${baris.nomor_versi}. `}
                                    Pengukuran baru sejak {terpilih.nama} memakai versi koreksi dan baru dapat diajukan setelah Rencana Aksi dibuka kembali dan
                                    disahkan ulang.
                                </p>
                                <p>Pengukuran draf atau dikembalikan pada versi lama tidak dapat diajukan setelah Rencana Aksi disahkan ulang.</p>
                                {terpilih.urutan > urutanMinimum && (
                                    <p>Target draf Rencana Aksi untuk periode sebelum {terpilih.nama} dilepas saat draf disimpan berikutnya.</p>
                                )}
                            </div>
                        )}
                    </div>
                )
            }
        />
    );
}
