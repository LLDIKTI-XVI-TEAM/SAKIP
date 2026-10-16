import type { ReactNode } from "react";
import { cleanup, render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { router } from "@inertiajs/react";
import { afterEach, expect, it, vi } from "vitest";
import AuditIndex from "@/Pages/Audit/Index";
import AuditShow from "@/Pages/Audit/Show";
import type { AuditDetail, AuditIndexProps } from "@/types/audit";

vi.mock("@inertiajs/react", async (original) => ({
    ...(await original<typeof import("@inertiajs/react")>()), Head: () => null, usePage: () => ({ props: { errors: {} }, flash: {} }),
}));
vi.mock("@/Layouts/AuthenticatedLayout", () => ({
    AuthenticatedLayout: ({ children, headerActions }: { children: ReactNode; headerActions?: ReactNode }) => <main>{headerActions}{children}</main>,
}));
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const kosong = { dari: "", sampai: "", aktor: "", tindakan: "", objek_tipe: "", objek_id: "" };
const props: AuditIndexProps = {
    audit: {
        data: [
            { id: "a1", waktu: "2031-03-16T07:59:00+00:00", tindakan: "renstra.ubah_ditolak", objek_tipe: "renstra", objek_id: "11111111-1111-4111-8111-111111111111",
                actor_type: "user", sumber: "manual", aktor: { nama: "Budi Perencana", email: "budi@lldikti16.test" }, alasan: "Renstra aktif tidak dapat diubah.", ditolak: true },
            { id: "a2", waktu: "2031-03-16T06:00:00+00:00", tindakan: "pengguna.terdaftar", objek_tipe: "users", objek_id: "22222222-2222-4222-8222-222222222222",
                actor_type: "system", sumber: "sso_onboarding", aktor: null, alasan: "Sistem — onboarding SSO", ditolak: false },
        ],
        from: 1, to: 2, total: 2, links: [],
    },
    filters: kosong,
};

it("menampilkan baris audit, penolakan beserta alasannya, dan tautan detail", () => {
    render(<AuditIndex {...props} />);
    const table = screen.getByRole("table");
    expect(within(table).getAllByText("renstra.ubah_ditolak").length).toBeGreaterThan(0);
    expect(within(table).getByText("Ditolak")).toBeTruthy();
    expect(within(table).getByText("Renstra aktif tidak dapat diubah.")).toBeTruthy();
    expect(within(table).getByText("Budi Perencana")).toBeTruthy();
    expect(within(table).getByText("Sistem")).toBeTruthy();
    // Alasan baris yang tidak ditolak tidak diangkat ke daftar.
    expect(within(table).queryByText("Sistem — onboarding SSO")).toBeNull();
    const detail = screen.getByRole("link", { name: "Lihat detail audit renstra.ubah_ditolak" });
    expect(detail.getAttribute("href")).toBe("/audit/a1");
    // Aksi detail berupa tombol ikon seperti tabel lain; nama aksesibel tetap dari aria-label.
    expect(detail.textContent?.trim()).toBe("");
    expect(detail.querySelector("svg")).toBeTruthy();
    expect(within(table).getAllByText("Lihat detail").length).toBe(props.audit.data.length);
    expect(screen.queryByRole("button", { name: /Hapus|Ubah|Simpan/ })).toBeNull();
});

it("filter dikirim lewat GET hanya dengan isian yang terisi", async () => {
    const get = vi.spyOn(router, "get").mockImplementation(() => undefined);
    const user = userEvent.setup();
    render(<AuditIndex {...props} />);
    await user.type(screen.getByLabelText("Aktor"), "budi");
    await user.type(screen.getByLabelText("Tindakan"), "ditolak");
    await user.type(screen.getByLabelText("Jenis objek"), "renstra");
    await user.click(screen.getByRole("button", { name: "Terapkan" }));
    expect(get).toHaveBeenCalledWith("/audit", { aktor: "budi", tindakan: "ditolak", objek_tipe: "renstra" }, expect.objectContaining({ preserveState: true }));
    expect(screen.getByRole("link", { name: "Atur ulang" }).getAttribute("href")).toBe("/audit");
});

it("empty state menjelaskan hasil filter tanpa tabel", () => {
    render(<AuditIndex {...props} audit={{ ...props.audit, data: [], from: null, to: null, total: 0 }} filters={{ ...kosong, tindakan: "tidak-ada" }} />);
    expect(screen.getByRole("heading", { name: "Belum ada catatan audit yang sesuai" })).toBeTruthy();
    expect(screen.queryByRole("table")).toBeNull();
    expect((screen.getByLabelText("Tindakan") as HTMLInputElement).value).toBe("tidak-ada");
});

const detail: AuditDetail = {
    ...props.audit.data[0],
    operator_reference: null,
    runtime_identity: null,
    nilai_lama: { nama: "Renstra Lama", status: "aktif", token_akses: "[disamarkan]" },
    nilai_baru: { nama: "Renstra Baru", status: "aktif", lampiran: [{ nama_asli: "surat.pdf" }] },
    dasar_izin: { permission: "renstra:update", keputusan: "ditolak", sumber_allow: { roles: ["peran-1"], grants: [] }, deny: ["deny-1"] },
};

it("detail menandai data yang berubah dan menampilkan nilai tersamar dari server", () => {
    render(<AuditShow audit={detail} />);
    const table = screen.getByRole("table", { name: "Perbandingan nilai lama dan nilai baru" });
    const rows = within(table).getAllByRole("row").slice(1);
    const baris = (data: string) => rows.find((row) => within(row).queryAllByText(data).length > 0)!;
    expect(within(baris("nama")).getByText("Berubah")).toBeTruthy();
    expect(within(baris("nama")).getByText("Renstra Lama")).toBeTruthy();
    expect(within(baris("nama")).getByText("Renstra Baru")).toBeTruthy();
    expect(within(baris("status")).queryByText("Berubah")).toBeNull();
    expect(within(baris("token_akses")).getByText("[disamarkan]")).toBeTruthy();
    expect(within(baris("token_akses")).getByText("Tidak dicatat")).toBeTruthy();
    expect(within(baris("lampiran")).getByText("surat.pdf")).toBeTruthy();
    expect(screen.getByRole("link", { name: /Kembali ke daftar/ }).getAttribute("href")).toBe("/audit");
});

it("detail menampilkan dasar izin allow dan deny dalam bentuk terbaca", () => {
    render(<AuditShow audit={detail} />);
    const dasar = screen.getByRole("region", { name: "Dasar izin" });
    expect(within(dasar).getByText("renstra:update")).toBeTruthy();
    expect(within(dasar).getByText("ditolak")).toBeTruthy();
    expect(within(dasar).getByText("sumber_allow")).toBeTruthy();
    expect(within(dasar).getByText("peran-1")).toBeTruthy();
    expect(within(dasar).getByText("deny-1")).toBeTruthy();
    expect(screen.getByText("Renstra aktif tidak dapat diubah.")).toBeTruthy();
    expect(screen.getAllByText("Ditolak").length).toBeGreaterThan(0);
});

it("detail tanpa payload dan tanpa dasar izin memberi keterangan, pelaku sistem diberi label", () => {
    render(<AuditShow audit={{ ...props.audit.data[1], operator_reference: null, runtime_identity: "seeder@rilis", nilai_lama: null, nilai_baru: null, dasar_izin: null }} />);
    expect(screen.getByText("Tidak ada perubahan data yang dicatat.")).toBeTruthy();
    expect(screen.getByText("Tidak ada dasar izin yang dicatat untuk tindakan ini.")).toBeTruthy();
    expect(screen.getAllByText("Sistem").length).toBeGreaterThan(0);
    expect(screen.getByText("seeder@rilis")).toBeTruthy();
    expect(screen.queryByRole("table")).toBeNull();
});
