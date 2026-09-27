import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Badge } from "@/Components/Badge";
import { Button } from "@/Components/Button";
import { Input } from "@/Components/Input";
import { Select } from "@/Components/Select";
import { AuthenticatedLayout } from "@/Layouts/AuthenticatedLayout";
import { secondaryButton } from "@/Pages/Auth/AuthShell";
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
            <section
                aria-busy={navigating}
                className="rounded-xl border border-border bg-surface p-4 sm:p-6"
            >
                <h2 className="text-sm font-semibold text-ink">Peran & Izin</h2>
                <p className="mt-0.5 max-w-3xl text-xs text-muted">
                    Lihat izin bawaan setiap peran. Hak akses pengguna juga
                    dipengaruhi oleh grant, pembatasan izin, dan aturan pekerjaan.
                </p>
                <div className="mt-6 max-w-md">
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
                    <>
                        <div className="mt-6 flex flex-wrap items-center gap-3">
                            <h2 className="font-semibold">{selectedRole.nama}</h2>
                            {!selectedRole.aktif && (
                                <Badge variant="muted">Peran nonaktif</Badge>
                            )}
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
                                />
                            </div>
                            <Button type="submit" disabled={navigating}>
                                {navigating ? "Mencari…" : "Cari"}
                            </Button>
                        </form>
                        <p role="status" className="sr-only">
                            {navigating ? "Memuat izin…" : ""}
                        </p>
                        {permissions.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted">
                                Tidak ada izin yang cocok dengan pencarian.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <caption className="sr-only">
                                        Izin tersimpan untuk {selectedRole.nama}
                                    </caption>
                                    <thead className="border-b border-border bg-soft text-muted">
                                        <tr>
                                            <th scope="col" className="p-3">
                                                Izin
                                            </th>
                                            <th scope="col" className="p-3">
                                                Scope
                                            </th>
                                            <th scope="col" className="p-3">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {permissions.map((permission) => (
                                            <tr key={permission.id}>
                                                <td className="p-3">
                                                    <p className="break-all font-medium">
                                                        {permission.kode}
                                                    </p>
                                                    <p className="mt-1 max-w-xl break-words text-muted">
                                                        {permission.keterangan ||
                                                            "—"}
                                                    </p>
                                                    {!permission.in_catalog && (
                                                        <span className="mt-2 inline-block">
                                                            <Badge variant="warning">
                                                                Di luar katalog rilis
                                                            </Badge>
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3 align-top">
                                                    {permission.butuh_scope ===
                                                    "unit"
                                                        ? "Unit"
                                                        : "Global"}
                                                </td>
                                                <td className="p-3 align-top">
                                                    <Badge
                                                        variant={
                                                            permission.aktif
                                                                ? "success"
                                                                : "muted"
                                                        }
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
                            className="mt-5 flex flex-wrap items-center justify-between gap-3"
                        >
                            <span className="text-sm text-muted">
                                Halaman {pagination.page}
                            </span>
                            <div className="flex gap-2">
                                {[
                                    ["Sebelumnya", pagination.prev_page_url],
                                    ["Berikutnya", pagination.next_page_url],
                                ].map(([label, url]) =>
                                    url ? (
                                        <Link
                                            key={label}
                                            href={url}
                                            className={secondaryButton}
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
                    </>
                ) : (
                    <p className="mt-6 text-sm text-muted">
                        Pilih peran untuk melihat izin bawaannya.
                    </p>
                )}
            </section>
        </AuthenticatedLayout>
    );
}
