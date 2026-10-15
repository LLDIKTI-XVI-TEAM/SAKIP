import type { ActiveVisit, Page, PendingVisit } from "@inertiajs/core";
import type { ReactNode } from "react";
import { act, cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { router } from "@inertiajs/react";
import { afterEach, expect, it, vi } from "vitest";
import RolePermissionIndex from "@/Pages/Access/RolePermissionIndex";
import type { RolePermissionIndexProps } from "@/types/role-permission";

vi.mock("@inertiajs/react", async (original) => ({
    ...(await original<typeof import("@inertiajs/react")>()),
    Head: () => null,
    usePage: () => ({ props: {}, flash: {} }),
}));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => (
        <main>{children}</main>
    ),
}));
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.useRealTimers();
});

const role = {
    id: "11111111-1111-4111-8111-111111111111",
    kode: "pegawai",
    nama: "Pegawai",
    aktif: true,
};
const props: RolePermissionIndexProps = {
    roles: [role],
    selectedRole: role,
    permissions: [
        {
            id: "22222222-2222-4222-8222-222222222222",
            kode: "legacy:read",
            keterangan: null,
            butuh_scope: "global",
            aktif: false,
            in_catalog: false,
        },
    ],
    pagination: {
        links: [
            { url: null, label: "&laquo; Previous", active: false },
            { url: "/akses/izin-peran?page=1", label: "1", active: true },
            { url: "/akses/izin-peran?page=2", label: "2", active: false },
            { url: "/akses/izin-peran?page=2", label: "Next &raquo;", active: false },
        ],
        from: 1,
        to: 20,
        total: 25,
    },
    filters: { role: role.id, q: "" },
    can: { viewRolePermissions: true },
};

it("menampilkan metadata tersimpan tanpa kontrol mutasi atau klaim izin efektif", () => {
    render(<RolePermissionIndex {...props} />);
    expect(screen.getByRole("heading", { name: "Izin bawaan Pegawai", level: 2 })).not.toBeNull();
    expect(screen.queryByRole("heading", { name: "Peran & Izin", level: 2 })).toBeNull();
    expect(screen.getByText("25 izin")).not.toBeNull();
    const table = screen.getByRole("table", { name: "Izin tersimpan untuk Pegawai" });
    expect(within(table).getAllByRole("columnheader").map((cell) => cell.textContent)).toEqual(["Izin", "Scope", "Status"]);
    expect(screen.getByText("legacy:read")).not.toBeNull();
    expect(screen.getByText("Di luar katalog rilis")).not.toBeNull();
    expect(screen.getByText("Nonaktif")).not.toBeNull();
    expect(screen.queryByRole("button", { name: /Tambah|Cabut|Simpan/ })).toBeNull();
    expect(screen.queryByRole("dialog")).toBeNull();
    expect(screen.queryByText(/berhasil/i)).toBeNull();
    const nav = screen.getByRole("navigation", { name: "Halaman izin" });
    expect(nav.textContent).toContain("Menampilkan 1–20 dari 25 data");
    expect(within(nav).getByRole("link", { name: "Berikutnya ›" }).getAttribute("href")).toBe("/akses/izin-peran?page=2");
});

it("mencari otomatis setelah 2 huruf hanya lewat GET dan mengunci pilihan peran selama pending", () => {
    vi.useFakeTimers();
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    const post = vi.spyOn(router, "post").mockImplementation(() => undefined);
    render(<RolePermissionIndex {...props} />);
    const box = screen.getByRole("searchbox", { name: "Cari kode atau keterangan izin" });
    fireEvent.change(box, { target: { value: "d" } });
    act(() => { vi.advanceTimersByTime(1000); });
    expect(get).not.toHaveBeenCalled();
    fireEvent.change(box, { target: { value: "da" } });
    act(() => { vi.advanceTimersByTime(349); });
    expect(get).not.toHaveBeenCalled();
    act(() => { vi.advanceTimersByTime(1); });
    expect(get).toHaveBeenCalledWith(
        "/akses/izin-peran",
        { role: role.id, q: "da" },
        expect.objectContaining({ preserveState: true, preserveScroll: true, replace: true }),
    );
    const options = get.mock.calls[0][2];
    act(() => options?.onStart?.({} as PendingVisit));
    expect(screen.getByRole("combobox", { name: "Peran" }).hasAttribute("disabled")).toBe(true);
    expect(box.hasAttribute("disabled")).toBe(false);
    act(() => options?.onFinish?.({} as ActiveVisit));
    expect(screen.getByRole("combobox", { name: "Peran" }).hasAttribute("disabled")).toBe(false);
    expect(screen.queryByRole("button", { name: /Cari/ })).toBeNull();
    expect(post).not.toHaveBeenCalled();
});

