import { useState, type FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { AuditReasonModal } from '@/Components/AuditReasonModal';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { ARAH_DAMPAK_OPTIONS, type ArahDampak, type KlaimKegiatanItem, type KlaimKegiatanPanel, type OpsiRencanaAksi } from '@/types/kegiatan';

interface KlaimPanelProps {
    kegiatanId: string;
    klaim: KlaimKegiatanPanel;
}

/** Galat validasi bisnis gerbang klaim (422) yang tidak terikat ke field form. */
function galatBisnis(errs: Record<string, string | undefined>): string | undefined {
    return errs.status_alur ?? errs.jendela ?? errs.unit_id ?? errs.indikator_id ?? errs.klaim;
}

/** Pilihan komponen awal yang masih tersedia: tanpa komponen bila belum diklaim, selain itu komponen pertama. */
function komponenAwal(opsi: OpsiRencanaAksi | undefined): string {
    return !opsi || opsi.tanpa_komponen ? '' : (opsi.komponen[0]?.id ?? '');
}

/** Panel klaim kegiatan ke Rencana Aksi (PRD §16): hanya penjelasan dukungan, tidak mengubah nilai capaian. */
export function KlaimPanel({ kegiatanId, klaim }: KlaimPanelProps) {
    const { data, setData, post, processing, errors, clearErrors } = useForm<{
        rencana_aksi_id: string;
        komponen_id: string;
        arah_dampak: ArahDampak;
        catatan: string;
    }>({ rencana_aksi_id: klaim.opsiRencanaAksi[0]?.id ?? '', komponen_id: komponenAwal(klaim.opsiRencanaAksi[0]), arah_dampak: 'menambah', catatan: '' });
    const [hapusTarget, setHapusTarget] = useState<KlaimKegiatanItem | null>(null);
    const [alasan, setAlasan] = useState('');
    const [menghapus, setMenghapus] = useState(false);
    const [galatHapus, setGalatHapus] = useState<string | undefined>();
    const [galatKirim, setGalatKirim] = useState('');

    const fieldErrors = errors as Record<string, string | undefined>;
    const opsiTerpilih = klaim.opsiRencanaAksi.find((opsi) => opsi.id === data.rencana_aksi_id);
    const opsiKomponen = [
        ...(opsiTerpilih?.tanpa_komponen ? [{ value: '', label: 'Tanpa komponen tertentu' }] : []),
        ...(opsiTerpilih?.komponen ?? []).map((k) => ({ value: k.id, label: k.nama })),
    ];

    const kirim = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        if (processing) return;
        setGalatKirim('');
        post(`/kegiatan/${kegiatanId}/klaim`, {
            preserveScroll: true,
            onSuccess: (page) => {
                // Opsi server berubah setelah klaim tersimpan; default dipilih dari kombinasi yang masih tersedia.
                const opsi = (page.props.klaim as KlaimKegiatanPanel | null | undefined)?.opsiRencanaAksi ?? [];
                setData({ rencana_aksi_id: opsi[0]?.id ?? '', komponen_id: komponenAwal(opsi[0]), arah_dampak: 'menambah', catatan: '' });
                clearErrors();
            },
            onHttpException: (response) => {
                setGalatKirim(response.status === 403
                    ? 'Izin klaim ditolak. Muat ulang halaman untuk melihat akses terbaru.'
                    : 'Hasil klaim belum dapat dipastikan. Muat ulang halaman sebelum mencoba kembali.');
                return false;
            },
            onNetworkError: () => {
                setGalatKirim('Koneksi terputus. Muat ulang halaman untuk memastikan status klaim.');
                return false;
            },
        });
    };

    const tutupHapus = (): void => {
        setHapusTarget(null);
        setAlasan('');
        setGalatHapus(undefined);
    };

    const konfirmasiHapus = (): void => {
        if (!hapusTarget || menghapus) return;
        setMenghapus(true);
        setGalatHapus(undefined);
        router.delete(`/kegiatan/${kegiatanId}/klaim/${hapusTarget.id}`, {
            data: { alasan },
            preserveScroll: true,
            onSuccess: () => tutupHapus(),
            onError: (errs) => setGalatHapus(errs.alasan ?? galatBisnis(errs) ?? 'Penghapusan klaim gagal.'),
            onNetworkError: () => {
                setGalatHapus('Koneksi terputus. Muat ulang halaman untuk memastikan status klaim.');
                return false;
            },
            onHttpException: (response) => {
                setGalatHapus(response.status === 403
                    ? 'Klaim hanya dapat dihapus oleh pembuat klaim atau Perencanaan. Muat ulang halaman untuk melihat akses terbaru.'
                    : 'Hasil penghapusan belum dapat dipastikan. Muat ulang halaman sebelum mencoba kembali.');
                return false;
            },
            onFinish: () => setMenghapus(false),
        });
    };

    const galatUmum = galatKirim || galatBisnis(fieldErrors);

    return (
        <Card className="mt-6 max-w-4xl">
            <CardHeader>
                <CardTitle>Klaim Rencana Aksi</CardTitle>
                <p className="text-sm text-muted">Klaim menjelaskan dukungan kegiatan terhadap rencana aksi dan komponennya. Klaim tidak mengubah nilai komponen maupun pengukuran.</p>
            </CardHeader>
            <CardContent className="space-y-5">
                {klaim.daftar.length === 0 ? (
                    <p className="text-sm text-muted">Kegiatan ini belum diklaim ke rencana aksi mana pun.</p>
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {klaim.daftar.map((item) => (
                            <li key={item.id} className="flex flex-wrap items-start justify-between gap-3 p-3">
                                <div className="min-w-0 space-y-1">
                                    <p className="break-words text-sm font-semibold text-ink">{item.rencana_aksi ?? 'Rencana aksi'}</p>
                                    <p className="text-xs text-muted">{item.komponen ?? 'Tanpa komponen tertentu'}</p>
                                    {item.catatan && <p className="break-words text-xs text-muted">{item.catatan}</p>}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant={item.arah_dampak === 'menambah' ? 'success' : 'warning'}>{item.arah_dampak === 'menambah' ? 'Menambah' : 'Mengurangi'}</Badge>
                                    {item.can.hapus && (
                                        <Button type="button" size="sm" variant="outline" className="h-8 w-8 p-0 text-muted hover:text-danger"
                                            aria-label={`Hapus klaim ke ${item.rencana_aksi ?? 'rencana aksi'}`} onClick={() => setHapusTarget(item)}>
                                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {klaim.can.tambah ? (
                    <form onSubmit={kirim} className="space-y-4 border-t border-border pt-5" aria-busy={processing}>
                        {galatUmum && <p role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger-dark">{galatUmum}</p>}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Select id="klaim-rencana-aksi" label="Rencana aksi" required hoverScroll value={data.rencana_aksi_id}
                                    onChange={(e) => setData({ ...data, rencana_aksi_id: e.target.value, komponen_id: komponenAwal(klaim.opsiRencanaAksi.find((opsi) => opsi.id === e.target.value)) })}
                                    error={fieldErrors.rencana_aksi_id} options={klaim.opsiRencanaAksi.map((opsi) => ({ value: opsi.id, label: opsi.label }))} />
                            </div>
                            <Select id="klaim-komponen" label="Komponen (opsional)" value={data.komponen_id} onChange={(e) => setData('komponen_id', e.target.value)}
                                error={fieldErrors.komponen_id} options={opsiKomponen} />
                            <Select id="klaim-arah" label="Arah dampak" required value={data.arah_dampak} onChange={(e) => setData('arah_dampak', e.target.value as ArahDampak)}
                                error={fieldErrors.arah_dampak} options={ARAH_DAMPAK_OPTIONS} />
                            <div className="sm:col-span-2">
                                <Input id="klaim-catatan" label="Catatan (opsional)" maxLength={255} value={data.catatan} onChange={(e) => setData('catatan', e.target.value)} error={fieldErrors.catatan} />
                            </div>
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" isLoading={processing} disabled={processing || !data.rencana_aksi_id}>Tambah klaim</Button>
                        </div>
                    </form>
                ) : (
                    <p className="border-t border-border pt-5 text-sm text-muted">Belum ada rencana aksi yang dapat diklaim: rencana aksi unit dan tahun yang sama harus berstatus draf atau dikembalikan, dan periode kegiatan harus terbuka bagi Anda.</p>
                )}
            </CardContent>

            <AuditReasonModal
                open={hapusTarget !== null}
                title="Hapus klaim kegiatan"
                description={`Klaim ke "${hapusTarget?.rencana_aksi ?? 'rencana aksi'}" akan dihapus. Kegiatan tetap ada.`}
                reason={alasan}
                error={galatHapus}
                busy={menghapus}
                submitDisabled={alasan.trim().length < 3}
                confirmLabel="Hapus"
                destructive
                onReasonChange={setAlasan}
                onClose={tutupHapus}
                onConfirm={konfirmasiHapus}
            />
        </Card>
    );
}
