import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Search, X, Loader2 } from 'lucide-react';

export interface UserOption {
    id: string;
    nama: string;
    name?: string;
    email: string;
    roles?: string[];
    status?: 'aktif' | 'nonaktif';
}

export interface UserOptionPage {
    items: UserOption[];
    page: number;
    hasMore: boolean;
}

export interface GrantUserAutocompleteProps {
    id?: string;
    label?: string;
    value: string;
    onChange: (id: string, user?: UserOption | null) => void;
    disabled?: boolean;
    error?: string;
    initialUser?: UserOption | null;
    debounceMs?: number;
    placeholder?: string;
    endpoint?: string;
}

export function GrantUserAutocomplete({
    id = 'grant-user-target',
    label = 'Pengguna Target',
    value,
    onChange,
    disabled = false,
    error,
    initialUser = null,
    debounceMs = 350,
    placeholder = 'Cari nama atau email pengguna...',
    endpoint = '/akses/grant/opsi/pengguna',
}: GrantUserAutocompleteProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [items, setItems] = useState<UserOption[]>([]);
    const [selectedUser, setSelectedUser] = useState<UserOption | null>(initialUser);
    const [isLoading, setIsLoading] = useState(false);
    const [failure, setFailure] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);

    const wrapperRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const abortControllerRef = useRef<AbortController | null>(null);

    // Sinkronisasi saat value dikosongkan dari luar (misal: createForm.reset())
    useEffect(() => {
        if (!value) {
            setSelectedUser(null);
            setSearchTerm('');
            setItems([]);
            setIsOpen(false);
            setHighlightedIndex(-1);
        } else if (initialUser && initialUser.id === value) {
            setSelectedUser((current) => current ?? initialUser);
        }
    }, [value, initialUser]);

    // Handle klik di luar komponen untuk menutup dropdown
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    // Pencarian dengan debounce 300-400ms saat input minimal 2 karakter
    useEffect(() => {
        const trimmed = searchTerm.trim();

        // Jangan jalankan request jika kurang dari 2 karakter
        if (trimmed.length < 2) {
            setItems([]);
            setIsLoading(false);
            setFailure('');
            setHighlightedIndex(-1);
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
                abortControllerRef.current = null;
            }
            return;
        }

        setIsLoading(true);
        setFailure('');
        setHighlightedIndex(-1);

        const timer = setTimeout(() => {
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
            const controller = new AbortController();
            abortControllerRef.current = controller;

            const params = new URLSearchParams({
                q: trimmed,
                page: String(page),
            });

            void fetch(`${endpoint}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('Daftar pengguna belum dapat dimuat. Coba cari kembali.');
                    }
                    const data: UserOptionPage = await response.json();
                    if (controller.signal.aborted) return;
                    const incoming = Array.isArray(data?.items) ? data.items : [];
                    setItems((current) => page === 1 ? incoming : [...current, ...incoming.filter((item) => !current.some((existing) => existing.id === item.id))]);
                    setHasMore(data.hasMore === true);
                    setIsOpen(true);
                })
                .catch((err: unknown) => {
                    if (!controller.signal.aborted && !(err instanceof DOMException && err.name === 'AbortError')) {
                        setFailure(err instanceof Error ? err.message : 'Gagal memuat pengguna.');
                        setItems([]);
                    }
                })
                .finally(() => {
                    if (!controller.signal.aborted) setIsLoading(false);
                });
        }, debounceMs);

        return () => {
            clearTimeout(timer);
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
                abortControllerRef.current = null;
            }
        };
    }, [searchTerm, debounceMs, endpoint, page]);

    // Opsi keyboard aktif harus tetap terlihat dalam daftar yang dapat digulir.
    useEffect(() => {
        if (highlightedIndex >= 0) wrapperRef.current?.querySelector<HTMLElement>('[role="option"][aria-selected="true"]')?.scrollIntoView?.({ block: 'nearest' });
    }, [highlightedIndex]);

    const handleSelectUser = useCallback((user: UserOption) => {
        setSelectedUser(user);
        setSearchTerm('');
        setItems([]);
        setIsOpen(false);
        setHighlightedIndex(-1);
        onChange(user.id, user);
    }, [onChange]);

    const handleClear = useCallback(() => {
        setSelectedUser(null);
        setSearchTerm('');
        setItems([]);
        setIsOpen(false);
        setHighlightedIndex(-1);
        onChange('', null);
        setTimeout(() => {
            inputRef.current?.focus();
        }, 0);
    }, [onChange]);

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!isOpen) {
            if (e.key === 'ArrowDown' && (items.length > 0 || searchTerm.trim().length >= 2)) {
                setIsOpen(true);
                e.preventDefault();
            }
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (items.length > 0) {
                setHighlightedIndex((prev) => (prev < items.length - 1 ? prev + 1 : 0));
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length > 0) {
                setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : items.length - 1));
            }
        } else if (e.key === 'Enter') {
            if (highlightedIndex >= 0 && items[highlightedIndex]) {
                e.preventDefault();
                handleSelectUser(items[highlightedIndex]);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            setIsOpen(false);
        }
    };

    const trimmed = searchTerm.trim();
    const shouldShowDropdown = isOpen && (isLoading || Boolean(failure) || trimmed.length >= 2);

    return (
        <div className="space-y-1" ref={wrapperRef}>
            <label htmlFor={selectedUser ? undefined : id} className="block text-xs font-semibold text-ink">
                {label} <span className="text-danger">*</span>
            </label>

            {/* Input tersembunyi untuk form data */}
            <input type="hidden" name="user_id" value={value} />

            {selectedUser ? (
                /* State: Pengguna Terpilih */
                <div className="relative flex items-center justify-between p-2.5 bg-soft border border-border rounded-md focus-within:ring-2 focus-within:ring-primary focus-within:border-primary">
                    <div className="flex items-center gap-2.5 min-w-0">
                        <div className="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                            {selectedUser.nama ? selectedUser.nama.charAt(0).toUpperCase() : 'U'}
                        </div>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-xs font-semibold text-ink truncate">
                                    {selectedUser.nama || selectedUser.name}
                                </span>
                                {selectedUser.roles && selectedUser.roles.length > 0 ? (
                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-primary/10 text-primary border border-primary/20 shrink-0">
                                        {selectedUser.roles.join(', ')}
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-soft text-muted shrink-0">
                                        Tanpa Role
                                    </span>
                                )}
                            </div>
                            <p className="text-[11px] text-muted truncate">{selectedUser.email}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={handleClear}
                        disabled={disabled}
                        title="Ganti pengguna target"
                        aria-label="Ganti pengguna target"
                        className="text-muted hover:text-danger p-1.5 rounded-md hover:bg-soft transition-colors shrink-0 disabled:opacity-40"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>
            ) : (
                /* State: Pencarian Autocomplete */
                <div className="relative">
                    <div className="relative">
                        <Search className="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            ref={inputRef}
                            id={id}
                            type="search"
                            role="combobox"
                            aria-expanded={isOpen}
                            aria-autocomplete="list"
                            aria-required="true"
                            aria-activedescendant={highlightedIndex >= 0 ? `${id}-option-${highlightedIndex}` : undefined}
                            aria-controls={`${id}-suggestions`}
                            disabled={disabled}
                            placeholder={placeholder}
                            value={searchTerm}
                            onChange={(e) => {
                                setPage(1);
                                setHasMore(false);
                                setItems([]);
                                setSearchTerm(e.target.value);
                                setIsOpen(true);
                            }}
                            onFocus={() => {
                                if (searchTerm.trim().length >= 2 || items.length > 0) {
                                    setIsOpen(true);
                                }
                            }}
                            onKeyDown={handleKeyDown}
                            aria-invalid={Boolean(error)}
                            aria-describedby={error ? `${id}-error` : undefined}
                            className="w-full text-xs rounded-md border-border pl-9 pr-9 py-2 focus:border-primary focus:ring-primary"
                        />
                        {isLoading ? (
                            <Loader2 className="w-4 h-4 text-primary animate-spin absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        ) : searchTerm ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    setItems([]);
                                    setIsOpen(false);
                                    inputRef.current?.focus();
                                }}
                                className="text-muted hover:text-ink absolute right-3 top-1/2 -translate-y-1/2"
                                aria-label="Hapus teks pencarian"
                            >
                                <X className="w-3.5 h-3.5" />
                            </button>
                        ) : null}
                    </div>

                    {/* Dropdown Suggestions */}
                    {shouldShowDropdown && (
                        <div className="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-border bg-surface shadow-lg">
                        <div
                            id={`${id}-suggestions`}
                            role="listbox"
                            aria-label="Daftar saran pengguna target"
                            className="divide-y divide-border"
                        >
                            {isLoading ? (
                                <div className="px-4 py-3 text-xs text-muted flex items-center justify-center gap-2" role="status">
                                    <Loader2 className="w-4 h-4 animate-spin text-primary" />
                                    <span>Mencari pengguna...</span>
                                </div>
                            ) : failure ? (
                                <div className="px-4 py-3 text-xs text-danger text-center" role="alert">
                                    {failure}
                                </div>
                            ) : items.length === 0 ? (
                                <div className="px-4 py-3 text-xs text-muted text-center" role="status">
                                    Pengguna tidak ditemukan
                                </div>
                            ) : (
                                items.map((user, index) => (
                                    <button
                                        key={user.id}
                                        id={`${id}-option-${index}`}
                                        type="button"
                                        role="option"
                                        tabIndex={-1}
                                        aria-selected={highlightedIndex === index}
                                        onClick={() => handleSelectUser(user)}
                                        onMouseEnter={() => setHighlightedIndex(index)}
                                        className={`w-full text-left px-3 py-2 transition-colors cursor-pointer ${
                                            highlightedIndex === index ? 'bg-primary/10' : 'hover:bg-soft'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs font-semibold text-ink">
                                                {user.nama || user.name}
                                            </span>
                                            {user.roles && user.roles.length > 0 ? (
                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-primary/10 text-primary border border-primary/20 shrink-0">
                                                    {user.roles.join(', ')}
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-soft text-muted shrink-0">
                                                    Tanpa Role
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-[11px] text-muted mt-0.5">{user.email}</p>
                                    </button>
                                ))
                            )}
                        </div>
                        {hasMore && !isLoading && !failure && <button type="button" onClick={() => setPage((current) => current + 1)} className="w-full border-t border-border px-3 py-3 text-left text-sm font-medium text-primary hover:bg-soft">Muat pengguna berikutnya</button>}
                        </div>
                    )}
                </div>
            )}

            {error && (
                <p id={`${id}-error`} role="alert" className="text-xs text-danger mt-1">
                    {error}
                </p>
            )}
        </div>
    );
}
