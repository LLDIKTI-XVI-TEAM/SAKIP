import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { CustomSelect } from '@/Components/CustomSelect';
import { EmptyState } from '@/Components/EmptyState';
import { Input } from '@/Components/Input';
import { Pagination } from '@/Components/Pagination';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/Components/Table';
import { AuthenticatedLayout } from '@/Layouts/AuthenticatedLayout';
import { KeyRound, ShieldCheck } from 'lucide-react';
import type { RolePermissionIndexProps } from '@/types/role-permission';

export default function RolePermissionIndex({
    roles,
    selectedRole,
    permissions,
    pagination,
    filters,
}: RolePermissionIndexProps) {
    const { errors } = usePage().props;
    const [query, setQuery] = useState(filters.q);
    const [navigating, setNavigating] = useState(false);
    const pending = useRef(false);

    useEffect(() => {
        setQuery(filters.q);
    }, [filters.q]);

    const navigationEvents = {
        onStart: () => {
            pending.current = true;
            setNavigating(true);
        },
        onFinish: () => {
            pending.current = false;
            setNavigating(false);
        },
    };

    const navigate = (next: Partial<typeof filters>) => {
        if (pending.current) return;
        router.get(
            '/akses/izin-peran',
            { ...filters, ...next },
            { preserveState: false, ...navigationEvents },
        );
    };

    return (
        <AuthenticatedLayout
            title="Peran & Izin"
            breadcrumbs={[
                { label: 'Manajemen Akses' },
                { label: 'Peran & Izin' },
            ]}
        >
            <Head title="Peran & Izin" />
            <h2 className="sr-only">Peran & Izin</h2>
            <div className="space-y-6">
                <Card className="overflow-visible border-border bg-surface p-5 sm:p-6 shadow-xs">
                    {/* Unified Banner with Integrated Role Selector */}
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/80 bg-soft/50 p-4 relative z-10">
                        <div className="flex items-center gap-3 min-w-0 flex-1">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                                <ShieldCheck className="h-5 w-5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2.5">
                                    <div className="w-56 min-w-[200px]">
                                        <CustomSelect
                                            id="role-permission-role"
                                            name="role"
                                            aria-label="Peran"
                                            nativeSelect={true}
                                            showEmptyOption={true}
                                            emptyOptionLabel="Pilih peran"
                                            placeholder="Pilih peran"
                                            value={filters.role ?? ''}
                                            disabled={navigating}
                                            onChange={(val) =>
                                                navigate({ role: String(val) || null, q: '' })
                                            }
                                            options={roles.map((role) => ({
                                                value: role.id,
                                                label: `${role.nama}${role.aktif ? '' : ' (nonaktif)'}`,
                                            }))}
                                            className="h-9 text-xs font-semibold"
                                        />
                                    </div>
                                    {selectedRole && (
                                        <Badge
                                            variant={selectedRole.aktif ? 'primary' : 'muted'}
                                            size="sm"
                                            dot
                                        >
                                            {selectedRole.aktif ? 'Peran aktif' : 'Peran nonaktif'}
                                        </Badge>
                                    )}
                                </div>
                                {selectedRole ? (
                                    <p className="text-xs text-muted mt-1">
                                        Kode peran: <span className="font-mono font-medium text-ink">{selectedRole.kode}</span>
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted mt-1">
                                        Pilih peran untuk menampilkan izin bawaannya
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    {!selectedRole ? (
                        <div className="mt-6 border-t border-border pt-6">
                            <EmptyState
                                icon={KeyRound}
                                title="Pilih peran untuk melihat izin bawaannya."
                                description="Gunakan pemilih peran di atas untuk meninjau daftar hak akses yang telah dikonfigurasi."
                                variant="dashed"
                            />
                        </div>
                    ) : (
                        <div className="mt-5 space-y-5">
                            <form
                                role="search"
                                className="flex flex-wrap items-end gap-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    navigate({ q: query });
                                }}
                            >
                                <div className="min-w-0 flex-1">
                                    <Input
                                        id="role-permission-search"
                                        name="q"
                                        type="search"
                                        label="Cari kode atau keterangan izin"
                                        value={query}
                                        maxLength={100}
                                        autoComplete="off"
                                        spellCheck={false}
                                        disabled={navigating}
                                        error={errors?.q}
                                        aria-invalid={Boolean(errors?.q)}
                                        onChange={(event) => setQuery(event.target.value)}
                                        placeholder="Ketik kode izin (mis: pengukuran:read)..."
                                    />
                                </div>
                                <Button type="submit" disabled={navigating}>
                                    {navigating ? 'Mencari…' : 'Cari'}
                                </Button>
                                {filters.q && (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={navigating}
                                        onClick={() => navigate({ q: '' })}
                                    >
                                        Reset
                                    </Button>
                                )}
                            </form>

                            <p role="status" className="sr-only">
                                {navigating ? 'Memuat izin…' : ''}
                            </p>

                            {permissions.length === 0 ? (
                                <div className="p-8">
                                    <EmptyState
                                        icon={KeyRound}
                                        title="Tidak ada izin yang cocok dengan pencarian."
                                        description="Coba cari dengan kata kunci lain atau kosongkan kolom pencarian."
                                        variant="inline"
                                    />
                                </div>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border border-border">
                                    <Table>
                                        <caption className="sr-only">
                                            Izin tersimpan untuk {selectedRole.nama}
                                        </caption>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="min-w-[280px] whitespace-nowrap">IZIN & KETERANGAN</TableHead>
                                                <TableHead className="w-32 whitespace-nowrap">SCOPE</TableHead>
                                                <TableHead className="w-32 whitespace-nowrap">STATUS</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {permissions.map((permission) => (
                                                <TableRow key={permission.id} className="transition-colors hover:bg-soft/40">
                                                    <TableCell className="py-3.5">
                                                        <div className="space-y-1">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="font-mono text-xs font-semibold text-ink bg-soft px-2 py-0.5 rounded border border-border">
                                                                    {permission.kode}
                                                                </span>
                                                                {!permission.in_catalog && (
                                                                    <Badge variant="warning" size="sm">
                                                                        Di luar katalog rilis
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            <p className="max-w-xl text-xs text-muted leading-relaxed break-words">
                                                                {permission.keterangan || '—'}
                                                            </p>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="align-top py-3.5">
                                                        <span className="inline-flex items-center rounded-md border border-border bg-soft px-2.5 py-0.5 text-xs font-medium text-ink">
                                                            {permission.butuh_scope === 'unit' ? 'Unit' : 'Global'}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="align-top py-3.5">
                                                        <Badge
                                                            variant={permission.aktif ? 'success' : 'muted'}
                                                            size="sm"
                                                            dot
                                                            className={permission.aktif ? 'text-success-dark' : undefined}
                                                        >
                                                            {permission.aktif ? 'Aktif' : 'Nonaktif'}
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}

                            {permissions.length > 0 && (
                                <Pagination
                                    currentPage={pagination.page}
                                    perPage={pagination.per_page ?? 20}
                                    total={pagination.total ?? permissions.length}
                                    lastPage={pagination.last_page}
                                    from={pagination.from}
                                    to={pagination.to}
                                    showPerPage={true}
                                    prevUrl={pagination.prev_page_url}
                                    nextUrl={pagination.next_page_url}
                                    prevLabel="Sebelumnya"
                                    nextLabel="Berikutnya"
                                    resourceName="izin"
                                    onPageChange={(page) => {
                                        navigate({ page } as any);
                                    }}
                                    onPerPageChange={(newPerPage) => {
                                        navigate({ per_page: newPerPage, page: 1 } as any);
                                    }}
                                />
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
