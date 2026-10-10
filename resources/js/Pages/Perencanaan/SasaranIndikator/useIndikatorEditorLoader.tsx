import React, { useEffect, useRef, useState } from 'react';
import { HttpResponseError } from '@inertiajs/core';
import { Modal } from '@/Components/Modal';
import { AuthRecoveryNotice } from '@/Components/Auth/AuthRecoveryNotice';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { loadDefinition, hasParentMetadata, type DefinitionEditor } from '@/Pages/Indikator/Komponen/definition';
import type { IndikatorKinerjaItem } from '@/types/sasaran-indikator';

/** Editor yang sudah dipastikan memuat metadata induk indikator. */
export type LoadedIndikatorEditor = DefinitionEditor & { indikator: IndikatorKinerjaItem };

/**
 * Memuat aggregate editor indikator (metadata + komponen pada revisi yang sama) dari server
 * sebelum modal ubah/formula dibuka. Permintaan lama dibatalkan agar respons usang tidak menimpa pilihan baru.
 */
export function useIndikatorEditorLoader() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const recovery = useAuthRecovery();
    const request = useRef<AbortController | null>(null);
    useEffect(() => () => request.current?.abort(), []);

    const load = async (indikatorId: string): Promise<LoadedIndikatorEditor | null> => {
        request.current?.abort();
        const controller = new AbortController();
        request.current = controller;
        const path = `/perencanaan/indikator/${indikatorId}/editor`;
        setLoading(true);
        setError('');
        try {
            const editor = await loadDefinition(path, controller.signal);
            if (controller.signal.aborted) return null;
            if (!hasParentMetadata(editor) || editor.indikator.id !== indikatorId) throw new Error('Metadata editor tidak lengkap.');
            return editor;
        } catch (failure) {
            if (!controller.signal.aborted) {
                if (failure instanceof HttpResponseError) recovery.handleHttpException(failure.response, { effectiveMethod: 'get', path, mutation: false });
                setError('Editor tidak dapat dimuat. Periksa koneksi dan akses, lalu buka kembali indikator.');
            }
            return null;
        } finally {
            if (!controller.signal.aborted) setLoading(false);
        }
    };

    const cancel = () => {
        request.current?.abort();
        setLoading(false);
        setError('');
    };

    const loaderModal = (
        <Modal isOpen={loading || Boolean(error)} onClose={cancel} title="Memuat Editor Indikator">
            <AuthRecoveryNotice recovery={recovery.recovery} />
            <p role={error ? 'alert' : 'status'}>{error || 'Memuat metadata dan seluruh komponen pada revisi yang sama…'}</p>
        </Modal>
    );

    return { load, cancel, loaderModal };
}
