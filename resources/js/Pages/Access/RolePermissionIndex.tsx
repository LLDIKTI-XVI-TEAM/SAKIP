import { Head, router, usePage } from "@inertiajs/react";
import { KeyRound, SearchX } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { Badge } from "@/Components/Badge";
import { Card, CardHeader } from "@/Components/Card";
import { Input } from "@/Components/Input";
import { Pagination } from "@/Components/Pagination";
import { Select } from "@/Components/Select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import { AuthenticatedLayout } from "@/Layouts/AuthenticatedLayout";
import type { RolePermissionIndexProps } from "@/types/role-permission";

const cellClass = "min-w-0 p-0 md:px-4 md:py-3.5";

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
    const pending = useRef(false);

    const navigationEvents = useMemo(() => ({
        onStart: () => {
            pending.current = true;
            setNavigating(true);
        },
        onFinish: () => {
            pending.current = false;
            setNavigating(false);
        },
    }), []);
    const navigate = (next: Partial<typeof filters>) => {
        if (pending.current) return;
        router.get(
            "/akses/izin-peran",
            { ...filters, ...next },
            { preserveState: false, ...navigationEvents },
        );
    };

    useEffect(() => {
        // Mengikuti pencarian pengguna Grant: minimal 2 huruf dan debounce 350 ms; kotak kosong menampilkan semua izin.
        const q = query.trim();
        if (!filters.role || q === filters.q || q.length === 1) return;
        const timer = window.setTimeout(() => {
            // Kunjungan baru membatalkan yang sedang berjalan; pencarian yang dilewati diulang saat filters berubah.
            if (pending.current) return;
            router.get(
                "/akses/izin-peran",
                { ...filters, q },
                { preserveState: true, preserveScroll: true, replace: true, ...navigationEvents },
            );
        }, 350);
        return () => window.clearTimeout(timer);
    }, [query, filters, navigationEvents]);

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
                </Card>
                <p role="status" className="sr-only">
                    {navigating ? "Memuat izin…" : ""}
                </p>
                <Card>
                    <CardHeader className="px-4 py-4">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-base font-semibold tracking-tight text-ink">
                                    {selectedRole ? `Izin bawaan ${selectedRole.nama}` : "Izin bawaan peran"}
                                </h2>
                                {selectedRole && !selectedRole.aktif && (
                                    <Badge variant="muted">Peran nonaktif</Badge>
                                )}
                                {selectedRole && (
                                    <span className="text-xs text-muted">
                                        {filters.q ? `${pagination.total} izin cocok` : `${pagination.total} izin`}
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
                                    <TableRow className="block md:table-row">
                                        <TableCell colSpan={3} className="block py-12 text-center md:table-cell">
                                            <SearchX className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                                            <p className="mt-2 text-sm font-semibold text-ink">
                                                {filters.q
                                                    ? "Tidak ada izin yang cocok dengan pencarian."
                                                    : pagination.total === 0 ? "Peran ini belum memiliki izin bawaan." : "Tidak ada izin di halaman ini."}
                                            </p>
                                            {filters.q && (
                                                <p className="mt-1 text-xs text-muted">Coba kata kunci kode atau keterangan lain.</p>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                )}
                                {permissions.map((permission) => (
                                    <TableRow key={permission.id} className="grid min-w-0 grid-cols-2 gap-3 p-4 md:table-row">
                                        <TableCell className={`col-span-2 ${cellClass}`}>
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
                                        <TableCell className={cellClass}>
                                            <span className="mr-2 text-muted md:hidden">Scope</span>
                                            {permission.butuh_scope === "unit"
                                                ? <Badge variant="info">Unit</Badge>
                                                : <span className="text-ink">Global</span>}
                                        </TableCell>
                                        <TableCell className={cellClass}>
                                            <span className="mr-2 text-muted md:hidden">Status</span>
                                            <Badge variant={permission.aktif ? "success" : "muted"}>
                                                {permission.aktif ? "Aktif" : "Nonaktif"}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <div className="px-6 py-14 text-center">
                            <KeyRound className="mx-auto h-8 w-8 text-muted/60" aria-hidden="true" />
                            <p className="mt-2 text-sm font-semibold text-ink">Belum ada peran dipilih</p>
                            <p className="mt-1 text-xs text-muted">Pilih peran untuk melihat izin bawaannya.</p>
                        </div>
                    )}
                </Card>
                {selectedRole && (
                    <Pagination
                        links={pagination.links}
                        from={pagination.from}
                        to={pagination.to}
                        total={pagination.total}
                        ariaLabel="Halaman izin"
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
