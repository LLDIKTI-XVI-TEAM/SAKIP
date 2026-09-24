import React from 'react';
import { Layers, Plus, Edit2, Trash2 } from 'lucide-react';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/Components/Table';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';

export interface KomponenItem {
    id: string;
    indikator_id: string;
    kode: string;
    label: string;
    peran: 'pembilang' | 'penyebut' | 'penjumlah';
    bobot: number;
    urutan: number;
    satuan: string | null;
    aktif: boolean;
    created_at?: string;
    updated_at?: string;
}

interface KomponenTableProps {
    komponen: KomponenItem[];
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
    hasFilterOrSearch: boolean;
    onOpenCreate: () => void;
    onOpenEdit: (item: KomponenItem) => void;
    onOpenDelete: (item: KomponenItem) => void;
}

export function KomponenTable({
    komponen = [],
    can,
    hasFilterOrSearch,
    onOpenCreate,
    onOpenEdit,
    onOpenDelete,
}: KomponenTableProps) {
    const getPeranBadge = (peran: string) => {
        switch (peran) {
            case 'pembilang':
                return <Badge variant="info" size="sm">Pembilang</Badge>;
            case 'penyebut':
                return <Badge variant="warning" size="sm">Penyebut</Badge>;
            case 'penjumlah':
                return <Badge variant="success" size="sm">Penjumlah (+)</Badge>;
            default:
                return <Badge variant="muted" size="sm">{peran}</Badge>;
        }
    };

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow className="bg-soft/50">
                        <TableHead className="w-12 text-center">#</TableHead>
                        <TableHead className="w-28">Kode</TableHead>
                        <TableHead>Label Komponen</TableHead>
                        <TableHead className="w-32">Peran</TableHead>
                        <TableHead className="w-24 text-right">Bobot</TableHead>
                        <TableHead className="w-24">Satuan</TableHead>
                        <TableHead className="w-24 text-center">Status</TableHead>
                        {(can.update || can.delete) && (
                            <TableHead className="w-28 text-right pr-4">Aksi</TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {komponen.length === 0 ? (
                        <TableRow>
                            <TableCell
                                colSpan={can.update || can.delete ? 8 : 7}
                                className="py-12 text-center"
                            >
                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-soft text-muted">
                                    <Layers className="h-6 w-6" aria-hidden="true" />
                                </div>
                                <h3 className="mt-3 text-sm font-semibold text-ink">
                                    Tidak ada komponen ditemukan
                                </h3>
                                <p className="mt-1 text-xs text-muted max-w-sm mx-auto">
                                    {hasFilterOrSearch
                                        ? 'Tidak ada komponen yang cocok dengan kriteria pencarian atau filter.'
                                        : 'Indikator ini belum memiliki komponen angka terdefinisi. Tambahkan komponen untuk memulai.'}
                                </p>
                                {can.create && !hasFilterOrSearch && (
                                    <div className="mt-4">
                                        <Button
                                            variant="primary"
                                            size="sm"
                                            onClick={onOpenCreate}
                                        >
                                            <Plus className="h-4 w-4 mr-1.5" />
                                            Tambah Komponen Pertama
                                        </Button>
                                    </div>
                                )}
                            </TableCell>
                        </TableRow>
                    ) : (
                        komponen.map((item) => (
                            <TableRow 
                                key={item.id}
                                className="hover:bg-soft/40 transition-colors"
                            >
                                <TableCell className="text-center font-mono text-xs text-muted">
                                    {item.urutan}
                                </TableCell>
                                <TableCell>
                                    <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-soft text-ink border border-border">
                                        {item.kode}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span className="text-sm font-medium text-ink block">
                                        {item.label}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {getPeranBadge(item.peran)}
                                </TableCell>
                                <TableCell className="text-right font-mono text-sm font-medium text-ink">
                                    {Number(item.bobot).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 4 })}
                                </TableCell>
                                <TableCell className="text-xs text-muted">
                                    {item.satuan || '-'}
                                </TableCell>
                                <TableCell className="text-center">
                                    {item.aktif ? (
                                        <span className="inline-flex items-center gap-1 text-xs font-medium text-success">
                                            <span className="h-1.5 w-1.5 rounded-full bg-success" />
                                            Aktif
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 text-xs font-medium text-muted">
                                            <span className="h-1.5 w-1.5 rounded-full bg-muted" />
                                            Nonaktif
                                        </span>
                                    )}
                                </TableCell>
                                {(can.update || can.delete) && (
                                    <TableCell className="text-right pr-4">
                                        <div className="flex items-center justify-end gap-1">
                                            {can.update && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => onOpenEdit(item)}
                                                    title="Ubah Komponen"
                                                    className="h-8 w-8 p-0"
                                                >
                                                    <Edit2 className="h-3.5 w-3.5 text-muted hover:text-ink" />
                                                </Button>
                                            )}
                                            {can.delete && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => onOpenDelete(item)}
                                                    title="Hapus Komponen"
                                                    className="h-8 w-8 p-0 hover:bg-danger/10 hover:text-danger"
                                                >
                                                    <Trash2 className="h-3.5 w-3.5 text-muted hover:text-danger" />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
