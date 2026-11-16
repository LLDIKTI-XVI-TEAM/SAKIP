import { useCallback, useState } from 'react';
import type { HttpExceptionResponse } from '@inertiajs/core';
import { classifyRecovery, type RecoveryRequest, type RecoveryState } from '@/lib/authRecovery';

export function useAuthRecovery() {
    const [recovery, setRecovery] = useState<RecoveryState | null>(null);
    const handleHttpException = useCallback((response: HttpExceptionResponse, request?: RecoveryRequest): boolean => {
        const next = classifyRecovery(response, request);
        if (!next) return false;
        setRecovery(next);
        return true;
    }, []);
    return { recovery, handleHttpException };
}
