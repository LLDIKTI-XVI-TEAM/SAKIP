import type { ActiveVisit, PendingVisit } from "@inertiajs/core";
import type { ReactNode } from "react";
import { act, cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { router } from "@inertiajs/react";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import EffectivePermissionIndex from "@/Pages/Access/EffectivePermissionIndex";
import type { EffectivePermissionIndexProps } from "@/types/effective-permission";

vi.mock("@inertiajs/react", async (original) => ({
    ...(await original<typeof import("@inertiajs/react")>()), Head: () => null, usePage: () => ({ props: { errors: {} }, flash: {} }),
}));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}));
beforeEach(() => { vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({ items: [], hasMore: false }), { status: 200 }))); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });
const props: EffectivePermissionIndexProps = {
    selectedUser: { id: "target", nama: "Pengguna Sintetis", email: "target@example.test", status: "aktif", role: { id: "role", kode: "pegawai", nama: "Pegawai", aktif: true, in_catalog: true } },
    selectedUnit: { id: "unit-a", nama: "Unit A", status: "aktif" },
    filters: { user_id: "target", unit_id: "unit-a", q: "", scope: "" },
    permissions: [{
        id: "permission", kode: "pengukuran:update", keterangan: "Mengubah pengukuran", scope: "unit", aktif: true,
        decision: { allowed: false, permission: "pengukuran:update", reason: "explicit_deny", roles: [], grants: ["grant"], denies: ["deny"] },
        status: "Dicabut oleh Deny", explanation: "Deny menang atas Grant.",
        sources: [
            { id: "grant", kind: "grant", label: "Grant", unit: { id: "unit-a", nama: "Unit A", status: "aktif" }, alasan: "Hak pengisian", effective: false },
            { id: "deny", kind: "deny", label: "Deny", unit: null, alasan: "Pembatasan sintetis", effective: true },
        ],
    }],
    diagnostics: [], pagination: { page: 1, prev_page_url: null, next_page_url: "/akses/jelaskan-izin?user_id=target&unit_id=unit-a&page=2" },
    diagnosticPagination: { page: 1, prev_page_url: null, next_page_url: null },
};

it("menjelaskan hasil server dan sumber tersimpan tanpa kontrol mutasi", async () => {
    render(<EffectivePermissionIndex {...props} />);
    expect(screen.getByText("Pengguna Sintetis")).toBeTruthy();
    expect(screen.getByText("Dicabut oleh Deny")).toBeTruthy();
    expect(screen.getByText("Alasan: Hak pengisian")).toBeTruthy();
    expect(screen.getByText(/Tersimpan, tidak efektif/)).toBeTruthy();
    expect(screen.getByText(/Pembatasan berlaku/)).toBeTruthy();
    expect(screen.queryByRole("button", { name: /Tambah|Cabut|Simpan|Ubah peran/ })).toBeNull();
    expect(screen.queryByRole("dialog")).toBeNull();
    expect(screen.getByRole("table").querySelectorAll('th[scope="col"]')).toHaveLength(4);
    expect(screen.getByRole("link", { name: "Berikutnya" }).getAttribute("href")).toContain("user_id=target");
    await waitFor(() => expect(screen.queryByText("Memuat unit…")).toBeNull());
});

it("filter dan konteks memakai GET serta mereset page dan menahan navigasi pending", async () => {
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    const post = vi.spyOn(router, "post").mockImplementation(() => undefined);
    const user = userEvent.setup();
    render(<EffectivePermissionIndex {...props} />);
    await user.type(screen.getByRole("searchbox", { name: "Cari kode atau keterangan izin" }), "pengukuran{Enter}");
    expect(get).toHaveBeenCalledWith("/akses/jelaskan-izin", { ...props.filters, q: "pengukuran" }, expect.any(Object));
    const options = get.mock.calls[0][2];
    act(() => options?.onStart?.({} as PendingVisit));
    expect(screen.getByRole("button", { name: "Memuat…" }).hasAttribute("disabled")).toBe(true);
    act(() => options?.onFinish?.({} as ActiveVisit));
    await user.selectOptions(screen.getByRole("combobox", { name: "Konteks pemeriksaan" }), "");
    expect(get).toHaveBeenLastCalledWith("/akses/jelaskan-izin", { ...props.filters, unit_id: null }, expect.any(Object));
    expect(post).not.toHaveBeenCalled();
});

it("empty state tidak mengklaim penolakan sebelum pengguna dipilih", async () => {
    render(<EffectivePermissionIndex {...props} selectedUser={null} permissions={[]} filters={{ ...props.filters, user_id: null }} />);
    expect(screen.getByRole("heading", { name: "Pilih pengguna untuk memulai" })).toBeTruthy();
    expect(screen.queryByRole("table")).toBeNull();
    expect(screen.queryByText("Tidak efektif")).toBeNull();
    await waitFor(() => expect(screen.queryByText("Memuat unit…")).toBeNull());
});

it("diagnosis nonaktif dan di luar katalog dibedakan dari permission aktif", async () => {
    render(<EffectivePermissionIndex {...props} selectedUser={{ ...props.selectedUser!, status: "nonaktif", role: null }}
        permissions={[{ ...props.permissions[0], status: "Pilih unit untuk memeriksa", sources: [] }]}
        diagnostics={[{ ...props.permissions[0], kode: "legacy:read", aktif: false, status: "Tidak efektif", decision: { ...props.permissions[0].decision, reason: "unknown_permission" } }]} />);
    expect(screen.getByText("Tanpa peran resmi")).toBeTruthy();
    expect(screen.getByText(/Pengguna ini tidak memiliki izin efektif/)).toBeTruthy();
    expect(screen.getByText("Pilih unit untuk memeriksa")).toBeTruthy();
    expect(screen.getByRole("heading", { name: "Sumber di luar katalog rilis" })).toBeTruthy();
    expect(screen.getByText("legacy:read")).toBeTruthy();
    await waitFor(() => expect(screen.queryByText("Memuat unit…")).toBeNull());
});

it("unit lookup yang gagal memberi feedback dan dapat dicoba lagi", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValueOnce(new Response("", { status: 403 }))
        .mockResolvedValue(new Response(JSON.stringify({ items: [{ id: "unit-b", nama: "Unit B", status: "nonaktif" }], hasMore: false }), { status: 200 })));
    render(<EffectivePermissionIndex {...props} />);
    expect(await screen.findByRole("alert")).toHaveProperty("textContent", "Unit belum dapat dimuat. Coba cari kembali.");
    await userEvent.setup().click(screen.getByRole("button", { name: "Cari" }));
    expect(await screen.findByRole("option", { name: "Unit B (nonaktif)" })).toBeTruthy();
});