it.each(["", "d"])("kotak pencarian \"%s\" (kurang dari 2 huruf) menampilkan kembali semua izin peran", (value) => {
    vi.useFakeTimers();
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    render(<RolePermissionIndex {...props} filters={{ role: role.id, q: "da" }} />);
    act(() => { vi.advanceTimersByTime(1000); });
    expect(get).not.toHaveBeenCalled();
    fireEvent.change(screen.getByRole("searchbox"), { target: { value } });
    act(() => { vi.advanceTimersByTime(350); });
    expect(get.mock.calls.map((call) => call.slice(0, 2))).toEqual([["/akses/izin-peran", { role: role.id, q: "" }]]);
});

it("pencarian tertunda tidak membatalkan pergantian peran yang sedang dimuat", () => {
    vi.useFakeTimers();
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    const other = { ...role, id: "33333333-3333-4333-8333-333333333333", kode: "admin", nama: "Admin" };
    render(<RolePermissionIndex {...props} roles={[role, other]} />);
    fireEvent.change(screen.getByRole("searchbox"), { target: { value: "da" } });
    fireEvent.change(screen.getByRole("combobox", { name: "Peran" }), { target: { value: other.id } });
    act(() => get.mock.calls[0][2]?.onStart?.({} as PendingVisit));
    act(() => { vi.advanceTimersByTime(350); });
    expect(get.mock.calls.map((call) => call.slice(0, 2))).toEqual([["/akses/izin-peran", { role: other.id, q: "" }]]);
});

it("mengirim kata cari terbaru sekali setelah pencarian sebelumnya gagal, tanpa mengulang terus-menerus", () => {
    vi.useFakeTimers();
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    render(<RolePermissionIndex {...props} />);
    const box = screen.getByRole("searchbox");
    fireEvent.change(box, { target: { value: "da" } });
    act(() => { vi.advanceTimersByTime(350); });
    const first = get.mock.calls[0][2];
    act(() => first?.onStart?.({} as PendingVisit));
    fireEvent.change(box, { target: { value: "dash" } });
    act(() => { vi.advanceTimersByTime(350); });
    expect(get).toHaveBeenCalledTimes(1);
    // Kunjungan gagal tidak mengubah props, tetapi Inertia tetap memanggil onFinish.
    act(() => first?.onFinish?.({} as ActiveVisit));
    expect(screen.queryByRole("alert")).toBeNull();
    act(() => { vi.advanceTimersByTime(350); });
    expect(get.mock.calls.map((call) => call.slice(0, 2))).toEqual([
        ["/akses/izin-peran", { role: role.id, q: "da" }],
        ["/akses/izin-peran", { role: role.id, q: "dash" }],
    ]);
    const second = get.mock.calls[1][2];
    act(() => second?.onStart?.({} as PendingVisit));
    act(() => second?.onFinish?.({} as ActiveVisit));
    act(() => { vi.advanceTimersByTime(5000); });
    expect(get).toHaveBeenCalledTimes(2);
});

it.each<Partial<ActiveVisit>>([{ interrupted: true }, { cancelled: true }])(
    "tidak mengulang pencarian bila kunjungan dibatalkan, misalnya karena pindah halaman (%o)",
    (flags) => {
        vi.useFakeTimers();
        const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
        render(<RolePermissionIndex {...props} />);
        const box = screen.getByRole("searchbox");
        fireEvent.change(box, { target: { value: "da" } });
        act(() => { vi.advanceTimersByTime(350); });
        const first = get.mock.calls[0][2];
        act(() => first?.onStart?.({} as PendingVisit));
        fireEvent.change(box, { target: { value: "dash" } });
        act(() => { vi.advanceTimersByTime(350); });
        act(() => first?.onFinish?.(flags as ActiveVisit));
        act(() => { vi.advanceTimersByTime(5000); });
        expect(get).toHaveBeenCalledTimes(1);
        expect(screen.queryByRole("alert")).toBeNull();
    },
);

