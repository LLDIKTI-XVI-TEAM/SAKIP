import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/Table';
import { Textarea } from '@/Components/Textarea';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import { useFormatNilai } from '@/Pages/Pengukuran/formatNilai';
import type { IndikatorKinerjaItem, RenstraOption, RevisiTargetRenstra, SasaranStrategisItem } from '@/types/sasaran-indikator';

const KONFLIK = 'Draf revisi sudah berubah oleh pengguna lain atau tidak lagi terbuka. Muat ulang untuk melanjutkan.';
const JEDA_AUTOSAVE = 800;

type Isian = Record<string, Record<string, string>>;

/**
 * Mode revisi seluruh tabel (Q71 §71.2): isian dan justifikasi tersimpan otomatis sebagai draf di server; target yang
 * berlaku baru berubah saat Simpan revisi. Versi draf dari props dipakai sebagai token, sehingga isian basi ditolak 409.
 */
export function ModeRevisiTargetRenstra({ renstra, sasarans, revisi, onMuatUlang }: {
    renstra: RenstraOption;
    sasarans: SasaranStrategisItem[];
    revisi: RevisiTargetRenstra;
    /** Memuat draf dan nilai berlaku terbaru lalu memasang ulang komponen agar isian lokal yang basi dibuang. */
    onMuatUlang: () => void;
}) {
    const formatTanggal = useFormatTanggal();
    const formatNilai = useFormatNilai();
    const url =`/perencanaan/renstra/${renstra.id}/revisi-target`;
    const draf = useForm<{ nilai: Isian; justifikasi: string }>({ nilai: {}, justifikasi: revisi.justifikasi ?? '' });
    const simpanForm = useForm({});
    const batalForm = useForm({});
    const [kotor, setKotor] = useState(false);
    const [konflik, setKonflik] = useState(false);
    // Draf yang baru dibuka belum berisi apa pun; waktu pembuatannya bukan bukti isian tersimpan.
    const [tersimpanPada, setTersimpanPada] = useState(Object.keys(revisi.nilai).length > 0 || revisi.justifikasi ? revisi.diubah_pada : null);
    const [konfirmasiBatal, setKonfirmasiBatal] = useState(false);
    const galat: Record<string, string | undefined> = { ...draf.errors, ...simpanForm.errors, ...batalForm.errors };
    const years = Array.from({ length: renstra.tahun_selesai - renstra.tahun_mulai + 1 }, (_, i) => renstra.tahun_mulai + i);
    const sibuk = draf.processing || simpanForm.processing || batalForm.processing;
    const tanganiGalat = (errors: Record<string, string>) => { if (errors.versi) setKonflik(true); };

    // Sel draf bernilai null berarti dikosongkan; sel tanpa entri draf memakai nilai berlaku.
    const nilaiAwal = (ind: IndikatorKinerjaItem, tahun: number): string => {
        const sel = revisi.nilai[ind.id];
        if (sel && String(tahun) in sel) return sel[tahun] ?? '';
        return ind.target_renstra?.find((t) => t.tahun === tahun)?.nilai ?? '';
    };
    const ubah = (indikatorId: string, tahun: number, value: string) => {
        draf.setData('nilai', { ...draf.data.nilai, [indikatorId]: { ...draf.data.nilai[indikatorId], [tahun]: value } });
        setKotor(true);
    };

    useEffect(() => {
        if (!kotor || konflik || draf.processing) return;
        const timer = setTimeout(() => {
            setKotor(false);
            // Sel yang pernah disentuh dikirim ulang setiap kali; server menyimpannya idempoten.
            draf.transform((data) => ({
                revisi_id: revisi.id,
                versi: revisi.versi,
                nilai: Object.fromEntries(Object.entries(data.nilai).map(([id, tahunan]) => [id,
                    Object.fromEntries(Object.entries(tahunan).map(([tahun, nilai]) => [tahun, nilai === '' ? null : nilai]))])),
                justifikasi: data.justifikasi === '' ? null : data.justifikasi,
            }));
            draf.put(url, { preserveScroll: true, preserveState: true, only: ['revisi_target'], onError: tanganiGalat, onSuccess: (page) => {
                // Reload membaca draf terbaru: versi selain hasil PUT ini berarti ada perubahan lain yang belum terlihat di isian lokal.
                const terbaru = (page.props as { revisi_target?: RevisiTargetRenstra | null }).revisi_target;
                if (terbaru?.id !== revisi.id || terbaru.versi !== revisi.versi + 1) {
                    setKonflik(true);
                    return;
                }
                setTersimpanPada(new Date().toISOString());
            } });
        }, JEDA_AUTOSAVE);
        return () => clearTimeout(timer);
    }, [kotor, konflik, draf, url, revisi.id, revisi.versi]);

    const simpan = () => {
        simpanForm.transform(() => ({ revisi_id: revisi.id, versi: revisi.versi, justifikasi: draf.data.justifikasi }));
        // preserveState: tab Target Renstra pada halaman induk tetap terbuka setelah draf selesai.
        simpanForm.post(`${url}/simpan`, { preserveScroll: true, preserveState: true, onError: tanganiGalat });
    };
    const batal = () => {
        batalForm.transform(() => ({ revisi_id: revisi.id, versi: revisi.versi }));
        batalForm.delete(url, { preserveScroll: true, preserveState: true, onError: (errors) => { setKonfirmasiBatal(false); tanganiGalat(errors); } });
    };

    const status = draf.processing ? 'Menyimpan draf…'
        : konflik ? 'Perubahan terakhir belum tersimpan.'
            : kotor ? 'Ada perubahan yang belum tersimpan.'
                : Object.keys(draf.errors).length > 0 ? 'Draf belum tersimpan. Periksa isian yang ditandai.'
                    : tersimpanPada ? `Draf tersimpan otomatis ${formatTanggal(tersimpanPada, { withTime: true })}.` : 'Draf belum memiliki isian.';

    return <div className="space-y-6">
        <Card className="space-y-2 p-4 md:p-5">
            <div className="flex flex-wrap items-center gap-2">
                <h2 className="text-base font-semibold text-ink">Mode revisi target Renstra</h2>
                <Badge variant="warning">Draf</Badge>
            </div>
            <p className="text-sm text-muted">
                Draf dibuka oleh {revisi.dibuat_oleh ?? '—'}. Isian tersimpan otomatis; target Renstra yang berlaku baru berubah setelah Simpan revisi.
            </p>
            <p role="status" aria-live="polite" className="text-xs text-muted">{status}</p>
            {konflik && <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger">
                <span>{KONFLIK}</span>
                <Button type="button" size="sm" variant="outline" onClick={onMuatUlang}>Muat ulang</Button>
            </div>}
            {(galat.nilai || galat.renstra) && !konflik && <p role="alert" className="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger">{galat.nilai ?? galat.renstra}</p>}
        </Card>

        {sasarans.map((sasaran) => <Card key={sasaran.id}>
            <div className="border-b border-border p-4 md:p-5">
                <Badge variant="primary" className="font-mono">{sasaran.kode}</Badge>
                <h2 className="mt-1 text-base font-semibold leading-relaxed text-ink">{sasaran.deskripsi}</h2>
            </div>
            {/* Di HP setiap indikator menjadi kartu dengan label tahun per sel, tanpa scroll horizontal. */}
            <Table wrapperClassName="overflow-x-visible" className="block md:table">
                <caption className="sr-only">Isian revisi target Renstra per tahun untuk sasaran {sasaran.kode}</caption>
                <TableHeader className="hidden md:table-header-group">
                    <TableRow>
                        <TableHead className="w-28">Kode</TableHead>
                        <TableHead>Indikator</TableHead>
                        {years.map((tahun) => <TableHead key={tahun} className="text-right">{tahun}</TableHead>)}
                    </TableRow>
                </TableHeader>
                <TableBody className="block md:table-row-group">
                    {sasaran.indikator_kinerjas.map((ind) => <TableRow key={ind.id} className="grid grid-cols-2 gap-3 p-4 md:table-row md:align-top">
                        <TableHead scope="row" className="h-auto p-0 font-mono text-sm normal-case tracking-normal md:px-4 md:py-3.5">{ind.kode}</TableHead>
                        <TableCell className="p-0 md:px-4 md:py-3.5">
                            <span className="text-ink">{ind.nama}</span>
                            {ind.status !== 'aktif' && <span className="mt-1 block"><Badge variant="muted">Arsip, hanya dibaca</Badge></span>}
                            {galat[`nilai.${ind.id}`] && <p role="alert" className="mt-1 text-xs font-medium text-danger">{galat[`nilai.${ind.id}`]}</p>}
                        </TableCell>
                        {years.map((tahun) => <TableCell key={tahun} className="min-w-0 p-0 tabular-nums md:px-4 md:py-3.5 md:text-right">
                            <span className="mb-1 block text-xs text-muted md:hidden">{tahun}</span>
                            {tahun < ind.tahun_mulai_berlaku ? <Badge variant="muted">Belum berlaku</Badge>
                                : ind.status !== 'aktif' ? <span>{nilaiAwal(ind, tahun) === '' ? '—' : formatNilai(nilaiAwal(ind, tahun), ind.desimal_tampilan)}</span>
                                    : <Input
                                        label={`Target ${tahun} ${ind.kode}`}
                                        labelClassName="sr-only"
                                        inputMode="decimal"
                                        autoComplete="off"
                                        className="md:min-w-24 md:text-right"
                                        value={draf.data.nilai[ind.id]?.[tahun] ?? nilaiAwal(ind, tahun)}
                                        readOnly={konflik || simpanForm.processing || batalForm.processing}
                                        onChange={(event) => ubah(ind.id, tahun, event.target.value)}
                                        error={galat[`nilai.${ind.id}.${tahun}`]}
                                    />}
                        </TableCell>)}
                    </TableRow>)}
                </TableBody>
            </Table>
        </Card>)}

        <Card className="space-y-4 p-4 md:p-5">
            <Textarea
                label="Justifikasi revisi"
                required
                rows={4}
                maxLength={1000}
                helperText="Satu justifikasi untuk seluruh perubahan pada revisi ini."
                value={draf.data.justifikasi}
                readOnly={konflik || simpanForm.processing || batalForm.processing}
                onChange={(event) => { draf.setData('justifikasi', event.target.value); setKotor(true); }}
                error={galat.justifikasi}
            />
            <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => setKonfirmasiBatal(true)} disabled={sibuk || konflik}>Batal revisi</Button>
                {/* Simpan memakai isian di server, jadi ditahan selama isian terakhir belum tersimpan atau ditolak. */}
                <Button type="button" onClick={simpan} disabled={kotor || konflik || draf.hasErrors || draf.processing || batalForm.processing} isLoading={simpanForm.processing}>Simpan revisi</Button>
            </div>
        </Card>

        <Modal isOpen={konfirmasiBatal} onClose={() => setKonfirmasiBatal(false)} title="Batalkan revisi?" size="sm"
            footer={<div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => setKonfirmasiBatal(false)} disabled={batalForm.processing}>Kembali</Button>
                <Button type="button" variant="danger" onClick={batal} isLoading={batalForm.processing}>Ya, batalkan revisi</Button>
            </div>}>
            <p className="text-sm text-ink">Seluruh isian draf dibuang dan target Renstra tidak berubah.</p>
        </Modal>
    </div>;
}
