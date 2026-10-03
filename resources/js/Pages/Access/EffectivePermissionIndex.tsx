import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { GrantUserAutocomplete } from "@/Components/Access/GrantUserAutocomplete";
import { Badge } from "@/Components/Badge";
import { Button } from "@/Components/Button";
import { Input } from "@/Components/Input";
import { Select } from "@/Components/Select";
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import { AuthenticatedLayout } from "@/Layouts/AuthenticatedLayout";
import { secondaryButton } from "@/Pages/Auth/AuthShell";
import type { EffectivePermissionIndexProps, EffectivePermissionRow, ExplorerPagination, ExplorerUnit } from "@/types/effective-permission";

function UnitContextPicker({ selected, disabled, error, onChange }: {
    selected: ExplorerUnit | null; disabled: boolean; error?: string; onChange: (id: string | null) => void;
}) {
    const [query, setQuery] = useState("");
    const [search, setSearch] = useState("");
    const [page, setPage] = useState(1);
    const [attempt, setAttempt] = useState(0);
    const [items, setItems] = useState<ExplorerUnit[]>([]);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState("");
    useEffect(() => {
        const controller = new AbortController();
        let active = true;
        setLoading(true);
        setFailure("");
        const params = new URLSearchParams({ q: search, page: String(page) });
        void fetch(`/akses/jelaskan-izin/opsi/unit?${params}`, { headers: { Accept: "application/json" }, signal: controller.signal })
            .then(async (response) => {
                if (!response.ok) throw new Error("Unit belum dapat dimuat. Coba cari kembali.");
                const data: { items: ExplorerUnit[]; hasMore: boolean } = await response.json();
                if (active) { setItems(data.items); setHasMore(data.hasMore); }
            }).catch((reason: unknown) => {
                if (active) { setFailure(reason instanceof Error ? reason.message : "Gagal memuat unit."); setItems([]); }
            }).finally(() => { if (active) setLoading(false); });
        return () => { active = false; controller.abort(); };
    }, [search, page, attempt]);
    const options = selected && !items.some((unit) => unit.id === selected.id) ? [selected, ...items] : items;
    return <div className="grid min-w-0 gap-4 sm:grid-cols-2">
        <div className="min-w-0 space-y-2">
            <Select id="explorer-unit" label="Konteks pemeriksaan" labelClassName="sr-only" value={selected?.id ?? ""} disabled={disabled}
                aria-describedby={error || failure ? "explorer-unit-error" : undefined} aria-invalid={Boolean(error)} onChange={(event) => onChange(event.target.value || null)}>
                <option value="">Global</option>
                {options.map((unit) => <option key={unit.id} value={unit.id}>{unit.nama}{unit.status === "nonaktif" ? " (nonaktif)" : ""}</option>)}
            </Select>
            {(failure || error) && <p id="explorer-unit-error" role="alert" className="text-xs text-danger">{error || failure}</p>}
        </div>
        <div className="min-w-0 space-y-2">
            <form className="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2" onSubmit={(event) => { event.preventDefault(); setSearch(query.trim()); setPage(1); setAttempt((value) => value + 1); }}>
                <Input id="explorer-unit-search" label="Cari unit" labelClassName="sr-only" placeholder="Cari nama unit..." type="search" value={query} maxLength={100} disabled={disabled || loading}
                    onChange={(event) => setQuery(event.target.value)} />
                <Button variant="outline" type="submit" className="h-[42px]" disabled={disabled || loading}>Cari</Button>
            </form>
            <div className={loading || page > 1 || hasMore ? "flex flex-wrap items-center gap-2 text-xs leading-5 text-muted" : "sr-only"} aria-live="polite">
                {loading ? <span>Memuat unit…</span> : <span className="sr-only">{items.length ? `Pilihan unit · halaman ${page}` : "Tidak ada unit yang cocok."}</span>}
                {page > 1 && <Button size="sm" variant="ghost" disabled={disabled || loading} onClick={() => setPage(page - 1)}>Unit sebelumnya</Button>}
                {hasMore && <Button size="sm" variant="ghost" disabled={disabled || loading} onClick={() => setPage(page + 1)}>Unit berikutnya</Button>}
            </div>
        </div>
    </div>;
}

