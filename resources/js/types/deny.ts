export type UserOption = { id: string; nama: string; email: string; is_active: boolean };
export type UnitOption = { id: string; nama: string; status: 'aktif' | 'nonaktif' };
export type PermissionOption = { id: string; kode: string; keterangan: string | null; butuh_scope: 'global' | 'unit' };
export type OptionPage<T> = { items: T[]; page: number; hasMore: boolean };
export type DenyRow = {
    id: string;
    user: UserOption;
    permission: PermissionOption & { aktif: boolean };
    unit: UnitOption | null;
    alasan: string;
    ditetapkan_oleh: { id: string; nama: string };
    created_at: string;
};
export type DenyIndexProps = {
    denies: DenyRow[];
    pagination: { current_page: number; prev_page_url: string | null; next_page_url: string | null };
    filters: { q: string };
    can: { manageDeny: boolean };
};
export type DenyResultProps = { status: 'created' | 'revoked' | null; canReturn: boolean };
