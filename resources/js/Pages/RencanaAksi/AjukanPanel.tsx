import { useRef, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Button } from '@/Components/Button';
import { Modal } from '@/Components/Modal';
import type { SharedPageProps } from '@/types/auth';
import type { RencanaAksiPengajuan } from './types';

interface AjukanPanelProps {
    rencanaAksiId: string;
    expectedVersi: number;
    pengajuan: RencanaAksiPengajuan;
    /** Eligibility dari server; kartu kesiapan tetap tampil walau false agar alasan terlihat. */
    canSubmit: boolean;
    /** Formulir target punya perubahan belum tersimpan; pengajuan memakai data tersimpan saja. */
    kotor: boolean;
    onMuatUlang: () => void;
}

/** Konflik versi/snapshot memakai blok muat ulang; field lain di bag `ajukan` tampil sebagai pesan. */
const FIELD_KONFLIK = ['expected_versi', 'expected_snapshot_id'];

export default function AjukanPanel({ rencanaAksiId, expectedVersi, pengajuan, canSubmit, kotor, onMuatUlang }: AjukanPanelProps) {
    // Galat dibaca dari props halaman (bag `ajukan`), bukan `useForm().errors`:
    // penolakan jepit usang membawa token snapshot baru sehingga formulir induk
    // di-remount dan state lokal hilang. Bag terpisah juga menjaga galat simpan
    // target (bag default, field sama) tidak tampil ganda sebagai galat pengajuan.
    const errors = (usePage<SharedPageProps>().props.errors?.ajukan ?? {}) as Record<string, string | undefined>;
    const { post, processing } = useForm({ expected_versi: expectedVersi });
    const [konfirmasi, setKonfirmasi] = useState(false);
    const [requestError, setRequestError] = useState('');
    const galatRef = useRef<HTMLDivElement>(null);

    const konflik = errors.expected_versi ?? errors.expected_snapshot_id;
    const galat = Object.entries(errors).find(([field, pesan]) => !FIELD_KONFLIK.includes(field) && pesan)?.[1];

    const gagal = (pesan: string): void => {
        setKonfirmasi(false);
        setRequestError(pesan);
    };

    const ajukan = (): void => {
        setRequestError('');
        post(`/rencana-aksi/${rencanaAksiId}/ajukan`, {
            errorBag: 'ajukan',
            preserveScroll: true,
            onSuccess: () => setKonfirmasi(false),
            onError: () => {
                setKonfirmasi(false);
                requestAnimationFrame(() => galatRef.current?.focus());
            },
            onCancel: () => gagal('Permintaan dibatalkan. Hasil pengajuan belum diketahui; periksa data terbaru sebelum mencoba kembali.'),
            onNetworkError: () => {
                gagal('Koneksi terputus. Hasil pengajuan belum diketahui; periksa status rencana aksi sebelum mencoba kembali.');
                return false;
            },
            onHttpException: (response) => {
                if (response.status === 429) {
                    gagal('Terlalu sering mengajukan. Coba lagi sebentar.');
                } else if (response.status === 403) {
                    // 403 juga muncul bila pengajuan sukses lalu GET redirect ditolak (izin baca dicabut),
                    // jadi status ini tidak membuktikan pengajuan ditolak.
                    gagal('Akses ditolak; hasil pengajuan belum dapat dipastikan. Muat ulang halaman untuk memeriksa status rencana aksi sebelum mencoba kembali.');
                } else {
                    gagal('Hasil pengajuan belum dapat dipastikan. Periksa data terbaru sebelum mencoba kembali.');
                }
                return false;
            },
        });
    };

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>Kesiapan pengajuan</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                    {/* Isi lengkap tanpa eligibility (izin/jendela) tidak boleh terbaca "siap" tanpa tombol. */}
                    {pengajuan.siap ? (
                        <p className="text-muted">
                            {canSubmit ? 'Semua syarat pengajuan terpenuhi.' : 'Isi sudah lengkap. Pengajuan belum tersedia untuk akun Anda saat ini.'}
                        </p>
                    ) : (
                        <div className="rounded-lg border border-warning/30 bg-warning/10 p-4 text-warning-dark">
                            <p className="font-semibold">Pengajuan belum dapat dilakukan</p>
                            <ul className="mt-2 list-disc space-y-1 pl-5">
                                {pengajuan.alasan.map((alasan) => (
                                    <li key={alasan}>{alasan}</li>
                                ))}
                            </ul>
                            <p className="mt-2">Simpan draf untuk memperbarui hasil.</p>
                        </div>
                    )}

                    <div ref={galatRef} tabIndex={-1} className="space-y-3 outline-none">
                        {konflik && (
                            <div role="alert" className="rounded-lg border border-danger/30 bg-danger/10 p-4">
                                <p className="font-semibold text-danger-dark">Data telah berubah di server</p>
                                <p className="mt-1 text-danger-dark">{konflik}</p>
                                <Button type="button" variant="outline" onClick={onMuatUlang} disabled={processing} className="mt-3">
                                    Muat ulang data terbaru
                                </Button>
                            </div>
                        )}
                        {galat && <p role="alert" className="text-danger">{galat}</p>}
                        {requestError !== '' && <p role="alert" className="text-danger">{requestError}</p>}
                    </div>

                    {canSubmit && (
                        <div className="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-4">
                            {kotor && <p className="text-muted">Simpan atau buang perubahan sebelum mengajukan.</p>}
                            <Button
                                type="button"
                                variant="primary"
                                onClick={() => setKonfirmasi(true)}
                                disabled={!pengajuan.siap || kotor || processing}
                                isLoading={processing}
                            >
                                <Send className="mr-2 h-4 w-4" aria-hidden="true" />
                                Ajukan ke Tim Perencanaan
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Modal
                isOpen={konfirmasi}
                onClose={() => {
                    if (!processing) {
                        setKonfirmasi(false);
                    }
                }}
                title="Ajukan Rencana Aksi"
                footer={
                    <>
                        <Button type="button" variant="outline" onClick={() => setKonfirmasi(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="button" variant="primary" onClick={ajukan} isLoading={processing}>
                            Ajukan
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-ink">
                    Data yang sudah tersimpan akan dikunci sebagai versi pengajuan dan tidak dapat diubah sampai dikembalikan oleh Tim Perencanaan.
                </p>
            </Modal>
        </>
    );
}
