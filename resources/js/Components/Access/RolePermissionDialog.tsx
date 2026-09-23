import { router, useForm } from "@inertiajs/react";
import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import { Button } from "@/Components/Button";
import { AuthRecoveryNotice } from "@/Components/Auth/AuthRecoveryNotice";
import { useAuthRecovery } from "@/hooks/useAuthRecovery";
import { rolePermissionOutcome } from "@/Components/Access/rolePermissionOutcome";
import { primaryButton, secondaryButton } from "@/Pages/Auth/AuthShell";
import type {
    ChangeRolePermissionForm,
    PermissionRow,
    RoleOption,
} from "@/types/role-permission";

export function RolePermissionDialog({
    role,
    permission,
    expectedState,
    affectsActorRole,
    onClose,
    onSaved,
}: {
    role: RoleOption;
    permission: PermissionRow;
    expectedState: string;
    affectsActorRole: boolean;
    onClose: () => void;
    onSaved: () => void;
}) {
    const id = useId();
    const dialog = useRef<HTMLDialogElement>(null);
    const reason = useRef<HTMLTextAreaElement>(null);
    const alert = useRef<HTMLParagraphElement>(null);
    const [message, setMessage] = useState("");
    const recovery = useAuthRecovery();
    const form = useForm<ChangeRolePermissionForm>({
        permission_id: permission.id,
        operation: permission.attached ? "revoke" : "add",
        alasan: "",
        expected_state: expectedState,
    });
    const reviewError =
        form.errors.expected_state ||
        form.errors.permission_id ||
        form.errors.operation ||
        ("role_id" in form.errors ? String(form.errors.role_id) : "");
    const needsReview = Boolean(reviewError || message || recovery.recovery);
    const lockout =
        role.kode === "superadmin" &&
        permission.kode === "akses:update" &&
        permission.attached;
    useEffect(() => {
        const element = dialog.current;
        element?.showModal();
        reason.current?.focus();
        return () => element?.close();
    }, []);
    useEffect(() => {
        if (form.processing || recovery.recovery) return;
        if (needsReview) alert.current?.focus();
        else if (form.errors.alasan) reason.current?.focus();
    }, [form.processing, form.errors, needsReview, message, recovery.recovery]);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || needsReview) return;
        form.post(`/akses/izin-peran/${role.id}`, {
            preserveScroll: true,
            onSuccess: (page) => {
                if (rolePermissionOutcome(page.props.receiptId, page))
                    onSaved();
                else
                    setMessage(
                        "Hasil perubahan belum dapat dipastikan. Periksa data terbaru sebelum melakukan perubahan lain.",
                    );
            },
            onCancel: () => {
                setMessage(
                    "Permintaan dibatalkan. Hasil perubahan belum dapat dipastikan. Periksa data terbaru sebelum melakukan perubahan lain.",
                );
            },
            onNetworkError: () => {
                setMessage(
                    "Koneksi terputus. Hasil perubahan belum dapat dipastikan. Periksa data terbaru sebelum melakukan perubahan lain.",
                );
                return false;
            },
            onHttpException: (response) => {
                if (
                    recovery.handleHttpException(response, {
                        effectiveMethod: "post",
                        path: `/akses/izin-peran/${role.id}`,
                        mutation: true,
                    })
                ) return false;
                setMessage(
                    response.status === 403
                        ? "Akses pengelolaan berubah atau tidak tersedia. Periksa akses dan data terbaru sebelum melakukan perubahan lain."
                        : "Hasil perubahan belum dapat dipastikan. Sesi mungkin berakhir atau respons tidak tersedia. Periksa data terbaru sebelum melakukan perubahan lain.",
                );
                return false;
            },
        });
    };
    return (
        <dialog
            ref={dialog}
            aria-labelledby={`${id}-title`}
            aria-describedby={`${id}-description${affectsActorRole || lockout ? ` ${id}-warning` : ""}`}
            onCancel={(event) => {
                if (form.processing || message || recovery.recovery)
                    event.preventDefault();
            }}
            onClose={onClose}
            className="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl border border-border bg-surface p-5 text-ink shadow-xl backdrop:bg-ink/50 sm:p-6"
        >
            <h2 id={`${id}-title`} className="text-lg font-semibold">
                {permission.attached
                    ? "Cabut izin dari peran"
                    : "Tambah izin ke peran"}
            </h2>
            <p id={`${id}-description`} className="mt-2 text-sm text-muted">
                Perubahan berlaku bagi seluruh pengguna yang memegang peran ini.
            </p>
            <dl className="mt-4 space-y-2 rounded-lg bg-soft p-4 text-sm">
                <div>
                    <dt className="font-medium">Peran</dt>
                    <dd>{role.nama}</dd>
                </div>
                <div>
                    <dt className="font-medium">Izin global</dt>
                    <dd className="break-words">{permission.kode}</dd>
                </div>
            </dl>
            <p className="mt-4 text-sm text-muted">
                Pencabutan menghapus izin dari peran. Grant individual yang
                masih berlaku tetap diperhitungkan; deny tetap menang.
            </p>
            {(affectsActorRole || lockout) && (
                <div
                    id={`${id}-warning`}
                    className="mt-4 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm"
                >
                    <p className="font-semibold">
                        {affectsActorRole
                            ? "Anda mengubah izin peran akun sendiri."
                            : "Perhatikan dampak perubahan akses."}
                    </p>
                    <p className="mt-1">
                        {lockout
                            ? "Anda dapat kehilangan akses pengelolaan setelah menyimpan. Superadmin lain yang bergantung pada izin peran ini juga dapat kehilangan akses. Halaman ini tidak memulihkan izin secara otomatis."
                            : "Menu dan akses akun Anda dapat berubah setelah disimpan."}
                    </p>
                </div>
            )}
            <form
                onSubmit={submit}
                className="mt-5 space-y-4"
                aria-busy={form.processing}
            >
                <div>
                    <label
                        htmlFor={`${id}-reason`}
                        className="block text-sm font-medium"
                    >
                        Alasan perubahan <span className="text-danger">*</span>
                    </label>
                    <textarea
                        ref={reason}
                        autoFocus
                        id={`${id}-reason`}
                        required
                        maxLength={2000}
                        rows={3}
                        value={form.data.alasan}
                        disabled={form.processing}
                        onChange={(event) =>
                            form.setData("alasan", event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.alasan)}
                        aria-describedby={`${id}-${form.errors.alasan ? "error" : "help"}`}
                        className="mt-2 w-full rounded-lg border border-border bg-surface p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50"
                    />
                    {form.errors.alasan ? (
                        <p
                            id={`${id}-error`}
                            role="alert"
                            className="mt-1 text-sm text-danger"
                        >
                            {form.errors.alasan}
                        </p>
                    ) : (
                        <p
                            id={`${id}-help`}
                            className="mt-1 text-xs text-muted"
                        >
                            Wajib diisi, maksimal 2.000 karakter. Dicatat dalam
                            jejak audit.
                        </p>
                    )}
                </div>
                <AuthRecoveryNotice
                    recovery={recovery.recovery}
                    pending={form.processing}
                />
                {needsReview && (
                    <div className="space-y-3 rounded-lg bg-soft p-3">
                        {(reviewError || message) && (
                            <p
                                ref={alert}
                                tabIndex={-1}
                                role="alert"
                                className="text-sm text-danger"
                            >
                                {reviewError || message}
                            </p>
                        )}
                        {!recovery.recovery && (
                            <p className="text-sm text-muted">
                                Muat ulang atau login akan meninggalkan alasan yang
                                belum tersimpan di form ini. Pilih dan salin alasan
                                secara manual jika masih diperlukan.
                            </p>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className={secondaryButton}
                                disabled={form.processing}
                                onClick={() => {
                                    reason.current?.focus();
                                    reason.current?.select();
                                }}
                            >
                                Pilih alasan untuk disalin
                            </Button>
                            {!recovery.recovery && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className={secondaryButton}
                                    disabled={form.processing}
                                    onClick={() =>
                                        router.get(
                                            "/akses/izin-peran",
                                            {
                                                role: role.id,
                                                view: permission.attached
                                                    ? "attached"
                                                    : "available",
                                            },
                                            { replace: true, preserveState: false },
                                        )
                                    }
                                >
                                    Muat ulang data
                                </Button>
                            )}
                        </div>
                    </div>
                )}
                <div className="flex flex-wrap justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        className={secondaryButton}
                        disabled={
                            form.processing || Boolean(message) || Boolean(recovery.recovery)
                        }
                        onClick={onClose}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        className={primaryButton}
                        isLoading={form.processing}
                        disabled={needsReview}
                    >
                        {permission.attached
                            ? "Konfirmasi cabut izin"
                            : "Konfirmasi tambah izin"}
                    </Button>
                </div>
            </form>
        </dialog>
    );
}
