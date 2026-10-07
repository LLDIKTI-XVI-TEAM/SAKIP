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
    labelClassName?: string;
    value: string;
    onChange: (id: string, user?: UserOption | null) => void;
    disabled?: boolean;
    error?: string;
    initialUser?: UserOption | null;
    debounceMs?: number;
    placeholder?: string;
    endpoint?: string;
    required?: boolean;
}

export function GrantUserAutocomplete({
    id = 'grant-user-target',
    label = 'Pengguna Target',
    labelClassName,
    value,
    onChange,
    disabled = false,
    error,
    initialUser = null,
    debounceMs = 350,
    placeholder = 'Cari nama atau email pengguna...',
    endpoint = '/akses/grant/opsi/pengguna',
    required = true,
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
            setPage(1); setHasMore(false); setSearchTerm('');
            setItems([]);
            setIsOpen(false);
            setHighlightedIndex(-1);
        } else if (initialUser && initialUser.id === value) {
            setSelectedUser(initialUser);
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

        let active = true;
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
                    if (!active) return;
                    setItems(Array.isArray(data?.items) ? data.items : []);
                    setHasMore(Boolean(data.hasMore));

                    setIsOpen(true);
                })
                .catch((err: unknown) => {
                    if (active && !(err instanceof DOMException && err.name === 'AbortError')) {
                        setFailure(err instanceof Error ? err.message : 'Gagal memuat pengguna.');
                        setItems([]);
                    }
                })
                .finally(() => {
                    if (active) setIsLoading(false);
                });
        }, debounceMs);

        return () => {
            active = false;
            clearTimeout(timer);
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
                abortControllerRef.current = null;
            }
        };
    }, [searchTerm, debounceMs, endpoint, page]);

    // Opsi keyboard aktif tetap terlihat dalam daftar yang dapat digulir.
    useEffect(() => {
        if (highlightedIndex >= 0) wrapperRef.current?.querySelector<HTMLElement>('[role="option"][aria-selected="true"]')?.scrollIntoView?.({ block: 'nearest' });
    }, [highlightedIndex]);

    const handleSelectUser = useCallback((user: UserOption) => {
        setSelectedUser(user);
        setPage(1); setHasMore(false); setSearchTerm('');
        setItems([]);
        setIsOpen(false);
        setHighlightedIndex(-1);
        onChange(user.id, user);
    }, [onChange]);

    const handleClear = useCallback(() => {
        setSelectedUser(null);
        setPage(1); setHasMore(false); setSearchTerm('');
        setItems([]);
        setIsOpen(false);
        setHighlightedIndex(-1);
        onChange('', null);
        setTimeout(() => {
            inputRef.current?.focus();
        }, 0);
    }, [onChange]);

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (disabled || isLoading) {
            if (e.key === 'Escape') setIsOpen(false);
            if (e.key === 'Enter') e.preventDefault();
            return;
        }
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
        <div className="space-y-1.5" ref={wrapperRef}>
            <label htmlFor={selectedUser ? undefined : id} className={`block text-sm font-medium text-ink ${labelClassName ?? ''}`}>
                {label} {required && <span className="text-danger" aria-hidden="true">*</span>}
            </label>

            {/* Input tersembunyi untuk form data */}
            <input type="hidden" name="user_id" value={value} />

            {selectedUser ? (
                /* State: Pengguna Terpilih */
                <div className="relative flex items-center justify-between p-2.5 bg-soft border border-border rounded-lg focus-within:ring-2 focus-within:ring-primary/20 focus-within:border-primary">
                    <div className="flex items-center gap-2.5 min-w-0">
                        <div className="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                            {selectedUser.nama ? selectedUser.nama.charAt(0).toUpperCase() : 'U'}
                        </div>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-xs font-semibold text-ink truncate">
                                    {selectedUser.nama || selectedUser.name}
                                    {selectedUser.status === "nonaktif" && <span className="ml-2 text-muted">(nonaktif)</span>}
                                </span>
                                {selectedUser.roles && selectedUser.roles.length > 0 ? (
                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-primary/5 text-primary border border-primary/20 shrink-0">
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
                            aria-required={required}
                            aria-controls={`${id}-suggestions`}
                            aria-activedescendant={shouldShowDropdown && !isLoading && !failure && highlightedIndex >= 0 ? `${id}-option-${highlightedIndex}` : undefined}
                            maxLength={100}
                            disabled={disabled}
                            placeholder={placeholder}
                            value={searchTerm}
                            onChange={(e) => {
                                setPage(1);
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
                            className={`w-full h-[42px] rounded-lg border bg-surface pl-9 pr-9 py-2 text-sm text-ink transition-colors placeholder:text-muted focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-soft disabled:text-muted ${error ? 'border-danger focus:border-danger focus:ring-danger/20' : 'border-border focus:border-primary focus:ring-primary/20'}`}
                        />
                        {isLoading ? (
                            <Loader2 className="w-4 h-4 text-primary animate-spin absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        ) : searchTerm ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setPage(1); setHasMore(false); setSearchTerm('');
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
                        <div className="absolute z-30 mt-1 w-full rounded-lg border border-border bg-surface shadow-lg">
                        <div
                            id={`${id}-suggestions`}
                            role="listbox"
                            aria-label="Daftar saran pengguna target"
                            className="max-h-60 overflow-y-auto divide-y divide-border"
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
                                        type="button"
                                        role="option"
                                        tabIndex={-1}
                                        id={`${id}-option-${index}`}
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
                                                {user.status === "nonaktif" && <span className="ml-2 text-muted">(nonaktif)</span>}
                                            </span>
                                            {user.roles && user.roles.length > 0 ? (
                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-primary/5 text-primary border border-primary/20 shrink-0">
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
                        {(page > 1 || (hasMore && !failure)) && <nav aria-label="Halaman pilihan pengguna" className="flex items-center justify-between gap-2 border-t border-border px-3 py-2 text-xs">
                            <span className="text-muted" aria-live="polite">Halaman {page}</span>
                            <div className="flex gap-2">
                                {page > 1 && <button type="button" disabled={isLoading || disabled} onClick={() => setPage(page - 1)} className="rounded px-2 py-1 text-primary hover:bg-soft focus-visible:outline-primary disabled:opacity-50">Pengguna sebelumnya</button>}
                                {hasMore && !failure && <button type="button" disabled={isLoading || disabled} onClick={() => setPage(page + 1)} className="rounded px-2 py-1 text-primary hover:bg-soft focus-visible:outline-primary disabled:opacity-50">Pengguna berikutnya</button>}
                            </div>
                        </nav>}
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