it("memberi tahu dan menawarkan coba lagi bila pencarian otomatis gagal, tanpa membuang kata cari", () => {
    vi.useFakeTimers();
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    render(<RolePermissionIndex {...props} />);
    const box = screen.getByRole("searchbox") as HTMLInputElement;
    fireEvent.change(box, { target: { value: "da" } });
    act(() => { vi.advanceTimersByTime(350); });
    const failed = get.mock.calls[0][2];
    act(() => failed?.onStart?.({} as PendingVisit));
    // Gangguan jaringan atau server: Inertia tetap memanggil onFinish tanpa onSuccess.
    act(() => failed?.onFinish?.({ cancelled: false, interrupted: false } as ActiveVisit));
    expect(screen.getByRole("alert").textContent).toContain("Hasil pencarian belum diperbarui. Periksa koneksi, lalu coba lagi.");
    expect(box.value).toBe("da");
    fireEvent.click(screen.getByRole("button", { name: "Coba lagi" }));
    expect(screen.queryByRole("alert")).toBeNull();
    act(() => { vi.advanceTimersByTime(350); });
    expect(get.mock.calls.map((call) => call.slice(0, 2))).toEqual([
        ["/akses/izin-peran", { role: role.id, q: "da" }],
        ["/akses/izin-peran", { role: role.id, q: "da" }],
    ]);
    const retried = get.mock.calls[1][2];
    act(() => retried?.onStart?.({} as PendingVisit));
    act(() => retried?.onSuccess?.({} as Page));
    act(() => retried?.onFinish?.({ cancelled: false, interrupted: false } as ActiveVisit));
    expect(screen.queryByRole("alert")).toBeNull();
});

it("menyediakan tombol ke halaman pertama hanya untuk halaman izin di luar jangkauan", async () => {
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    const empty = { links: [], from: null, to: null, total: 0 };
    const view = render(<RolePermissionIndex {...props} permissions={[]} pagination={empty} />);
    expect(screen.queryByRole("button", { name: "Ke halaman pertama" })).toBeNull();
    view.rerender(<RolePermissionIndex {...props} permissions={[]} pagination={{ ...empty, total: 13 }} />);
    expect(screen.getByText("Tidak ada izin di halaman ini.")).not.toBeNull();
    await userEvent.setup().click(screen.getByRole("button", { name: "Ke halaman pertama" }));
    expect(get).toHaveBeenCalledWith("/akses/izin-peran", { role: role.id, q: "" }, expect.objectContaining({ preserveState: false }));
});

it("menonaktifkan paginasi peran lama selama pergantian peran dimuat", () => {
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    const other = { ...role, id: "33333333-3333-4333-8333-333333333333", kode: "admin", nama: "Admin" };
    render(<RolePermissionIndex {...props} roles={[role, other]} />);
    const nav = screen.getByRole("navigation", { name: "Halaman izin" });
    expect(nav.closest("[inert]")).toBeNull();
    fireEvent.change(screen.getByRole("combobox", { name: "Peran" }), { target: { value: other.id } });
    const visit = get.mock.calls[0][2];
    act(() => visit?.onStart?.({} as PendingVisit));
    expect(nav.closest("[inert]")).not.toBeNull();
    act(() => visit?.onFinish?.({} as ActiveVisit));
    expect(nav.closest("[inert]")).toBeNull();
});

it.each([
    [0, "Peran ini belum memiliki izin bawaan."],
    [13, "Tidak ada izin di halaman ini."],
])("membedakan peran tanpa izin dari halaman di luar jangkauan (total %i)", (total, message) => {
    render(<RolePermissionIndex {...props} permissions={[]} pagination={{ links: [], from: null, to: null, total }} />);
    expect(screen.getByText(message)).not.toBeNull();
});

it("pilihan role mereset pagination melalui GET dan empty state tetap jelas", async () => {
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    render(
        <RolePermissionIndex
            {...props}
            selectedRole={null}
            permissions={[]}
            filters={{ role: null, q: "" }}
        />,
    );
    expect(screen.getByText("Pilih peran untuk melihat izin bawaannya.")).not.toBeNull();
    await userEvent
        .setup()
        .selectOptions(screen.getByRole("combobox", { name: "Peran" }), role.id);
    expect(get).toHaveBeenCalledWith(
        "/akses/izin-peran",
        { role: role.id, q: "" },
        expect.any(Object),
    );
});

it("hasil kosong dan role nonaktif tetap ditampilkan sebagai metadata", () => {
    render(
        <RolePermissionIndex
            {...props}
            selectedRole={{ ...role, aktif: false }}
            permissions={[]}
            pagination={{ links: [], from: null, to: null, total: 0 }}
            filters={{ role: role.id, q: "tidak-ada" }}
        />,
    );
    expect(screen.getByText("Peran nonaktif")).not.toBeNull();
    expect(screen.getByText("Tidak ada izin yang cocok dengan pencarian.")).not.toBeNull();
});
