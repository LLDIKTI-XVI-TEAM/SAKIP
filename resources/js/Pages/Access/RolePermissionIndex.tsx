import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Badge } from "@/Components/Badge";
import { Button } from "@/Components/Button";
import { Input } from "@/Components/Input";
import { Select } from "@/Components/Select";
import { AuthenticatedLayout } from "@/Layouts/AuthenticatedLayout";
import { secondaryButton } from "@/Pages/Auth/AuthShell";
import { KeyRound, Search, ShieldCheck } from "lucide-react";
import type { RolePermissionIndexProps } from "@/types/role-permission";

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

    useEffect(() => {
        setQuery(filters.q);
    }, [filters.q]);

    const navigationEvents = {
        onStart: () => {
            pending.current = true;
            setNavigating(true);
        },
        onFinish: () => {
            pending.current = false;
            setNavigating(false);
        },
    };
    const navigate = (next: Partial<typeof filters>) => {
        if (pending.current) return;
        router.get(
            "/akses/izin-peran",
            { ...filters, ...next },
            { preserveState: false, ...navigationEvents },
        );
    };

    return (
        <AuthenticatedLayout title="Peran & Izin">
            <Head title="Peran & Izin" />
            <div className="space-y-6">
                <section
                    aria-busy={navigating}
                    className="rounded-xl border border-border bg-surface p-5 sm:p-6 shadow-xs"
                >
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 className="text-base font-semibold text-ink">Peran & Izin</h2>
                            <p className="mt-1 max-w-3xl text-xs text-muted leading-relaxed">
                                Lihat izin bawaan setiap peran. Hak akses pengguna juga
                                dipengaruhi oleh grant, pembatasan izin, dan aturan pekerjaan.
                            </p>
                        </div>
                    </div>

                    <div className="mt-5 max-w-md">
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
                    </div>

                    {selectedRole ? (
                        <div className="mt-6 border-t border-border pt-6">
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/80 bg-soft/50 p-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-semibold text-ink text-base">{selectedRole.nama}</h3>
                                            <Badge variant={selectedRole.aktif ? "primary" : "muted"} size="sm" dot>
                                                {selectedRole.aktif ? "Peran aktif" : "Peran nonaktif"}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted mt-0.5">Kode peran: <span className="font-mono">{selectedRole.kode}</span></p>
                                    </div>
                                </div>
                            </div>

                            <form
                                role="search"
                                className="my-5 flex flex-wrap items-end gap-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    navigate({ q: query });
                                }}
                            >
                                <div className="min-w-0 flex-1">
                                    <Input
                                        id="role-permission-search"
                                        name="q"
                                        type="search"
                                        label="Cari kode atau keterangan izin"
                                        value={query}
                                        maxLength={100}
                                        autoComplete="off"
                                        spellCheck={false}
                                        disabled={navigating}
                                        error={errors?.q}
                                        aria-invalid={Boolean(errors?.q)}
                                        onChange={(event) =>
                                            setQuery(event.target.value)
                                        }
                                        placeholder="Ketik kode izin (mis: pengukuran:read)..."
                                    />
                                </div>
                                <Button type="submit" disabled={navigating}>
                                    {navigating ? "Mencari…" : "Cari"}
                                </Button>
                                {filters.q && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className={secondaryButton}
                                        disabled={navigating}
                                        onClick={() => navigate({ q: "" })}
                                    >
                                        Reset
                                    </Button>
                                )}
                            </form>

                            <p role="status" className="sr-only">
                                {navigating ? "Memuat izin…" : ""}
                            </p>

                            {permissions.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-border bg-soft/30 py-10 text-center text-sm text-muted">
                                    <p className="font-medium text-ink">Tidak ada izin yang cocok dengan pencarian.</p>
                                    <p className="mt-1 text-xs text-muted">Coba cari dengan kata kunci lain atau kosongkan kolom pencarian.</p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border border-border">
                                    <table className="w-full text-left text-sm">
                                        <caption className="sr-only">
                                            Izin tersimpan untuk {selectedRole.nama}
                                        </caption>
                                        <thead className="border-b border-border bg-soft/60 text-xs font-semibold uppercase tracking-wider text-muted">
                                            <tr>
                                                <th scope="col" className="px-4 py-3">
                                                    Izin
                                                </th>
                                                <th scope="col" className="px-4 py-3">
                                                    Scope
                                                </th>
                                                <th scope="col" className="px-4 py-3">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {permissions.map((permission) => (
                                                <tr key={permission.id} className="hover:bg-soft/30 transition-colors">
                                                    <td className="px-4 py-3.5">
                                                        <p className="break-all font-mono font-semibold text-ink text-xs">
                                                            {permission.kode}
                                                        </p>
                                                        <p className="mt-1 max-w-xl break-words text-xs text-muted leading-relaxed">
                                                            {permission.keterangan ||
                                                                "—"}
                                                        </p>
                                                        {!permission.in_catalog && (
                                                            <span className="mt-1.5 inline-block">
                                                                <Badge variant="warning" size="sm">
                                                                    Di luar katalog rilis
                                                                </Badge>
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 align-top">
                                                        <span className="inline-flex items-center rounded-md border border-border bg-soft px-2.5 py-0.5 text-xs font-medium text-ink">
                                                            {permission.butuh_scope ===
                                                            "unit"
                                                                ? "Unit"
                                                                : "Global"}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3.5 align-top">
                                                        <Badge
                                                            variant={
                                                                permission.aktif
                                                                    ? "success"
                                                                    : "muted"
                                                            }
                                                            size="sm"
                                                            dot
                                                            className={
                                                                permission.aktif
                                                                    ? "text-success-dark"
                                                                    : undefined
                                                            }
                                                        >
                                                            {permission.aktif
                                                                ? "Aktif"
                                                                : "Nonaktif"}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            <nav
                                aria-label="Halaman izin"
                                className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4 text-xs text-muted"
                            >
                                <span>
                                    Halaman <strong className="font-semibold text-ink">{pagination.page}</strong>
                                </span>
                                <div className="flex items-center gap-2">
                                    {[
                                        ["Sebelumnya", pagination.prev_page_url],
                                        ["Berikutnya", pagination.next_page_url],
                                    ].map(([label, url]) =>
                                        url ? (
                                            <Link
                                                key={label}
                                                href={url}
                                                className="inline-flex items-center rounded-md border border-border bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-soft focus:ring-2 focus:ring-primary transition-colors"
                                                aria-disabled={navigating}
                                                onBefore={() => !pending.current}
                                                {...navigationEvents}
                                            >
                                                {label}
                                            </Link>
                                        ) : null,
                                    )}
                                </div>
                            </nav>
                        </div>
                    ) : (
                        <div className="mt-6 rounded-xl border border-dashed border-border bg-soft/30 p-8 text-center text-sm text-muted">
                            <KeyRound className="mx-auto h-8 w-8 text-muted/60 mb-2" aria-hidden="true" />
                            <p className="font-medium text-ink">Pilih peran untuk melihat izin bawaannya.</p>
                            <p className="mt-1 text-xs text-muted">Gunakan pemilih peran di atas untuk meninjau daftar hak akses yang telah dikonfigurasi.</p>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
