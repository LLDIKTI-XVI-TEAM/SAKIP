import { useEffect, useState } from 'react';
import { Search } from 'lucide-react';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';
import { Modal } from '@/Components/Modal';

export function OptionPicker<T extends { id: string; nama: string }>({
    kind,
    onSelect,
    onClose,
    describe,
    selectedIds = [],
}: {
    kind: 'renstra' | 'periode';
    onSelect: (option: T) => void;
    onClose: () => void;
    describe: (option: T) => string;
    selectedIds?: string[];
}) {
    const [query, setQuery] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<{ data: T[]; has_more: boolean }>({ data: [], has_more: false });
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState('');
    const [retry, setRetry] = useState(0);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setFailure('');
        const params = new URLSearchParams({ q: search, page: String(page) });
        void fetch(`/jadwal/opsi/${kind}?${params}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    if (!controller.signal.aborted)
                        setFailure(
                            response.status === 401 || response.status === 419
                                ? 'Sesi perlu dipulihkan. Tutup pilihan dan muat ulang halaman setelah menyalin input.'
                                : 'Pilihan belum dapat dimuat. Coba lagi.',
                        );
                    return;
                }
                const next: { data: T[]; has_more: boolean } = await response.json();
                if (!controller.signal.aborted) setResult(next);
            })
            .catch(() => {
                if (!controller.signal.aborted) setFailure('Pilihan belum dapat dimuat. Coba lagi.');
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [kind, search, page, retry]);

    const title = kind === 'renstra' ? 'Pilih Renstra' : 'Tambah periode';
    return (
        <Modal isOpen onClose={onClose} title={title} size="lg">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    setSearch(query);
                    setPage(1);
                    setRetry((value) => value + 1);
                }}
                className="flex items-end gap-2"
            >
                <Input
                    label={kind === 'renstra' ? 'Cari Renstra' : 'Cari periode'}
                    value={query}
                    maxLength={100}
                    autoComplete="off"
                    onChange={(event) => setQuery(event.target.value)}
                />
                <Button type="submit" variant="outline" aria-label="Cari pilihan">
                    <Search className="h-4 w-4" aria-hidden="true" />
                </Button>
            </form>
            <div className="mt-4 space-y-2" aria-live="polite" aria-busy={loading}>
                {loading ? (
                    <p className="py-4 text-sm text-muted">Memuat pilihan…</p>
                ) : failure ? (
                    <p role="alert" className="text-sm text-danger">
                        {failure}
                    </p>
                ) : result.data.length === 0 ? (
                    <p className="py-4 text-sm text-muted">Tidak ada pilihan yang sesuai.</p>
                ) : (
                    result.data.map((option) => (
                        <button
                            type="button"
                            key={option.id}
                            disabled={selectedIds.includes(option.id)}
                            onClick={() => onSelect(option)}
                            className="block w-full rounded-lg border border-border p-3 text-left text-sm transition-colors hover:bg-soft focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span className="block break-words font-semibold">
                                {option.nama}
                                {selectedIds.includes(option.id) ? ' · Sudah dipilih' : ''}
                            </span>
                            <span className="mt-1 block text-xs text-muted">{describe(option)}</span>
                        </button>
                    ))
                )}
            </div>
            <div className="mt-4 flex items-center justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={loading || page === 1}
                    onClick={() => setPage((value) => value - 1)}
                >
                    Sebelumnya
                </Button>
                <span className="text-xs text-muted">Halaman {page}</span>
                <Button
                    type="button"
                    variant="outline"
                    disabled={loading || Boolean(failure) || !result.has_more}
                    onClick={() => setPage((value) => value + 1)}
                >
                    Berikutnya
                </Button>
            </div>
        </Modal>
    );
}
