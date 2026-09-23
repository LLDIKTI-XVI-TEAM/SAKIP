import { Head, Link, router, usePage } from "@inertiajs/react";
import { CheckCircle, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { Button } from "@/Components/Button";
import { RolePermissionDialog } from "@/Components/Access/RolePermissionDialog";
import {
    rolePermissionMessage,
    rolePermissionOutcome,
} from "@/Components/Access/rolePermissionOutcome";
import { AuthenticatedLayout } from "@/Layouts/AuthenticatedLayout";
import { secondaryButton } from "@/Pages/Auth/AuthShell";
import type {
    PermissionRow,
    RoleOption,
    RolePermissionIndexProps,
} from "@/types/role-permission";

const fieldClass =
    "mt-2 w-full rounded-lg border border-border bg-surface p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary";
const readOnlyCopy = {
    scoped: "Izin ini berada di luar pengelolaan izin global pada halaman ini.",
    inactive: "Izin nonaktif. Isi tersimpan dipertahankan.",
    unknown: "Izin tersimpan ini tidak termasuk katalog pengelolaan.",
};

export default function RolePermissionIndex({
    roles,
    selectedRole,
    expectedState,
    permissions,
    pagination,
    filters,
    can,
    affectsActorRole,
    receiptId,
}: RolePermissionIndexProps) {
    const page = usePage();
    const status = rolePermissionOutcome(receiptId, page);
    const [dismissed, setDismissed] = useState<string | null>(null);
    const [query, setQuery] = useState(filters.q);
    const [navigating, setNavigating] = useState(false);
    const [modal, setModal] = useState<{
        role: RoleOption;
        permission: PermissionRow;
        state: string;
        affectsActorRole: boolean;
    } | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const title = useRef<HTMLHeadingElement>(null);
    useEffect(() => {
        setQuery(filters.q);
    }, [filters.q]);
    useEffect(() => {
        if (modal || !trigger.current) return;
        if (trigger.current.isConnected) trigger.current.focus();
        else title.current?.focus();
    }, [modal]);
    const navigationEvents = {
        onStart: () => setNavigating(true),
        onFinish: () => setNavigating(false),
    };
    const navigate = (next: Partial<typeof filters>) => {
        if (navigating || modal) return;
        router.get(
            "/akses/izin-peran",
            { ...filters, ...next },
            { preserveState: false, ...navigationEvents },
        );
    };
    return (
        <AuthenticatedLayout title="Izin Peran">
            <Head title="Izin Peran" />
            {status && dismissed !== receiptId && (
                <div className="fixed bottom-4 right-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl border border-success/30 bg-surface p-4 shadow-lg">
                    <CheckCircle
                        aria-hidden="true"
                        className="h-5 w-5 shrink-0 text-success"
                    />
                    <p role="status" className="flex-1 text-sm font-medium">
                        {rolePermissionMessage(status)}
                    </p>
                    <button
                        type="button"
                        aria-label="Tutup notifikasi"
                        onClick={() => setDismissed(receiptId)}
                        className="-m-2 rounded-lg p-3 text-muted hover:bg-soft focus:ring-2 focus:ring-primary"
                    >
                        <X aria-hidden="true" className="h-4 w-4" />
                    </button>
                </div>
            )}
            <section className="rounded-xl border border-border bg-surface p-4 sm:p-6">
                <h1 ref={title} tabIndex={-1} className="text-lg font-semibold">
                    Pengelolaan izin peran
                </h1>
                <p className="mt-2 max-w-3xl text-sm text-muted">
                    Kelola satu izin global dalam setiap perubahan. Perubahan
                    berlaku bagi seluruh pengguna yang memegang peran ini.
                </p>
                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            htmlFor="role-permission-role"
                            className="block text-sm font-medium"
                        >
                            Peran
                        </label>
                        <select
                            id="role-permission-role"
                            disabled={navigating}
                            value={filters.role ?? ""}
                            className={fieldClass}
                            onChange={(event) =>
                                navigate({ role: event.target.value || null })
                            }
                        >
                            <option value="">Pilih peran</option>
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.nama}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label
                            htmlFor="role-permission-view"
                            className="block text-sm font-medium"
                        >
                            Tampilan izin
                        </label>
                        <select
                            id="role-permission-view"
                            value={filters.view}
                            disabled={!selectedRole || navigating}
                            className={`${fieldClass} disabled:opacity-50`}
                            onChange={(event) =>
                                navigate({
                                    view:
                                        event.target.value === "available"
                                            ? "available"
                                            : "attached",
                                })
                            }
                        >
                            <option value="attached">Izin terpasang</option>
                            <option value="available">
                                Izin yang dapat ditambahkan
                            </option>
                        </select>
                    </div>
                </div>
                {selectedRole ? (
                    <>
                        <form
                            role="search"
                            className="my-5 flex flex-wrap items-end gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                navigate({ q: query });
                            }}
                        >
                            <div className="min-w-0 flex-1">
                                <label
                                    htmlFor="role-permission-search"
                                    className="block text-sm font-medium"
                                >
                                    Cari kode atau keterangan izin
                                </label>
                                <input
                                    id="role-permission-search"
                                    type="search"
                                    maxLength={100}
                                    disabled={navigating}
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    className={fieldClass}
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={navigating}
                                variant="outline"
                                className={secondaryButton}
                            >
                                Cari
                            </Button>
                        </form>
                        <h2 className="text-sm font-semibold">
                            {filters.view === "attached"
                                ? "Izin terpasang"
                                : "Izin tersedia"}{" "}
                            · {selectedRole.nama}
                        </h2>
                        {permissions.length === 0 ? (
                            <p className="mt-3 rounded-lg bg-soft p-4 text-sm text-muted">
                                {filters.q
                                    ? "Tidak ada izin yang sesuai dengan pencarian."
                                    : filters.view === "attached"
                                      ? "Belum ada izin yang terpasang pada peran ini."
                                      : "Tidak ada izin global yang dapat ditambahkan."}
                            </p>
                        ) : (
                            <ul className="mt-3 divide-y divide-border">
                                {permissions.map((permission) => (
                                    <li
                                        key={permission.id}
                                        className="flex flex-col gap-3 py-4 lg:flex-row lg:items-start lg:justify-between"
                                    >
                                        <div className="min-w-0 space-y-1 text-sm">
                                            <h3 className="break-words font-semibold">
                                                {permission.kode}
                                            </h3>
                                            {permission.keterangan && (
                                                <p className="break-words text-muted">
                                                    {permission.keterangan}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted">
                                                {permission.butuh_scope ===
                                                "global"
                                                    ? "Global"
                                                    : "Konteks unit"}
                                                {!permission.aktif &&
                                                    " · Nonaktif"}
                                            </p>
                                            {permission.non_editable_reason && (
                                                <p className="text-sm text-muted">
                                                    {
                                                        readOnlyCopy[
                                                            permission
                                                                .non_editable_reason
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        {can.manageRolePermissions &&
                                            permission.editable &&
                                            expectedState && (
                                                <Button
                                                    type="button"
                                                    disabled={navigating}
                                                    variant="outline"
                                                    className={`${secondaryButton} self-start lg:shrink-0`}
                                                    aria-label={`${permission.attached ? "Cabut" : "Tambah"} izin ${permission.kode}`}
                                                    onClick={(event) => {
                                                        trigger.current =
                                                            event.currentTarget;
                                                        setModal({
                                                            role: selectedRole,
                                                            permission,
                                                            state: expectedState,
                                                            affectsActorRole,
                                                        });
                                                    }}
                                                >
                                                    {permission.attached
                                                        ? "Cabut izin"
                                                        : "Tambah izin"}
                                                </Button>
                                            )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {(pagination.prev_page_url ||
                            pagination.next_page_url) && (
                            <nav
                                aria-label="Halaman izin peran"
                                className="mt-6 flex items-center justify-between gap-3 text-sm"
                            >
                                {pagination.prev_page_url ? (
                                    <Link
                                        href={pagination.prev_page_url}
                                        {...navigationEvents}
                                        onBefore={() => !navigating}
                                        className="rounded text-primary underline focus:ring-2 focus:ring-primary"
                                    >
                                        Sebelumnya
                                    </Link>
                                ) : (
                                    <span className="text-muted">
                                        Sebelumnya
                                    </span>
                                )}
                                <span>Halaman {pagination.page}</span>
                                {pagination.next_page_url ? (
                                    <Link
                                        href={pagination.next_page_url}
                                        {...navigationEvents}
                                        onBefore={() => !navigating}
                                        className="rounded text-primary underline focus:ring-2 focus:ring-primary"
                                    >
                                        Berikutnya
                                    </Link>
                                ) : (
                                    <span className="text-muted">
                                        Berikutnya
                                    </span>
                                )}
                            </nav>
                        )}
                    </>
                ) : (
                    <p className="mt-5 rounded-lg bg-soft p-4 text-sm text-muted">
                        Pilih peran untuk melihat dan mengelola izin globalnya.
                    </p>
                )}
            </section>
            {modal && (
                <RolePermissionDialog
                    role={modal.role}
                    permission={modal.permission}
                    expectedState={modal.state}
                    affectsActorRole={modal.affectsActorRole}
                    onClose={() => setModal(null)}
                    onSaved={() => setModal(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}
