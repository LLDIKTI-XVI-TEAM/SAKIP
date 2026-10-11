import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card, CardContent, CardHeader } from '@/Components/Card';
import { Input } from '@/Components/Input';
import { ActivationPanel } from '@/Components/Jadwal/ActivationPanel';
import { JendelaPeriode } from '@/Components/Jadwal/JendelaPeriode';
import { Select } from '@/Components/Select';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { statusJadwal } from '@/lib/jadwal';
import type { JadwalEditorProps, JadwalPeriode, JadwalPeriodeInput } from '@/types/periode-jadwal';

interface JadwalForm {
    renstra_id: string;
    tahun: string;
    rencana_aksi_mulai: string;
    rencana_aksi_selesai: string;
    penutupan: string;
    revisi?: number;
    periode: JadwalPeriode[];
}

const linkClass =
    'rounded-lg px-3 py-2 text-sm font-medium text-primary hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/30';
// Padding rapat hanya di HP agar tanggal muat di setengah lebar kartu.
const dateClass = 'min-w-0 px-2 text-xs sm:px-3.5 sm:text-sm';
const kapital = (text: string) => text.charAt(0).toUpperCase() + text.slice(1);

export default function Editor(props: JadwalEditorProps) {
    return <JadwalEditor key={props.jadwal?.id ?? 'create'} {...props} />;
}

