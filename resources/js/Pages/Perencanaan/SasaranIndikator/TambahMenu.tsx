import React from 'react';
import { Plus } from 'lucide-react';
import { ActionMenu } from '@/Components/ActionMenu';

interface TambahMenuProps {
    showSasaran: boolean;
    showIndikator: boolean;
    onAddSasaran: () => void;
    onAddIndikator: () => void;
}

/**
 * Tombol gabungan "Tambah" untuk header Sasaran & Indikator.
 * Gate capability dihitung pemanggil dari props `can.*` server;
 * bila kedua item tidak memenuhi syarat, tombol tidak tampil sama sekali.
 */
export function TambahMenu({
    showSasaran,
    showIndikator,
    onAddSasaran,
    onAddIndikator,
}: TambahMenuProps) {
    return (
        <ActionMenu
            variant="primary"
            triggerClassName="h-[42px] gap-2 px-4 text-sm"
            trigger={<><Plus className="h-4 w-4" aria-hidden="true" />Tambah</>}
            items={[
                ...(showSasaran ? [{ key: 'sasaran', label: 'Tambah Sasaran', onSelect: onAddSasaran }] : []),
                ...(showIndikator ? [{ key: 'indikator', label: 'Tambah Indikator', onSelect: onAddIndikator }] : []),
            ]}
        />
    );
}