function PermissionTable({ rows, caption }: { rows: EffectivePermissionRow[]; caption: string }) {
    return <Table>
        <TableCaption className="sr-only">{caption}</TableCaption>
        <TableHeader><TableRow><TableHead>Izin</TableHead><TableHead>Scope</TableHead><TableHead>Hasil pemeriksaan</TableHead><TableHead>Sumber dan pembatasan</TableHead></TableRow></TableHeader>
        <TableBody>{rows.map((row) => <TableRow key={row.kode}>
            <TableCell className="min-w-48 align-top whitespace-normal">
                <p className="break-all font-medium">{row.kode}</p>
                <p className="mt-1 max-w-sm text-xs text-muted">{row.keterangan || "Keterangan belum tersedia."}</p>
                {!row.aktif && <p className="mt-2 text-xs text-muted">{row.id ? "Permission nonaktif" : "Metadata permission belum tersedia"}</p>}
            </TableCell>
            <TableCell className="align-top">{row.scope === "unit" ? "Unit" : "Global"}</TableCell>
            <TableCell className="min-w-56 align-top whitespace-normal">
                <Badge variant={row.decision.allowed ? "success" : row.decision.reason === "explicit_deny" ? "danger" : row.decision.reason === "invalid_scope" ? "info" : "muted"}
                    className={row.decision.allowed ? "text-success-dark" : undefined}>{row.status}</Badge>
                <p className="mt-2 max-w-sm text-xs text-muted">{row.explanation}</p>
            </TableCell>
            <TableCell className="min-w-60 align-top whitespace-normal">
                {row.sources.length === 0 ? <span className="text-xs text-muted">Tidak ada sumber dalam konteks ini.</span>
                    : <ul className="space-y-3">{row.sources.map((source) => <li key={source.kind + source.id} className="text-xs">
                        <p className="font-semibold">{source.label}</p>
                        <p className="mt-0.5 text-muted">{source.unit ? `${source.unit.nama}${source.unit.status === "nonaktif" ? " (nonaktif)" : ""}` : "Global"}
                            {" · "}{source.effective ? source.kind === "deny" ? "Pembatasan berlaku" : "Berlaku" : "Tersimpan, tidak efektif"}</p>
                        {source.alasan && <p className="mt-1 break-words text-muted">Alasan: {source.alasan}</p>}
                    </li>)}</ul>}
            </TableCell>
        </TableRow>)}</TableBody>
    </Table>;
}

