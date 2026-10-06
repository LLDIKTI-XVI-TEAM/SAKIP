import { useState } from 'react';
import type { Page, HttpExceptionResponse } from '@inertiajs/core';
import { useAuthRecovery } from '@/hooks/useAuthRecovery';
import { mutationOutcome } from './definition';

/** Hasil operasi dicocokkan sebelum form ditutup atau baseline diubah. */
export function useDefinitionFeedback() {
    const recovery = useAuthRecovery();
    const [failure, setFailure] = useState('');
    const [notice, setNotice] = useState('');
    const unknown = () => setFailure('Hasil penyimpanan belum terkonfirmasi. Periksa data terbaru sebelum menyimpan kembali.');
    const options = (requestId: string, indikatorId: string | undefined, path: string, method: 'post' | 'put' | 'patch', saved: () => void) => ({
        preserveScroll: true,
        onSuccess: (page: Page) => {
            const outcome = mutationOutcome(page.flash.indikatorMutation, requestId, indikatorId);
            if (!outcome) { unknown(); return; }
            if (outcome.status === 'unchanged') setNotice('Tidak ada perubahan.');
            else saved();
        },
        onHttpException: (response: HttpExceptionResponse) => {
            if (!recovery.handleHttpException(response, { effectiveMethod: method, path, mutation: true })) unknown();
            return false;
        },
        onNetworkError: () => { unknown(); return false; },
        onCancel: unknown,
    });
    return { ...recovery, failure, notice, blocked: Boolean(failure || recovery.recovery), options,
        clearNotice: () => setNotice('') };
}
