import type { Page } from "@inertiajs/core";
import type { RolePermissionStatus } from "@/types/role-permission";

// Ref URL hanya locator; hasil harus berasal dari flash respons operasi yang sama.
export function rolePermissionOutcome(
    receiptId: unknown,
    page: Pick<Page, "component" | "url" | "flash">,
): RolePermissionStatus | null {
    if (
        typeof receiptId !== "string" ||
        !receiptId ||
        !["Access/RolePermissionIndex", "Access/RolePermissionResult"].includes(
            page.component,
        )
    )
        return null;
    const url = new URL(page.url, "http://localhost");
    if (
        url.pathname !== "/akses/izin-peran/hasil" ||
        url.searchParams.get("receipt") !== receiptId
    )
        return null;
    const outcome = page.flash.rolePermissionOutcome;
    if (
        !outcome ||
        typeof outcome !== "object" ||
        !("receipt_id" in outcome) ||
        outcome.receipt_id !== receiptId ||
        !("status" in outcome)
    )
        return null;
    return outcome.status === "added" ||
        outcome.status === "revoked" ||
        outcome.status === "unchanged"
        ? outcome.status
        : null;
}

export function rolePermissionMessage(
    status: RolePermissionStatus | null,
): string {
    if (status === "added") return "Izin berhasil ditambahkan ke peran.";
    if (status === "revoked") return "Izin berhasil dicabut dari peran.";
    if (status === "unchanged") return "Tidak ada perubahan izin peran.";
    return "Hasil operasi ini tidak tersedia. Periksa data terbaru sebelum melakukan perubahan lain.";
}
