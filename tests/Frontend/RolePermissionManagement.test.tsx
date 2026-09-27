import type { ActiveVisit, PendingVisit } from "@inertiajs/core";
import type { ReactNode } from "react";
import { act, cleanup, render, screen } from "@testing-library/react";
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
        page: 1,
        prev_page_url: null,
        next_page_url: "/akses/izin-peran?page=2",
    },
    filters: { role: role.id, q: "" },
    can: { viewRolePermissions: true },
};

it("menampilkan metadata tersimpan tanpa kontrol mutasi atau klaim izin efektif", () => {
    render(<RolePermissionIndex {...props} />);
    expect(screen.getByRole("heading", { name: "Peran & Izin", level: 2 })).not.toBeNull();
    expect(screen.getByText("legacy:read")).not.toBeNull();
    expect(screen.getByText("Di luar katalog rilis")).not.toBeNull();
    expect(screen.getByText("Nonaktif")).not.toBeNull();
    expect(screen.queryByRole("button", { name: /Tambah|Cabut|Simpan/ })).toBeNull();
    expect(screen.queryByRole("dialog")).toBeNull();
    expect(screen.queryByText(/berhasil/i)).toBeNull();
    expect(
        screen.getByRole("link", { name: "Berikutnya" }).getAttribute("href"),
    ).toBe("/akses/izin-peran?page=2");
});

it("pencarian keyboard hanya GET dan mencegah navigasi ganda selama pending", async () => {
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    const post = vi.spyOn(router, "post").mockImplementation(() => undefined);
    const user = userEvent.setup();
    render(<RolePermissionIndex {...props} />);
    await user.type(screen.getByRole("searchbox"), "dashboard{Enter}");
    expect(get).toHaveBeenCalledWith(
        "/akses/izin-peran",
        { role: role.id, q: "dashboard" },
        expect.any(Object),
    );
    const options = get.mock.calls[0][2];
    act(() => options?.onStart?.({} as PendingVisit));
    expect(
        screen.getByRole("button", { name: /Cari|Mencari/ }).hasAttribute("disabled"),
    ).toBe(true);
    expect(
        screen.getByRole("combobox", { name: "Peran" }).hasAttribute("disabled"),
    ).toBe(true);
    act(() => options?.onFinish?.({} as ActiveVisit));
    expect(
        screen.getByRole("button", { name: /Cari/ }).hasAttribute("disabled"),
    ).toBe(false);
    expect(post).not.toHaveBeenCalled();
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
        />,
    );
    expect(screen.getByText("Peran nonaktif")).not.toBeNull();
    expect(screen.getByText("Tidak ada izin yang cocok dengan pencarian.")).not.toBeNull();
});