function JadwalEditor({ jadwal, can, read_only_reason, renstra_options, periode_options }: JadwalEditorProps) {
    const form = useForm<JadwalForm>({
        renstra_id: jadwal?.renstra_id ?? '',
        tahun: jadwal ? String(jadwal.tahun) : '',
        rencana_aksi_mulai: jadwal?.rencana_aksi_mulai ?? '',
        rencana_aksi_selesai: jadwal?.rencana_aksi_selesai ?? '',
        penutupan: jadwal?.penutupan ?? '',
        ...(jadwal ? { revisi: jadwal.revisi } : {}),
        periode: jadwal?.periode ?? [],
    });
    // Renstra milik jadwal tetap tampil walau bukan pilihan lagi (kalender hanya dapat dibaca).
    const renstraOptions =
        jadwal && !renstra_options.some((option) => option.id === jadwal.renstra.id)
            ? [jadwal.renstra, ...renstra_options]
            : renstra_options;
    const renstra = renstraOptions.find((option) => option.id === form.data.renstra_id) ?? null;
    const pilihanPeriode = periode_options.filter(
        (option) => !form.data.periode.some((item) => item.periode_id === option.id),
    );
    const [failure, setFailure] = useState('');
    const recovery = useAuthRecovery();
    const submitting = useRef(false);
    const summaryRef = useRef<HTMLDivElement>(null);
    const errors: Record<string, string | undefined> = form.errors;
    const editable = !read_only_reason && (jadwal ? can.update : can.create);
    const blocked = Boolean(failure || recovery.recovery);
    const disabled = !editable || form.processing || blocked;
    const title = jadwal ? `Jadwal Tahunan ${jadwal.tahun}` : 'Susun Jadwal Tahunan';
    const authoritativeUrl = jadwal
        ? `/jadwal/${jadwal.id}`
        : `/jadwal?${new URLSearchParams({ renstra_id: form.data.renstra_id, tahun: form.data.tahun })}`;

    useEffect(() => {
        if (form.hasErrors) summaryRef.current?.focus();
    }, [form.hasErrors, form.errors]);

    const unknown = () =>
        setFailure('Hasil penyimpanan belum terkonfirmasi. Periksa kalender terbaru sebelum menyimpan kembali.');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (disabled || submitting.current) return;
        submitting.current = true;
        form.transform((data) => ({
            renstra_id: data.renstra_id,
            tahun: data.tahun,
            rencana_aksi_mulai: data.rencana_aksi_mulai,
            rencana_aksi_selesai: data.rencana_aksi_selesai,
            penutupan: data.penutupan,
            ...(jadwal ? { revisi: data.revisi } : {}),
            periode: data.periode.map(
                ({
                    periode_id,
                    periode_revisi,
                    pengisian_mulai,
                    pengisian_selesai,
                    reviu_mulai,
                    reviu_selesai,
                }): JadwalPeriodeInput => ({
                    periode_id,
                    periode_revisi,
                    pengisian_mulai,
                    pengisian_selesai,
                    reviu_mulai,
                    reviu_selesai,
                }),
            ),
        }));
        const path = jadwal ? `/jadwal/${jadwal.id}` : '/jadwal';
        form.submit(jadwal ? 'put' : 'post', path, {
            preserveScroll: true,
            // Hanya hasil commit terkonfirmasi yang mengganti seluruh baseline beserta revisinya.
            preserveState: (page) => !page.flash.success,
            onSuccess: (page) => {
                if (!page.flash.success)
                    setFailure(page.flash.error || 'Hasil penyimpanan belum terkonfirmasi. Periksa kalender terbaru.');
            },
            onHttpException: (response) => {
                // Status akhir dapat berasal dari GET redirect setelah mutasi sudah commit.
                if (
                    !recovery.handleHttpException(response, {
                        effectiveMethod: jadwal ? 'put' : 'post',
                        path,
                        mutation: true,
                    })
                ) {
                    setFailure(
                        response.status === 403
                            ? 'Hasil penyimpanan belum terkonfirmasi karena akses ditolak. Periksa izin dan kalender terbaru sebelum mencoba kembali.'
                            : 'Hasil penyimpanan belum terkonfirmasi. Periksa kalender terbaru.',
                    );
                }
                return false;
            },
            onNetworkError: () => {
                unknown();
                return false;
            },
            onCancel: unknown,
            onFinish: () => {
                submitting.current = false;
            },
        });
    };

    const addPeriode = (periodeId: string) => {
        const option = periode_options.find((item) => item.id === periodeId);
        if (!option) return;
        form.clearErrors();
        form.setData((data) => ({
            ...data,
            periode: [
                ...data.periode,
                {
                    periode_id: option.id,
                    periode_revisi: option.revisi,
                    nama: option.nama,
                    urutan: option.urutan,
                    aktif: option.aktif,
                    is_nilai_akhir: option.is_nilai_akhir,
                    pengisian_mulai: '',
                    pengisian_selesai: '',
                    reviu_mulai: '',
                    reviu_selesai: '',
                },
            ].sort((a, b) => a.urutan - b.urutan || a.periode_id.localeCompare(b.periode_id)),
        }));
    };
    const removePeriode = (id: string) => {
        const index = form.data.periode.findIndex((item) => item.periode_id === id);
        const next = form.data.periode[index + 1] ?? form.data.periode[index - 1];
        form.clearErrors();
        form.setData((data) => ({ ...data, periode: data.periode.filter((item) => item.periode_id !== id) }));
        document.getElementById(next ? `periode-${next.periode_id}-pengisian_mulai` : 'tambah-periode')?.focus();
    };

    return (
        <AuthenticatedLayout
            title={title}
            breadcrumbs={[
                { label: 'Jadwal Tahunan', href: '/jadwal' },
                { label: jadwal ? `Tahun ${jadwal.tahun}` : 'Susun jadwal' },
            ]}
        >
            <Head title={title} />
            <div className="mb-5">
                <Link href="/jadwal" className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                    <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                    Kembali ke daftar
                </Link>
            </div>
            <AuthRecoveryNotice recovery={recovery.recovery} pending={form.processing} />
            {failure && !recovery.recovery && (
                <div
                    role="alert"
                    className="mb-5 space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm"
                >
                    <p>{failure}</p>
                    <p>Salin input yang ingin dipertahankan sebelum memuat data terbaru.</p>
                    <Link href={authoritativeUrl} className={linkClass}>
                        Periksa kalender terbaru
                    </Link>
                </div>
            )}
            {read_only_reason && (
                <div className="mb-5 rounded-lg border border-border bg-soft p-4 text-sm">
                    <p className="font-semibold">Kalender hanya dapat dibaca</p>
                    <p className="mt-1">{read_only_reason}</p>
                </div>
            )}
            {jadwal?.status === 'draft' && form.data.periode.some((item) => !item.aktif) && (
                <div className="mb-5 rounded-lg border border-warning/30 bg-warning/10 p-4 text-sm">
                    <p className="font-semibold">Ada periode nonaktif pada draft</p>
                    <p className="mt-1">
                        Referensi tetap ditampilkan. Ganti atau hapus periode nonaktif sebelum aktivasi; kewajiban dan
                        riwayat jadwal aktif/ditutup tetap dipertahankan.
                    </p>
                </div>
            )}
            <form onSubmit={submit} noValidate className="space-y-5">
                {form.hasErrors && (
                    <div
                        ref={summaryRef}
                        tabIndex={-1}
                        role="alert"
                        className="rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm focus:outline-none focus:ring-2 focus:ring-danger/30"
                    >
                        <p className="font-semibold">Periksa data kalender</p>
                        <ul className="mt-2 list-inside list-disc space-y-1">
                            {Object.entries(errors).map(([field, message]) => (
                                <li key={field}>{message}</li>
                            ))}
                        </ul>
                        <Link href={authoritativeUrl} className="mt-2 inline-block text-primary underline">
                            Periksa jadwal yang sudah tersimpan
                        </Link>
                    </div>
                )}
                <Card>
                    <CardHeader>
                        <h2 className="font-semibold">Informasi tahunan</h2>
                        {jadwal && (
                            <Badge variant={statusJadwal[jadwal.status].variant}>
                                {statusJadwal[jadwal.status].label}
                            </Badge>
                        )}
                    </CardHeader>
                    <CardContent className="p-4 sm:p-6">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,1fr)]">
                            <div className="col-span-2 min-w-0 lg:col-span-1">
                                <Select
                                    id="pilih-renstra"
                                    label="Renstra"
                                    required
                                    value={form.data.renstra_id}
                                    error={errors.renstra_id}
                                    helperText={
                                        renstra ? `Rentang Renstra ${renstra.tahun_mulai}–${renstra.tahun_selesai}` : undefined
                                    }
                                    disabled={disabled}
                                    onChange={(event) => form.setData('renstra_id', event.target.value)}
                                >
                                    <option value="" disabled>
                                        Pilih Renstra
                                    </option>
                                    {renstraOptions.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.nama} · {kapital(option.status)}
                                        </option>
                                    ))}
                                </Select>
                                {!jadwal && renstra && form.data.tahun && (
                                    <Link
                                        href={authoritativeUrl}
                                        className="mt-2 inline-block rounded text-xs text-primary underline focus:ring-2 focus:ring-primary/30"
                                    >
                                        Periksa jadwal Renstra dan tahun ini
                                    </Link>
                                )}
                            </div>
                            <Input
                                name="tahun"
                                label="Tahun"
                                type="number"
                                required
                                autoComplete="off"
                                placeholder="Contoh: 2026"
                                value={form.data.tahun}
                                onChange={(event) => form.setData('tahun', event.target.value)}
                                error={errors.tahun}
                                disabled={disabled}
                            />
                        </div>
                        {/* Urut waktu: Rencana Aksi lebih dulu, penutupan paling akhir. */}
                        <div className="mt-5 grid grid-cols-2 items-end gap-4 border-t border-border pt-5 lg:grid-cols-3">
                            <Input
                                name="rencana_aksi_mulai"
                                label="Rencana Aksi mulai"
                                type="date"
                                required
                                className={dateClass}
                                value={form.data.rencana_aksi_mulai}
                                onChange={(event) => form.setData('rencana_aksi_mulai', event.target.value)}
                                error={errors.rencana_aksi_mulai}
                                disabled={disabled}
                            />
                            <Input
                                name="rencana_aksi_selesai"
                                label="Rencana Aksi selesai"
                                type="date"
                                required
                                className={dateClass}
                                value={form.data.rencana_aksi_selesai}
                                onChange={(event) => form.setData('rencana_aksi_selesai', event.target.value)}
                                error={errors.rencana_aksi_selesai}
                                disabled={disabled}
                            />
                            <Input
                                name="penutupan"
                                label="Penutupan"
                                type="date"
                                required
                                className={dateClass}
                                value={form.data.penutupan}
                                onChange={(event) => form.setData('penutupan', event.target.value)}
                                error={errors.penutupan}
                                disabled={disabled}
                            />
                        </div>
                        <p className="mt-3 text-xs leading-relaxed text-muted">
                            Rencana Aksi selesai sebelum pengisian pertama; penutupan setelah seluruh jendela.
                        </p>
                    </CardContent>
                </Card>
                <JendelaPeriode
                    periode={form.data.periode}
                    errors={errors}
                    editable={editable}
                    disabled={disabled}
                    options={pilihanPeriode}
                    onAdd={addPeriode}
                    onRemove={removePeriode}
                    onChange={(periodeId, field, value) =>
                        form.setData((data) => ({
                            ...data,
                            periode: data.periode.map((period) =>
                                period.periode_id === periodeId ? { ...period, [field]: value } : period,
                            ),
                        }))
                    }
                />
                {editable && (
                    <div className="flex items-center justify-end gap-3">
                        <Link
                            href="/jadwal"
                            className="inline-flex h-10 items-center justify-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-ink transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            Batal
                        </Link>
                        <Button type="submit" disabled={disabled} isLoading={form.processing}>
                            <Check className="h-4 w-4" aria-hidden="true" />
                            Simpan draft
                        </Button>
                    </div>
                )}
            </form>
            {/* Aktivasi langkah terakhir: setelah kalender diisi dan disimpan. */}
            {jadwal && can.activate && (
                <div className="mt-5">
                    <ActivationPanel
                        jadwalId={jadwal.id}
                        tahun={jadwal.tahun}
                        // Revisi kalender yang benar-benar tampil; props bisa lebih baru saat state dipertahankan sesudah POST gagal.
                        revisi={form.data.revisi ?? jadwal.revisi}
                        status={jadwal.status}
                        dirtyCalendar={form.isDirty}
                        periodeNames={Object.fromEntries(jadwal.periode.map((item) => [item.periode_id, item.nama]))}
                    />
                </div>
            )}
        </AuthenticatedLayout>
    );
}
