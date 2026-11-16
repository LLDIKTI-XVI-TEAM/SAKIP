import type { HttpExceptionResponse } from '@inertiajs/core';

export type RecoveryRequest = { effectiveMethod: 'get' | 'post' | 'put' | 'patch' | 'delete'; path: string; mutation: boolean };
export type RecoveryState = { reason: 'authentication_required' | 'csrf_mismatch'; outcome: 'rejected' | 'unknown' | 'not-a-mutation' };
function record(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Status saja tidak membuktikan mutation ditolak sebelum Action. */
export function classifyRecovery(response: HttpExceptionResponse, request?: RecoveryRequest): RecoveryState | null {
    if (response.status !== 401 && response.status !== 419) return null;
    const reason = response.status === 401 ? 'authentication_required' : 'csrf_mismatch';
    if (request?.mutation === false) return { reason, outcome: 'not-a-mutation' };
    let body: unknown = response.data;
    if (typeof body === 'string') {
        try { body = body.length <= 4096 ? JSON.parse(body) : null; }
        catch { body = null; }
    }
    const recovery = record(body) && record(body.recovery) ? body.recovery : null;
    const rejected = recovery && record(recovery.rejected) ? recovery.rejected : null;
    const matches = request?.mutation === true && request.effectiveMethod !== 'get'
        && recovery?.reason === reason && rejected?.before_action === true
        && typeof rejected.method === 'string' && rejected.method === request.effectiveMethod.toUpperCase()
        && typeof rejected.path === 'string' && rejected.path.length <= 2048
        && rejected.path.startsWith('/') && !/[?#]/.test(rejected.path) && rejected.path === request.path;
    return { reason, outcome: matches ? 'rejected' : 'unknown' };
}
