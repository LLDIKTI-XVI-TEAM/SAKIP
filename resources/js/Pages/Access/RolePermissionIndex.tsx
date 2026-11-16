import { Head, router, usePage } from "@inertiajs/react";
import { Eye, KeyRound, SearchX } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { Badge } from "@/Components/Badge";
import { Button } from "@/Components/Button";
import { Card, CardHeader } from "@/Components/Card";
import { Input } from "@/Components/Input";
import { Pagination } from "@/Components/Pagination";
import { Select } from "@/Components/Select";
import {
    Table,
    TableActionLink,
    TableBody,
    TableCell,
    TableCellLabel,
    TableEmptyRow,
    TableHead,
    TableHeader,
    TableRow,
    tableCellResponsive,
} from "@/Components/Table";
import { AuthenticatedLayout } from "@/Layouts/AuthenticatedLayout";
import { classifyRecovery } from "@/lib/authRecovery";
import type { RolePermissionIndexProps } from "@/types/role-permission";

const failureText = {
    network: "Hasil pencarian belum diperbarui. Periksa koneksi, lalu coba lagi.",
    denied: "Akses ke daftar izin ditolak. Periksa akses sebelum mencoba kembali.",
    other: "Hasil pencarian belum diperbarui.",
};

export default function RolePermissionIndex({
    roles,
    selectedRole,
    permissions,
    pagination,
    filters,
}: RolePermissionIndexProps) {
    const { errors } = usePage().props;
    const [query, setQuery] = useState(filters.q);
    const [navigating, setNavigating] = useState(false);
    const [retry, setRetry] = useState(0);
    const [failure, setFailure] = useState<{ query: string; kind: keyof typeof failureText } | null>(null);
    const pending = useRef(false);
    const leavingPage = useRef(false);
    const skipped = useRef(false);

    const navigationEvents = useMemo(() => ({
        onStart: () => {
            pending.current = true;
            setNavigating(true);
        },
        onFinish: (visit: { cancelled: boolean; interrupted: boolean }) => {
            pending.current = false;
            setNavigating(false);
            // Kunjungan gagal tetap memanggil onFinish: kirim ulang pencarian tertunda sekali, kecuali kunjungan dibatalkan (mis. pindah halaman).
            if (skipped.current) {
                skipped.current = false;
                if (!visit.cancelled && !visit.interrupted) setRetry((count) => count + 1);
            }
        },
    }), []);

    useEffect(() => {
        // Navigasi ke halaman lain (sidebar, breadcrumb) tidak lewat callback di atas; hanya pencarian otomatis yang ditahan agar tidak membatalkannya.
        const leaving = (visit: { url: URL; prefetch: boolean }) => !visit.prefetch && visit.url.pathname !== "/akses/izin-peran";
        const offStart = router.on("start", (event) => {
            if (leaving(event.detail.visit)) leavingPage.current = true;
        });
        const offFinish = router.on("finish", (event) => {
            if (!leaving(event.detail.visit)) return;
            leavingPage.current = false;
            navigationEvents.onFinish(event.detail.visit);
        });
        return () => {
            offStart();
            offFinish();
        };
    }, [navigationEvents]);

    const navigate = (next: Partial<typeof filters>) => {
        if (pending.current) return;
        router.get(
            "/akses/izin-peran",
            { ...filters, ...next },
            { preserveState: false, ...navigationEvents },
        );
    };

    // Mengikuti pencarian pengguna Grant: minimal 2 huruf dan debounce 350 ms; kurang dari 2 huruf menampilkan semua izin.
    const trimmed = query.trim();
    const searchQuery = trimmed.length < 2 ? "" : trimmed;
    // Paginasi hasil lama tidak berlaku selama kunjungan atau kata cari baru belum dimuat; tautannya akan dipotong pencarian otomatis.
    const stale = navigating || searchQuery !== filters.q;
    const countText = filters.q ? `${pagination.total} izin cocok` : `${pagination.total} izin`;

    useEffect(() => {
        if (!filters.role || searchQuery === filters.q) return;
        const timer = window.setTimeout(() => {
            // Kunjungan baru membatalkan yang sedang berjalan; pencarian ditunda sampai kunjungan itu selesai.
            if (pending.current || leavingPage.current) {
                skipped.current = true;
                return;
            }
            let loaded = false;
            let kind: keyof typeof failureText = "other";
            let sessionDialog = false;
            setFailure(null);
            router.get(
                "/akses/izin-peran",
                { ...filters, q: searchQuery },
                {
                    preserveState: true, preserveScroll: true, replace: true, ...navigationEvents,
                    onSuccess: () => { loaded = true; },
                    onNetworkError: () => {
                        kind = "network";
                        return false;
                    },
                    // Sesi kedaluwarsa diteruskan ke dialog sesi global; kegagalan lain cukup ditampilkan di halaman tanpa dialog galat bawaan.
                    onHttpException: (response) => {
                        sessionDialog = classifyRecovery(response) !== null;
                        if (sessionDialog) return;
                        if (response.status === 403) kind = "denied";
                        return false;
                    },
                    // onFinish juga terpanggil saat koneksi/server gagal; hanya onSuccess yang membuktikan hasil sudah diperbarui.
                    onFinish: (visit) => {
                        const queued = skipped.current;
                        navigationEvents.onFinish(visit);
                        // Dipotong kunjungan lain: kirim ulang sekali bila kunjungan itu gagal dan halaman tetap terbuka.
                        if (visit.interrupted) skipped.current = true;
                        else if (!loaded && !queued && !visit.cancelled && !sessionDialog) setFailure({ query: searchQuery, kind });
                    },
                },
            );
        }, 350);
        return () => window.clearTimeout(timer);
    }, [searchQuery, filters, navigationEvents, retry]);

    return (
        <AuthenticatedLayout title="Peran & Izin" breadcrumbs={[{ label: "Peran & Izin" }]}>
            <Head title="Peran & Izin" />
            <div aria-busy={navigating} className="space-y-5">
                <Card className="overflow-visible">
                    <form
                        role="search"
                        onSubmit={(event) => event.preventDefault()}
                        className="grid gap-3 p-4 md:grid-cols-[16rem_minmax(0,1fr)] md:items-start"
                    >
                        <Select
                            id="role-permission-role"
                            name="role"
                            label="Peran"
                            value={filters.role ?? ""}
                            disabled={navigating}
                            error={errors?.role}
                            aria-invalid={Boolean(errors?.role)}
                            onChange={(event) =>
                                navigate({ role: event.target.value || null, q: "" })
                            }
                        >
                            <option value="">Pilih peran</option>
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.nama}
                                    {role.aktif ? "" : " (nonaktif)"}
                                </option>
                            ))}
                        </Select>
                        {selectedRole && (
                            <Input
                                id="role-permission-search"
                                name="q"
                                type="search"
                                label="Cari kode atau keterangan izin"
                                placeholder="Ketik kode atau keterangan izin…"
                                helperText="Hasil diperbarui otomatis setelah mengetik minimal 2 huruf."
                                value={query}
                                maxLength={100}
                                autoComplete="off"
                                spellCheck={false}
                                error={errors?.q}
                                aria-invalid={Boolean(errors?.q)}
                                onChange={(event) => setQuery(event.target.value)}
                            />
                        )}
                    </form>
                    {failure && failure.query === searchQuery && searchQuery !== filters.q && (
                        <p role="alert" className="px-4 pb-4 text-xs text-danger">
                            {failureText[failure.kind]}{" "}
                            <button
                                type="button"
                                onClick={() => {
                                    // Tombol ini hilang setelah diklik; fokus dipindah agar keyboard tidak jatuh ke awal halaman.
                                    document.getElementById("role-permission-search")?.focus();
                                    setFailure(null);
                                    setRetry((count) => count + 1);
                                }}
                                className="font-semibold text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/25"
                            >
                                Coba lagi
                            </button>
                        </p>
                    )}
                </Card>
                <p role="status" className="sr-only">
                    {navigating ? "Memuat izin…" : filters.q ? countText : ""}
                </p>
                <Card>
                    <CardHeader className="px-4 py-4">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-base font-semibold tracking-tight text-ink">
                                    {selectedRole ? `Izin bawaan ${selectedRole.nama}` : "Daftar peran"}
                                </h2>
                                {selectedRole && !selectedRole.aktif && (
                                    <Badge variant="muted">Peran nonaktif</Badge>
                                )}
                                {selectedRole && (
                                    <span className="text-xs text-muted">
                                        {countText}
                                    </span>
                                )}
                            </div>
                            <p className="mt-0.5 max-w-3xl text-xs text-muted">
                                Lihat izin bawaan setiap peran. Hak akses pengguna juga
                                dipengaruhi oleh grant, pembatasan izin, dan aturan pekerjaan.
                            </p>
                        </div>
                    </CardHeader>
                    {selectedRole ? (
                        /* Grid mobile: setiap izin tampil sebagai kartu ringkas tanpa scroll horizontal. */
                        <Table wrapperClassName="overflow-x-visible" className="block md:table">
                            <caption className="sr-only">
                                Izin tersimpan untuk {selectedRole.nama}
                            </caption>
                            <TableHeader className="max-md:sr-only">
                                <TableRow>
                                    <TableHead className="whitespace-normal">Izin</TableHead>
                                    <TableHead>Scope</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {permissions.length === 0 && (
                                    // Halaman kosong padahal total > 0 berarti nomor halaman melampaui halaman terakhir, mis. bookmark lama.
                                    <TableEmptyRow
                                        colSpan={3}
                                        icon={SearchX}
                                        title={pagination.total > 0
                                            ? "Tidak ada izin di halaman ini."
                                            : filters.q ? "Tidak ada izin yang cocok dengan pencarian." : "Peran ini belum memiliki izin bawaan."}
                                        description={pagination.total === 0 && filters.q ? "Coba kata kunci kode atau keterangan lain." : undefined}
                                        action={pagination.total > 0 && (
                                            <Button type="button" variant="outline" size="sm" disabled={stale} onClick={() => navigate({})}>
                                                Ke halaman pertama
                                            </Button>
                                        )}
                                    />
                                )}
                                {permissions.map((permission) => (
                                    <TableRow key={permission.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                        <TableCell className={`col-span-2 ${tableCellResponsive}`}>
                                            <p className="break-all font-mono text-xs font-semibold text-ink">
                                                {permission.kode}
                                            </p>
                                            <p className="mt-1 max-w-xl break-words text-xs text-muted">
                                                {permission.keterangan || "—"}
                                            </p>
                                            {!permission.in_catalog && (
                                                <div className="mt-2">
                                                    <Badge variant="warning">Di luar katalog rilis</Badge>
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className={tableCellResponsive}>
                                            <TableCellLabel>Scope</TableCellLabel>
                                            {permission.butuh_scope === "unit"
                                                ? <Badge variant="info">Unit</Badge>
                                                : <span className="text-ink">Global</span>}
                                        </TableCell>
                                        <TableCell className={tableCellResponsive}>
                                            <TableCellLabel>Status</TableCellLabel>
                                            <Badge variant={permission.aktif ? "success" : "muted"}>
                                                {permission.aktif ? "Aktif" : "Nonaktif"}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Table wrapperClassName="overflow-x-visible" className="block md:table">
                            <caption className="sr-only">Daftar peran resmi beserta jumlah izin bawaan</caption>
                            <TableHeader className="max-md:sr-only">
                                <TableRow>
                                    <TableHead>Peran</TableHead>
                                    <TableHead>Jumlah izin</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody className="block md:table-row-group">
                                {roles.length === 0 && <TableEmptyRow colSpan={4} icon={KeyRound} title="Peran resmi belum tersedia." />}
                                {roles.map((role) => (
                                    <TableRow key={role.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                        <TableCell className={`col-span-2 ${tableCellResponsive}`}>
                                            <p className="break-words text-sm font-semibold text-ink">{role.nama}</p>
                                        </TableCell>
                                        <TableCell className={tableCellResponsive}>
                                            <TableCellLabel>Jumlah izin</TableCellLabel>
                                            {role.jumlah_izin}
                                        </TableCell>
                                        <TableCell className={tableCellResponsive}>
                                            <TableCellLabel>Status</TableCellLabel>
                                            <Badge variant={role.aktif ? "success" : "muted"}>
                                                {role.aktif ? "Aktif" : "Nonaktif"}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className={`col-span-2 text-right ${tableCellResponsive}`}>
                                            {/* Memakai callback yang sama dengan dropdown agar pilihan peran terkunci selama memuat. */}
                                            <TableActionLink
                                                href={`/akses/izin-peran?role=${role.id}`}
                                                label={`Lihat izin ${role.nama}`}
                                                tooltip="Lihat izin"
                                                icon={Eye}
                                                onStart={navigationEvents.onStart}
                                                onFinish={navigationEvents.onFinish}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Card>
                {selectedRole && (
                    // Tautan halaman hasil lama tidak boleh membatalkan kunjungan atau dipotong pencarian otomatis.
                    <div inert={stale} className={`transition-opacity ${stale ? "opacity-60" : ""}`}>
                        <Pagination
                            links={pagination.links}
                            from={pagination.from}
                            to={pagination.to}
                            total={pagination.total}
                            ariaLabel="Halaman izin"
                        />
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
