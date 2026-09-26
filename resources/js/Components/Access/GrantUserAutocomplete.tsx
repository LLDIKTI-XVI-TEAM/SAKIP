import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Search, X, Loader2 } from 'lucide-react';

export interface UserOption {
    id: string;
    nama: string;
    name?: string;
    email: string;
    roles?: string[];
    is_active?: boolean;
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
}: GrantUserAutocompleteProps) {
    const [searchTerm, setSearchTerm] = useState('');
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
        } else if (initialUser && initialUser.id === value && !selectedUser) {
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

        const timer = setTimeout(() => {
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
            const controller = new AbortController();
            abortControllerRef.current = controller;

            const params = new URLSearchParams({
                q: trimmed,
                page: '1',
            });

            void fetch(`/akses/grant/opsi/pengguna?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('Daftar pengguna belum dapat dimuat. Coba cari kembali.');
                    }
                    const data: UserOptionPage = await response.json();
                    setItems(Array.isArray(data?.items) ? data.items : []);
                    setIsOpen(true);
                })
                .catch((err: unknown) => {
                    if (!(err instanceof DOMException && err.name === 'AbortError')) {
                        setFailure(err instanceof Error ? err.message : 'Gagal memuat pengguna.');
                        setItems([]);
                    }
                })
                .finally(() => {
                    setIsLoading(false);
                });
        }, debounceMs);

        return () => {
            clearTimeout(timer);
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
                abortControllerRef.current = null;
            }
        };
    }, [searchTerm, debounceMs]);

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
            setHighlightedIndex((prev) => (prev < items.length - 1 ? prev + 1 : 0));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : items.length - 1));
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
            <label htmlFor={selectedUser ? undefined : id} className="block text-xs font-semibold text-slate-700">
                {label} <span className="text-red-500">*</span>
            </label>

            {/* Input tersembunyi untuk form data */}
            <input type="hidden" name="user_id" value={value} />

            {selectedUser ? (
                /* State: Pengguna Terpilih */
                <div className="relative flex items-center justify-between p-2.5 bg-slate-50 border border-slate-300 rounded-md focus-within:ring-2 focus-within:ring-[#122E92] focus-within:border-[#122E92]">
                    <div className="flex items-center gap-2.5 min-w-0">
                        <div className="w-8 h-8 rounded-full bg-[#122E92]/10 text-[#122E92] flex items-center justify-center font-bold text-xs shrink-0">
                            {selectedUser.nama ? selectedUser.nama.charAt(0).toUpperCase() : 'U'}
                        </div>
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-xs font-semibold text-slate-900 truncate">
                                    {selectedUser.nama || selectedUser.name}
                                </span>
                                {selectedUser.roles && selectedUser.roles.length > 0 ? (
                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-[#122E92] border border-blue-200 shrink-0">
                                        {selectedUser.roles.join(', ')}
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-500 shrink-0">
                                        Tanpa Role
                                    </span>
                                )}
                            </div>
                            <p className="text-[11px] text-slate-500 truncate">{selectedUser.email}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={handleClear}
                        disabled={disabled}
                        title="Ganti pengguna target"
                        aria-label="Ganti pengguna target"
                        className="text-slate-400 hover:text-red-600 p-1.5 rounded-md hover:bg-slate-200 transition-colors shrink-0 disabled:opacity-40"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>
            ) : (
                /* State: Pencarian Autocomplete */
                <div className="relative">
                    <div className="relative">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            ref={inputRef}
                            id={id}
                            type="search"
                            role="combobox"
                            aria-expanded={isOpen}
                            aria-autocomplete="list"
                            aria-controls={`${id}-suggestions`}
                            disabled={disabled}
                            placeholder={placeholder}
                            value={searchTerm}
                            onChange={(e) => {
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
                            className="w-full text-xs rounded-md border-slate-300 pl-9 pr-9 py-2 focus:border-[#122E92] focus:ring-[#122E92]"
                        />
                        {isLoading ? (
                            <Loader2 className="w-4 h-4 text-[#122E92] animate-spin absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        ) : searchTerm ? (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    setItems([]);
                                    setIsOpen(false);
                                    inputRef.current?.focus();
                                }}
                                className="text-slate-400 hover:text-slate-600 absolute right-3 top-1/2 -translate-y-1/2"
                                aria-label="Hapus teks pencarian"
                            >
                                <X className="w-3.5 h-3.5" />
                            </button>
                        ) : null}
                    </div>

                    {/* Dropdown Suggestions */}
                    {shouldShowDropdown && (
                        <div
                            id={`${id}-suggestions`}
                            role="listbox"
                            aria-label="Daftar saran pengguna target"
                            className="absolute z-30 mt-1 w-full bg-white rounded-md border border-slate-200 shadow-lg max-h-60 overflow-y-auto divide-y divide-slate-100"
                        >
                            {isLoading ? (
                                <div className="px-4 py-3 text-xs text-slate-500 flex items-center justify-center gap-2" role="status">
                                    <Loader2 className="w-4 h-4 animate-spin text-[#122E92]" />
                                    <span>Mencari pengguna...</span>
                                </div>
                            ) : failure ? (
                                <div className="px-4 py-3 text-xs text-red-600 text-center" role="alert">
                                    {failure}
                                </div>
                            ) : items.length === 0 ? (
                                <div className="px-4 py-3 text-xs text-slate-500 text-center" role="status">
                                    Pengguna tidak ditemukan
                                </div>
                            ) : (
                                items.map((user, index) => (
                                    <button
                                        key={user.id}
                                        type="button"
                                        role="option"
                                        aria-selected={highlightedIndex === index}
                                        onClick={() => handleSelectUser(user)}
                                        onMouseEnter={() => setHighlightedIndex(index)}
                                        className={`w-full text-left px-3 py-2 transition-colors cursor-pointer ${
                                            highlightedIndex === index ? 'bg-indigo-50/70' : 'hover:bg-slate-50'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-xs font-semibold text-slate-800">
                                                {user.nama || user.name}
                                            </span>
                                            {user.roles && user.roles.length > 0 ? (
                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-[#122E92] border border-blue-200 shrink-0">
                                                    {user.roles.join(', ')}
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-500 shrink-0">
                                                    Tanpa Role
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-[11px] text-slate-500 mt-0.5">{user.email}</p>
                                    </button>
                                ))
                            )}
                        </div>
                    )}
                </div>
            )}

            {error && (
                <p id={`${id}-error`} role="alert" className="text-xs text-red-600 mt-1">
                    {error}
                </p>
            )}
        </div>
    );
}
