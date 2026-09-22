import { Head, Link, usePage } from "@inertiajs/react";
import { useEffect, useRef } from "react";
import {
    rolePermissionMessage,
    rolePermissionOutcome,
} from "@/Components/Access/rolePermissionOutcome";
import { AuthenticatedLayout } from "@/Layouts/AuthenticatedLayout";
import { secondaryButton } from "@/Pages/Auth/AuthShell";

export default function RolePermissionResult({
    receiptId,
    canReturn,
}: {
    receiptId: string | null;
    canReturn: boolean;
}) {
    const status = rolePermissionOutcome(receiptId, usePage());
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => {
        heading.current?.focus();
    }, []);
    return (
        <AuthenticatedLayout title="Hasil Perubahan Izin Peran">
            <Head title="Hasil Perubahan Izin Peran" />
            <section className="max-w-2xl rounded-xl border border-border bg-surface p-5 sm:p-6">
                <h1
                    ref={heading}
                    tabIndex={-1}
                    className="text-lg font-semibold"
                >
                    Hasil perubahan izin peran
                </h1>
                <p role="status" className="mt-3 text-sm">
                    {rolePermissionMessage(status)}
                </p>
                {canReturn ? (
                    <Link
                        href="/akses/izin-peran"
                        className={`${secondaryButton} mt-5 inline-flex`}
                    >
                        Kembali ke izin peran
                    </Link>
                ) : (
                    <p className="mt-4 text-sm text-muted">
                        Akses pengelolaan izin peran tidak tersedia untuk akun
                        Anda saat ini. Gunakan menu yang tersedia atau keluar
                        dari sistem. Halaman ini tidak memulihkan izin secara
                        otomatis.
                    </p>
                )}
            </section>
        </AuthenticatedLayout>
    );
}
