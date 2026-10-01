import { useState, useRef, useEffect } from 'react';
import { Bell, Check, CheckCheck, Clock, Inbox } from 'lucide-react';

export interface NotificationItem {
    id: string;
    title: string;
    body: string;
    time: string;
    is_read: boolean;
    url?: string;
}

const DEFAULT_NOTIFICATIONS: NotificationItem[] = [
    {
        id: 'notif-1',
        title: 'Periode Pelaporan Kinerja Aktif',
        body: 'Jadwal pengisian capaian indikator triwulan aktif telah dibuka. Silakan tinjau lembar kerja pengukuran Anda.',
        time: 'Baru saja',
        is_read: false,
        url: '/pengukuran',
    },
    {
        id: 'notif-2',
        title: 'Pengesahan Capaian Kinerja',
        body: 'Data capaian indikator telah diverifikasi dan disahkan oleh Tim Perencanaan LLDIKTI Wilayah XVI.',
        time: '1 hari yang lalu',
        is_read: false,
        url: '/verifikasi',
    },
];

export function NotificationDropdown() {
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState<NotificationItem[]>(() => {
        if (typeof window !== 'undefined') {
            try {
                const saved = localStorage.getItem('sakip_notifications');
                if (saved) return JSON.parse(saved);
            } catch {
                // Ignore storage error
            }
        }
        return DEFAULT_NOTIFICATIONS;
    });

    const dropdownRef = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);

    const unreadCount = notifications.filter((n) => !n.is_read).length;

    const saveNotifications = (items: NotificationItem[]) => {
        setNotifications(items);
        if (typeof window !== 'undefined') {
            try {
                localStorage.setItem('sakip_notifications', JSON.stringify(items));
            } catch {
                // Ignore storage error
            }
        }
    };

    const markAsRead = (id: string, e?: React.MouseEvent) => {
        if (e) e.stopPropagation();
        const updated = notifications.map((n) => (n.id === id ? { ...n, is_read: true } : n));
        saveNotifications(updated);
    };

    const markAllAsRead = () => {
        const updated = notifications.map((n) => ({ ...n, is_read: true }));
        saveNotifications(updated);
    };

    useEffect(() => {
        if (!isOpen) return;

        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Node;
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(target) &&
                buttonRef.current &&
                !buttonRef.current.contains(target)
            ) {
                setIsOpen(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
                buttonRef.current?.focus();
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen]);

    return (
        <div className="relative">
            <button
                ref={buttonRef}
                id="notif-btn"
                type="button"
                aria-expanded={isOpen}
                aria-haspopup="menu"
                aria-label={`Notifikasi${unreadCount > 0 ? `, ${unreadCount} belum dibaca` : ''}`}
                onClick={() => setIsOpen((prev) => !prev)}
                className="relative inline-flex min-h-[40px] min-w-[40px] items-center justify-center rounded-xl p-2 text-muted transition-colors hover:bg-soft hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer touch-manipulation"
            >
                <Bell className="h-5 w-5" aria-hidden="true" />
                {unreadCount > 0 && (
                    <span className="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white leading-none shadow-xs animate-in zoom-in-75 duration-150">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div
                    ref={dropdownRef}
                    role="region"
                    aria-label="Daftar Notifikasi"
                    className="absolute right-0 top-full mt-2 w-80 sm:w-96 origin-top-right overflow-hidden rounded-2xl border border-border bg-surface shadow-xl z-50 animate-in fade-in zoom-in-95 duration-100"
                >
                    {/* Header */}
                    <div className="flex items-center justify-between border-b border-border px-4 py-3 bg-soft/40">
                        <div className="flex items-center gap-2">
                            <h3 className="text-sm font-bold text-ink">Notifikasi</h3>
                            {unreadCount > 0 && (
                                <span className="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">
                                    {unreadCount} baru
                                </span>
                            )}
                        </div>
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={markAllAsRead}
                                className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline cursor-pointer"
                            >
                                <CheckCheck className="h-3.5 w-3.5" aria-hidden="true" />
                                <span>Tandai semua dibaca</span>
                            </button>
                        )}
                    </div>

                    {/* Notification List */}
                    <div className="max-h-96 divide-y divide-border/60 overflow-y-auto">
                        {notifications.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-10 px-4 text-center">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-soft text-muted mb-2 border border-border">
                                    <Inbox className="h-5 w-5 text-muted" aria-hidden="true" />
                                </div>
                                <p className="text-xs font-semibold text-ink">Belum ada notifikasi</p>
                                <p className="text-[11px] text-muted mt-0.5">Pemberitahuan terkait alur kerja kinerja akan tampil di sini.</p>
                            </div>
                        ) : (
                            notifications.map((item) => (
                                <div
                                    key={item.id}
                                    onClick={() => markAsRead(item.id)}
                                    className={`group flex items-start justify-between gap-3 p-3.5 text-left transition-colors hover:bg-soft/50 cursor-pointer ${
                                        item.is_read ? 'opacity-70 bg-surface' : 'bg-primary/5'
                                    }`}
                                >
                                    <div className="flex items-start gap-2.5 min-w-0 flex-1">
                                        <span
                                            className={`mt-1.5 h-2 w-2 shrink-0 rounded-full transition-colors ${
                                                item.is_read ? 'bg-border' : 'bg-primary'
                                            }`}
                                            aria-hidden="true"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p
                                                className={`text-xs leading-snug truncate ${
                                                    item.is_read ? 'font-medium text-ink' : 'font-bold text-ink'
                                                }`}
                                            >
                                                {item.title}
                                            </p>
                                            <p className="mt-0.5 text-[11px] text-muted line-clamp-2 leading-relaxed">
                                                {item.body}
                                            </p>
                                            <div className="mt-1.5 flex items-center gap-1 text-[10px] text-muted">
                                                <Clock className="h-3 w-3" aria-hidden="true" />
                                                <span>{item.time}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {!item.is_read && (
                                        <button
                                            type="button"
                                            onClick={(e) => markAsRead(item.id, e)}
                                            className="shrink-0 rounded-md p-1 text-muted hover:bg-surface hover:text-primary transition-colors mt-0.5"
                                            title="Tandai sudah dibaca"
                                            aria-label="Tandai sudah dibaca"
                                        >
                                            <Check className="h-3.5 w-3.5" aria-hidden="true" />
                                        </button>
                                    )}
                                </div>
                            ))
                        )}
                    </div>

                    {/* Footer */}
                    <div className="border-t border-border px-4 py-2.5 text-center bg-soft/20">
                        <span className="text-[11px] font-medium text-muted">
                            Semua notifikasi tersinkronisasi
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}
