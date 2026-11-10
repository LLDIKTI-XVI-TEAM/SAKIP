import { Badge } from '@/Components/Badge';
import { BerkasLampiranItem } from '@/Components/BerkasLampiranItem';
import { labelModeBukti, type Pengukuran } from './types';

const daftarMode = (modes: string[]) => modes.map((mode) => labelModeBukti[mode] ?? mode).join(', ');

export default function EvidenceList({ pengukuran }: { pengukuran: Pengukuran }) {
    if (!pengukuran.can.evidence) return <p className="text-sm text-muted">Anda tidak memiliki akses untuk melihat bukti dukung pengukuran ini.</p>;
    return <div className="space-y-4">
        {pengukuran.persyaratan_bukti.length > 0 && <ul className="space-y-2 text-sm" aria-label="Pemenuhan persyaratan bukti">
            {pengukuran.persyaratan_bukti.map((item) => <li key={item.id} className="flex flex-col gap-2 rounded-lg border border-border p-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                <div className="min-w-0">
                    <p className="break-words font-medium text-ink">{item.nama}</p>
                    <p className="mt-1 text-xs text-muted">{item.semua_mode_wajib ? 'Seluruh mode yang tersedia diperlukan' : 'Cukup salah satu mode yang diizinkan'}</p>
                    {item.pemenuhan.mode_kurang.length > 0 && <p className="mt-1 text-xs text-muted">Kurang: {daftarMode(item.pemenuhan.mode_kurang)}</p>}
                    {item.pemenuhan.mode_dikecualikan.length > 0 && <p className="mt-1 text-xs text-muted">Dikecualikan: {daftarMode(item.pemenuhan.mode_dikecualikan)}. {item.pemenuhan.alasan_pengecualian} Pengecualian bukan bukti terunggah.</p>}
                </div>
                <div className="flex shrink-0 flex-wrap gap-1.5">
                    <Badge variant={item.wajib ? 'danger' : 'muted'}>{item.wajib ? 'Wajib' : 'Opsional'}</Badge>
                    <Badge variant={item.pemenuhan.terpenuhi ? 'success' : 'warning'}>{item.pemenuhan.terpenuhi ? 'Terpenuhi' : 'Belum terpenuhi'}</Badge>
                </div>
            </li>)}
        </ul>}
        {pengukuran.bukti_dukungs.length === 0 ? <p className="text-sm text-muted">Belum ada bukti dukung tersimpan.</p> : <ul className="space-y-2" aria-label="Bukti dukung tersimpan">
            {pengukuran.bukti_dukungs.map((item) => {
                const persyaratan = pengukuran.persyaratan_bukti.find((requirement) => requirement.id === item.jenis_berkas_id)?.nama || 'Lampiran tambahan';
                // Bukti tanpa nama berkas (tautan/teks) memakai nama persyaratan sebagai judul, jadi persyaratan tidak diulang di metadata.
                const meta = [item.nama_asli ? persyaratan : null, item.menggantikan_id ? `Bukti koreksi · ${item.alasan_koreksi}` : null].filter(Boolean).join(' · ');
                return <BerkasLampiranItem key={item.id} berkas={{ ...item, nama_asli: item.nama_asli || persyaratan }} meta={meta || undefined} />;
            })}
        </ul>}
    </div>;
}