export default function EffectivePermissionIndex({ selectedUser, selectedUnit, permissions, diagnostics, pagination, diagnosticPagination, filters }: EffectivePermissionIndexProps) {
    const { errors } = usePage().props;
    const [query, setQuery] = useState(filters.q);
    const [navigating, setNavigating] = useState(false);
    const pending = useRef(false);
    useEffect(() => { setQuery(filters.q); }, [filters.q]);
    const navigationEvents = {
        onStart: () => { pending.current = true; setNavigating(true); },
        onFinish: () => { pending.current = false; setNavigating(false); },
    };
    const navigate = (next: Partial<typeof filters>) => {
        if (pending.current) return;
        pending.current = true;
        router.get("/akses/jelaskan-izin", { ...filters, ...next }, { preserveState: false, ...navigationEvents });
    };
    const pager = (value: ExplorerPagination, label: string) => <nav aria-label={label} className="mt-4 flex flex-wrap items-center justify-between gap-3">
        <span className="text-xs text-muted">Halaman {value.page}</span>
        <div className="flex gap-2">{[["Sebelumnya", value.prev_page_url], ["Berikutnya", value.next_page_url]].map(([text, url]) => url && <Link key={text}
            href={url} className={secondaryButton} aria-disabled={navigating} onBefore={() => !pending.current} {...navigationEvents}>{text}</Link>)}</div>
    </nav>;
    const officialRole = selectedUser?.role?.in_catalog && selectedUser.role.aktif;
    return <AuthenticatedLayout title="Izin Pengguna" breadcrumbs={[{ label: "Izin Pengguna" }]}>
        <Head title="Izin Pengguna" />
        <div className="space-y-6" aria-busy={navigating}>
            <section aria-label="Pilihan dan filter pemeriksaan" className="rounded-xl border border-border bg-surface p-4 sm:p-6">
                <div className="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                    <div className="min-w-0 space-y-2">
                        <GrantUserAutocomplete key={filters.user_id ?? "empty"} id="explorer-user" label="Pengguna yang diperiksa" labelClassName="sr-only"
                            endpoint="/akses/jelaskan-izin/opsi/pengguna" required={false} value={filters.user_id ?? ""}
                            initialUser={selectedUser ? { ...selectedUser, roles: selectedUser.role ? [selectedUser.role.nama] : [] } : null}
                            disabled={navigating} error={errors?.user_id}
                            onChange={(id) => navigate({ user_id: id || null, q: "", scope: "" })} />
                        {selectedUser && <div className="space-y-2 pt-2">
                            <div className="flex flex-wrap gap-2"><Badge variant={selectedUser.status === "aktif" ? "info" : "muted"}>Akun {selectedUser.status}</Badge>
                                <Badge variant={officialRole ? "primary" : "muted"}>{selectedUser.role ? `${selectedUser.role.nama}${officialRole ? "" : " · tidak efektif"}` : "Tanpa peran resmi"}</Badge></div>
                            {(!officialRole || selectedUser.status !== "aktif") && <p role="status" className="text-sm text-muted">Pengguna ini tidak memiliki izin efektif. Sumber tersimpan ditampilkan untuk diagnosis.</p>}
                        </div>}
                    </div>
                    <UnitContextPicker selected={selectedUnit} disabled={navigating} error={errors?.unit_id} onChange={(unit_id) => navigate({ unit_id })} />
                </div>
                {selectedUser && <form role="search" className="mt-5 grid items-end gap-3 border-t border-border pt-5 sm:grid-cols-[minmax(0,1fr)_180px_auto]"
                    onSubmit={(event) => { event.preventDefault(); navigate({ q: query }); }}>
                    <div><Input id="explorer-search" name="q" type="search" label="Cari kode atau keterangan izin" value={query} maxLength={100}
                        aria-invalid={Boolean(errors?.q)} aria-describedby={errors?.q ? "explorer-search-error" : undefined}
                        className={errors?.q ? "border-danger" : undefined} disabled={navigating} onChange={(event) => setQuery(event.target.value)} />
                        {errors?.q && <p id="explorer-search-error" role="alert" className="mt-1 text-xs text-danger">{errors.q}</p>}</div>
                    <div><Select id="explorer-scope" label="Scope permission" value={filters.scope} disabled={navigating}
                        aria-invalid={Boolean(errors?.scope)} aria-describedby={errors?.scope ? "explorer-scope-error" : undefined}
                        onChange={(event) => navigate({ scope: event.target.value as typeof filters.scope, q: query })}>
                        <option value="">Semua scope</option><option value="global">Global</option><option value="unit">Unit</option>
                    </Select>{errors?.scope && <p id="explorer-scope-error" role="alert" className="mt-1 text-xs text-danger">{errors.scope}</p>}</div>
                    <Button type="submit" disabled={navigating}>{navigating ? "Memuat…" : "Terapkan filter"}</Button>
                </form>}
            </section>
            <p role="status" className="sr-only">{navigating ? "Memuat hasil pemeriksaan izin…" : ""}</p>
            {Object.entries(errors ?? {}).filter(([field]) => !["user_id", "unit_id", "q", "scope"].includes(field)).map(([field, message]) =>
                <p key={field} role="alert" className="text-sm text-danger">{message}</p>)}
            {selectedUser ? <>
                <section aria-label="Katalog izin efektif" className="rounded-xl border border-border bg-surface p-4 sm:p-6">
                    <div className="mb-4"><h2 className="text-base font-semibold">Hasil pemeriksaan</h2>
                        <p className="mt-1 text-xs text-muted">Konteks: {selectedUnit ? selectedUnit.nama : "Global"}. Scope pada tabel menunjukkan kebutuhan setiap permission.</p></div>
                    {permissions.length ? <PermissionTable rows={permissions} caption={`Izin ${selectedUser.nama} pada konteks ${selectedUnit?.nama ?? "Global"}`} />
                        : <p className="py-8 text-center text-sm text-muted">Tidak ada izin pada halaman ini. Ubah filter atau kembali ke halaman sebelumnya.</p>}
                    {pager(pagination, "Halaman katalog izin")}
                </section>
                {(diagnostics.length > 0 || diagnosticPagination.page > 1) && <section aria-label="Diagnosis di luar katalog" className="rounded-xl border border-border bg-surface p-4 sm:p-6">
                    <h2 className="text-base font-semibold">Sumber di luar katalog rilis</h2>
                    <p className="mb-4 mt-1 text-xs text-muted">Record yang masih tersimpan ini tidak memberikan izin efektif. Riwayat pencabutan tersedia di audit.</p>
                    {diagnostics.length ? <PermissionTable rows={diagnostics} caption="Diagnosis permission di luar katalog rilis" /> : <p className="text-sm text-muted">Tidak ada diagnosis pada halaman ini.</p>}
                    {pager(diagnosticPagination, "Halaman diagnosis")}
                </section>}
            </> : <div className="rounded-xl border border-border bg-surface px-6 py-12 text-center">
                <h2 className="text-base font-semibold">Pilih pengguna untuk memulai</h2>
                <p className="mt-2 text-sm text-muted">Hasil izin dan sumbernya akan tampil setelah pengguna dipilih.</p>
            </div>}
        </div>
    </AuthenticatedLayout>;
}
