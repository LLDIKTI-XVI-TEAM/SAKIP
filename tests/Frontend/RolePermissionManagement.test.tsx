import type { ReactNode } from "react";
import { act, cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { router } from "@inertiajs/react";
import type { Page, PendingVisit } from "@inertiajs/core";
import {
    afterAll,
    afterEach,
    beforeAll,
    beforeEach,
    expect,
    it,
    vi,
} from "vitest";
import RolePermissionIndex from "@/Pages/Access/RolePermissionIndex";
import RolePermissionResult from "@/Pages/Access/RolePermissionResult";
import { AuthRecoveryFallback } from "@/Components/Auth/AuthRecoveryFallback";
import type { RolePermissionIndexProps } from "@/types/role-permission";

const currentPage = vi.hoisted(() => ({
    component: "Access/RolePermissionIndex",
    url: "/akses/izin-peran",
    props: {},
    flash: {} as Record<string, unknown>,
}));
vi.mock("@inertiajs/react", async (original) => ({
    ...(await original<typeof import("@inertiajs/react")>()),
    Head: () => null,
    usePage: () => currentPage,
}));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    AuthenticatedLayout: ({ children }: { children: ReactNode }) => (
        <main>{children}</main>
    ),
}));
const pic = { id: "pic-id", kode: "pic", nama: "PIC" };
const permission = {
    id: "permission-id",
    kode: "dashboard:read",
    keterangan: "Melihat dashboard",
    butuh_scope: "global" as const,
    aktif: true,
    attached: false,
    editable: true,
    non_editable_reason: null,
};
const props: RolePermissionIndexProps = {
    roles: [pic],
    selectedRole: pic,
    expectedState: "a".repeat(64),
    permissions: [permission],
    pagination: { page: 1, prev_page_url: null, next_page_url: null },
    filters: { role: pic.id, view: "available", q: "" },
    can: { manageRolePermissions: true },
    affectsActorRole: false,
    receiptId: null,
};
const methods = ["showModal", "close"] as const;
const originals = methods.map((name) =>
    Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name),
);
beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, "showModal", {
        configurable: true,
        value: function (this: HTMLDialogElement) {
            this.open = true;
        },
    });
    Object.defineProperty(HTMLDialogElement.prototype, "close", {
        configurable: true,
        value: function (this: HTMLDialogElement) {
            this.open = false;
        },
    });
});
afterAll(() =>
    methods.forEach((name, index) => {
        const descriptor = originals[index];
        if (descriptor)
            Object.defineProperty(
                HTMLDialogElement.prototype,
                name,
                descriptor,
            );
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    }),
);
beforeEach(() => {
    currentPage.component = "Access/RolePermissionIndex";
    currentPage.url = "/akses/izin-peran";
    currentPage.flash = {};
    vi.spyOn(router, "post").mockImplementation(() => undefined);
    vi.spyOn(router, "get").mockImplementation(() => undefined);
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

const visit: PendingVisit = {
    id: "test",
    url: new URL("http://localhost/akses/izin-peran"),
    method: "post",
    data: {},
    completed: false,
    cancelled: false,
    interrupted: false,
    replace: false,
    preserveScroll: true,
    preserveState: true,
    only: [],
    except: [],
    headers: {},
    errorBag: null,
    forceFormData: false,
    queryStringArrayFormat: "brackets",
    async: false,
    showProgress: true,
    prefetch: false,
    fresh: false,
    reset: [],
    preserveUrl: false,
    preserveErrors: false,
    invalidateCacheTags: [],
    viewTransition: false,
    component: null,
    pageProps: null,
    cached: false,
};
const response = (
    ref: string | null,
    flash: Record<string, unknown>,
    url = "/akses/izin-peran/hasil?receipt=a",
): Page => ({
    component: "Access/RolePermissionIndex",
    props: { errors: {}, receiptId: ref },
    url,
    version: null,
    rescuedProps: [],
    flash,
    rememberedState: {},
});
async function openAndSubmit() {
    const user = userEvent.setup();
    await user.click(
        screen.getByRole("button", { name: "Tambah izin dashboard:read" }),
    );
    const reason = screen.getByRole<HTMLTextAreaElement>("textbox", {
        name: /Alasan perubahan/,
    });
    expect(document.activeElement).toBe(reason);
    await user.type(reason, "Evaluasi kewenangan");
    await user.click(
        screen.getByRole("button", { name: "Konfirmasi tambah izin" }),
    );
    return user;
}

it("menampilkan PIC, membatasi aksi pada row editable dan menavigasi filter ke index biasa", async () => {
    const user = userEvent.setup();
    render(
        <RolePermissionIndex
            {...props}
            permissions={[
                permission,
                {
                    ...permission,
                    id: "scoped",
                    attached: true,
                    butuh_scope: "unit",
                    editable: false,
                    non_editable_reason: "scoped",
                },
            ]}
            filters={{ ...props.filters, view: "attached" }}
        />,
    );
    expect(screen.getByRole("option", { name: "PIC" })).toBeTruthy();
    expect(screen.getByText(/di luar pengelolaan izin global/)).toBeTruthy();
    expect(
        screen.queryByRole("button", { name: /Cabut izin dashboard/ }),
    ).toBeNull();
    await user.selectOptions(
        screen.getByRole("combobox", { name: "Tampilan izin" }),
        "available",
    );
    expect(vi.mocked(router.get).mock.calls[0].slice(0, 2)).toEqual([
        "/akses/izin-peran",
        { role: "pic-id", view: "available", q: "" },
    ]);
    await act(async () => {
        vi.mocked(router.get).mock.calls[0][2]?.onStart?.({
            ...visit,
            method: "get",
        });
    });
    expect(
        screen.getByRole<HTMLSelectElement>("combobox", { name: "Peran" })
            .disabled,
    ).toBe(true);
    expect(
        screen.getByRole<HTMLButtonElement>("button", { name: "Cari" })
            .disabled,
    ).toBe(true);
    expect(
        screen.getByRole<HTMLButtonElement>("button", {
            name: "Tambah izin dashboard:read",
        }).disabled,
    ).toBe(true);
    await user.click(
        screen.getByRole("button", { name: "Tambah izin dashboard:read" }),
    );
    expect(screen.queryByRole("dialog")).toBeNull();
});

it("mengirim satu delta/token, mempertahankan alasan pada validasi dan mengembalikan fokus setelah batal", async () => {
    render(<RolePermissionIndex {...props} />);
    const user = await openAndSubmit();
    const [url, data, options] = vi.mocked(router.post).mock.calls[0];
    expect(url).toBe("/akses/izin-peran/pic-id");
    expect(data).toEqual({
        permission_id: "permission-id",
        operation: "add",
        alasan: "Evaluasi kewenangan",
        expected_state: "a".repeat(64),
    });
    await act(async () => {
        options?.onError?.({ alasan: "Jelaskan alasan." });
    });
    const reason = screen.getByRole<HTMLTextAreaElement>("textbox", {
        name: /Alasan perubahan/,
    });
    expect(reason.value).toBe("Evaluasi kewenangan");
    expect(document.activeElement).toBe(reason);
    await user.click(screen.getByRole("button", { name: "Batal" }));
    expect(document.activeElement).toBe(
        screen.getByRole("button", { name: "Tambah izin dashboard:read" }),
    );
});

it("menahan pending/dismiss dan memblokir resubmit stale sampai data ditinjau ulang", async () => {
    render(<RolePermissionIndex {...props} />);
    const user = await openAndSubmit();
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => {
        options?.onStart?.(visit);
    });
    expect(
        screen.getByRole<HTMLButtonElement>("button", { name: "Batal" })
            .disabled,
    ).toBe(true);
    const cancel = new Event("cancel", { cancelable: true });
    screen.getByRole("dialog").dispatchEvent(cancel);
    expect(cancel.defaultPrevented).toBe(true);
    await act(async () => {
        options?.onError?.({
            expected_state:
                "Isi izin peran telah berubah. Tinjau data terbaru sebelum menyimpan.",
        });
    });
    await act(async () => {
        options?.onFinish?.({
            ...visit,
            completed: true,
            onCancelToken: vi.fn(),
            onBefore: vi.fn(),
            onBeforeUpdate: vi.fn(),
            onStart: vi.fn(),
            onProgress: vi.fn(),
            onFinish: vi.fn(),
            onCancel: vi.fn(),
            onSuccess: vi.fn(),
            onError: vi.fn(),
            onHttpException: vi.fn(),
            onNetworkError: vi.fn(),
            onFlash: vi.fn(),
            onPrefetched: vi.fn(),
            onPrefetching: vi.fn(),
        });
    });
    expect(document.activeElement).toBe(screen.getByRole("alert"));
    await user.click(
        screen.getByRole("button", { name: "Konfirmasi tambah izin" }),
    );
    expect(vi.mocked(router.post).mock.calls).toHaveLength(1);
    expect(
        screen.getByRole<HTMLTextAreaElement>("textbox", {
            name: /Alasan perubahan/,
        }).value,
    ).toBe("Evaluasi kewenangan");
    await user.click(
        screen.getByRole("button", { name: "Pilih alasan untuk disalin" }),
    );
    expect(
        screen.getByRole<HTMLTextAreaElement>("textbox", {
            name: /Alasan perubahan/,
        }).selectionEnd,
    ).toBe("Evaluasi kewenangan".length);
    expect(
        screen.getByText(/Muat ulang atau login akan meninggalkan/),
    ).toBeTruthy();
});

it("tidak menutup form dari receipt milik operasi lain dan hanya menerima korelasi lengkap", async () => {
    render(<RolePermissionIndex {...props} />);
    await openAndSubmit();
    const options = vi.mocked(router.post).mock.calls[0][2];
    await act(async () => {
        options?.onSuccess?.(
            response("a", {
                rolePermissionOutcome: { receipt_id: "b", status: "revoked" },
            }),
        );
    });
    expect(screen.getByRole("dialog")).toBeTruthy();
    expect(screen.getByRole("alert").textContent).toContain(
        "belum dapat dipastikan",
    );
    expect(
        screen.getByRole<HTMLButtonElement>("button", {
            name: "Konfirmasi tambah izin",
        }).disabled,
    ).toBe(true);
    await act(async () => {
        options?.onSuccess?.(
            response("a", {
                rolePermissionOutcome: { receipt_id: "a", status: "added" },
            }),
        );
    });
    expect(screen.queryByRole("dialog")).toBeNull();
});

it.each([401, 419])(
    "mengonsumsi recovery %s yang berkorelasi tanpa kehilangan draft atau membuka dialog kedua",
    async (status) => {
        render(
            <>
                <RolePermissionIndex {...props} />
                <AuthRecoveryFallback />
            </>,
        );
        const user = await openAndSubmit();
        const options = vi.mocked(router.post).mock.calls[0][2];
        await act(async () => {
            expect(
                options?.onHttpException?.({
                    status,
                    headers: {},
                    data: {
                        recovery: {
                            reason:
                                status === 401
                                    ? "authentication_required"
                                    : "csrf_mismatch",
                            rejected: {
                                method: "POST",
                                path: "/akses/izin-peran/pic-id",
                                before_action: true,
                            },
                        },
                    },
                }),
            ).toBe(false);
        });
        expect(screen.getAllByRole("dialog")).toHaveLength(1);
        expect(screen.getAllByRole("alert")).toHaveLength(1);
        expect(screen.getByRole("alert").textContent).toContain(
            "Permintaan perubahan ini ditolak",
        );
        expect(document.activeElement).toBe(
            screen.getByRole("heading", {
                name:
                    status === 401
                        ? "Autentikasi diperlukan"
                        : "Verifikasi keamanan diperlukan",
            }),
        );
        if (status === 401)
            expect(
                screen
                    .getByRole("link", { name: "Masuk ulang" })
                    .getAttribute("href"),
            ).toBe("/login?recovery=1");
        else
            expect(
                screen
                    .getByRole("button", { name: "Muat ulang halaman" })
                    .getAttribute("type"),
            ).toBe("button");
        const reason = screen.getByRole<HTMLTextAreaElement>("textbox", {
            name: /Alasan perubahan/,
        });
        expect(reason.value).toBe("Evaluasi kewenangan");
        await user.click(
            screen.getByRole("button", { name: "Pilih alasan untuk disalin" }),
        );
        expect(reason.selectionEnd - reason.selectionStart).toBe(
            reason.value.length,
        );
        expect(
            screen.getByRole<HTMLButtonElement>("button", {
                name: "Konfirmasi tambah izin",
            }).disabled,
        ).toBe(true);
        const cancel = new Event("cancel", { cancelable: true });
        screen.getByRole("dialog").dispatchEvent(cancel);
        expect(cancel.defaultPrevented).toBe(true);
        await user.click(
            screen.getByRole("button", { name: "Konfirmasi tambah izin" }),
        );
        expect(router.post).toHaveBeenCalledTimes(1);
    },
);

it.each([
    { status: 401, data: {} },
    { status: 419, data: {} },
    {
        status: 401,
        data: {
            recovery: {
                reason: "authentication_required",
                rejected: {
                    method: "GET",
                    path: "/akses/izin-peran/hasil",
                    before_action: true,
                },
            },
        },
    },
])(
    "recovery $status tanpa provenance mutation yang cocok tetap unknown",
    async ({ status, data }) => {
        render(<RolePermissionIndex {...props} />);
        const user = await openAndSubmit();
        await act(async () => {
            expect(
                vi.mocked(router.post).mock.calls[0][2]?.onHttpException?.({
                    status,
                    data,
                    headers: {},
                }),
            ).toBe(false);
        });
        expect(screen.getByRole("alert").textContent).toContain(
            "belum dapat dipastikan",
        );
        expect(screen.getByRole("alert").textContent).not.toContain(
            "Permintaan perubahan ini ditolak",
        );
        expect(
            screen.getByRole<HTMLTextAreaElement>("textbox", {
                name: /Alasan perubahan/,
            }).value,
        ).toBe("Evaluasi kewenangan");
        await user.click(
            screen.getByRole("button", { name: "Konfirmasi tambah izin" }),
        );
        expect(router.post).toHaveBeenCalledTimes(1);
    },
);

it("menangani 403 serta respons server/network yang tidak pasti tanpa replay atau false failure", async () => {
    render(<RolePermissionIndex {...props} />);
    const user = await openAndSubmit();
    const options = vi.mocked(router.post).mock.calls[0][2];
    for (const status of [500]) {
        let cancelled: boolean | void;
        await act(async () => {
            cancelled = options?.onHttpException?.({
                status,
                data: "",
                headers: {},
            });
        });
        expect(cancelled!).toBe(false);
        expect(screen.getByRole("alert").textContent).toContain(
            "belum dapat dipastikan",
        );
    }
    await act(async () => {
        options?.onHttpException?.({ status: 403, data: "", headers: {} });
    });
    expect(screen.getByRole("alert").textContent).toContain(
        "Akses pengelolaan berubah",
    );
    await act(async () => {
        options?.onNetworkError?.(new Error("offline"));
    });
    expect(screen.getByRole("alert").textContent).toContain("Koneksi terputus");
    await user.click(
        screen.getByRole("button", { name: "Konfirmasi tambah izin" }),
    );
    expect(vi.mocked(router.post).mock.calls).toHaveLength(1);
    expect(
        screen.getByRole<HTMLTextAreaElement>("textbox", {
            name: /Alasan perubahan/,
        }).value,
    ).toBe("Evaluasi kewenangan");
});

it("mengunci outcome pembatalan request yang belum pasti tanpa menghapus alasan", async () => {
    render(<RolePermissionIndex {...props} />);
    const user = await openAndSubmit();
    await act(async () => {
        vi.mocked(router.post).mock.calls[0][2]?.onCancel?.();
    });
    expect(screen.getByRole("alert").textContent).toContain(
        "belum dapat dipastikan",
    );
    expect(
        screen.getByRole<HTMLTextAreaElement>("textbox", {
            name: /Alasan perubahan/,
        }).value,
    ).toBe("Evaluasi kewenangan");
    expect(
        screen.getByRole<HTMLButtonElement>("button", {
            name: "Konfirmasi tambah izin",
        }).disabled,
    ).toBe(true);
    await user.click(
        screen.getByRole("button", { name: "Konfirmasi tambah izin" }),
    );
    expect(router.post).toHaveBeenCalledTimes(1);
});

it("memperingatkan dampak semua Superadmin dan result hanya memakai flash operasi saat ini", async () => {
    const superadmin = { id: "sa", kode: "superadmin", nama: "Superadmin" };
    const view = render(
        <RolePermissionIndex
            {...props}
            selectedRole={superadmin}
            affectsActorRole
            permissions={[
                { ...permission, kode: "akses:update", attached: true },
            ]}
        />,
    );
    await userEvent
        .setup()
        .click(screen.getByRole("button", { name: "Cabut izin akses:update" }));
    expect(screen.getByText(/Superadmin lain yang bergantung/)).toBeTruthy();
    expect(
        screen.getByText(/Grant individual yang masih berlaku/),
    ).toBeTruthy();
    view.unmount();
    currentPage.component = "Access/RolePermissionResult";
    currentPage.url = "/akses/izin-peran/hasil?receipt=a";
    currentPage.flash = {
        rolePermissionOutcome: { receipt_id: "a", status: "revoked" },
    };
    const receipt = render(
        <RolePermissionResult receiptId="a" canReturn={false} />,
    );
    expect(document.activeElement).toBe(
        screen.getByRole("heading", { name: "Hasil perubahan izin peran" }),
    );
    expect(screen.getByRole("status").textContent).toContain(
        "Izin berhasil dicabut dari peran",
    );
    expect(screen.queryByRole("link")).toBeNull();
    currentPage.flash = {};
    receipt.rerender(<RolePermissionResult receiptId="a" canReturn />);
    expect(screen.getByRole("status").textContent).toContain(
        "Hasil operasi ini tidak tersedia",
    );
    expect(
        screen.getByRole("link", { name: "Kembali ke izin peran" }),
    ).toBeTruthy();
});

it("toast tidak diputar ulang oleh props atau query dan memerlukan receipt, URL serta flash yang sama", async () => {
    currentPage.url = "/akses/izin-peran/hasil?receipt=a";
    currentPage.flash = {
        rolePermissionOutcome: { receipt_id: "a", status: "added" },
    };
    const view = render(<RolePermissionIndex {...props} receiptId="a" />);
    expect(screen.getByRole("status").textContent).toContain(
        "Izin berhasil ditambahkan",
    );
    await userEvent
        .setup()
        .click(screen.getByRole("button", { name: "Tutup notifikasi" }));
    expect(screen.queryByRole("status")).toBeNull();
    view.unmount();
    for (const [receiptId, url, flash] of [
        [
            "a",
            "/akses/izin-peran/hasil?receipt=b",
            { rolePermissionOutcome: { receipt_id: "a", status: "added" } },
        ],
        [
            null,
            "/akses/izin-peran?status=added",
            { rolePermissionOutcome: { receipt_id: "a", status: "added" } },
        ],
        ["a", "/akses/izin-peran/hasil?receipt=a", {}],
    ] as const) {
        currentPage.url = url;
        currentPage.flash = flash;
        const invalid = render(
            <RolePermissionIndex {...props} receiptId={receiptId} />,
        );
        expect(screen.queryByRole("status")).toBeNull();
        invalid.unmount();
    }
    expect(router.post).not.toHaveBeenCalled();
});
