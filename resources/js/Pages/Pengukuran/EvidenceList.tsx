import type { Pengukuran } from './types';

export default function EvidenceList({ pengukuran }: { pengukuran: Pengukuran }) {
    if (!pengukuran.can.evidence) return <p className="text-sm text-muted">Anda tidak memiliki akses untuk melihat bukti dukung pengukuran ini.</p>;
    return <div className="space-y-4">
        {pengukuran.persyaratan_bukti.length > 0 && <ul className="space-y-2 text-sm" aria-label="Pemenuhan persyaratan bukti">
            {pengukuran.persyaratan_bukti.map((item) => <li key={item.id} className="rounded-lg border border-border p-3">
                <p className="font-medium text-ink">{item.nama} <span className="text-xs text-muted">({item.wajib ? 'Wajib' : 'Opsional'})</span></p>
                <p className="mt-1 text-muted">{item.pemenuhan.terpenuhi ? 'Terpenuhi' : 'Belum terpenuhi'} · {item.semua_mode_wajib ? 'Seluruh mode yang tersedia diperlukan' : 'Cukup salah satu mode yang diizinkan'}</p>
                {item.pemenuhan.mode_kurang.length > 0 && <p className="mt-1 text-muted">Mode belum terisi: {item.pemenuhan.mode_kurang.join(', ')}</p>}
                {item.pemenuhan.mode_dikecualikan.length > 0 && <p className="mt-1 text-muted">Dikecualikan: {item.pemenuhan.mode_dikecualikan.join(', ')}. {item.pemenuhan.alasan_pengecualian} Pengecualian bukan bukti terunggah.</p>}
            </li>)}
        </ul>}
        {pengukuran.bukti_dukungs.length === 0 ? <p className="text-sm text-muted">Belum ada bukti dukung tersimpan.</p> : <ul className="divide-y divide-border rounded-lg border border-border" aria-label="Bukti dukung tersimpan">
            {pengukuran.bukti_dukungs.map((item) => <li key={item.id} className="space-y-2 p-3 text-sm">
                <p className="font-medium text-ink">{item.nama_asli || pengukuran.persyaratan_bukti.find((requirement) => requirement.id === item.jenis_berkas_id)?.nama || 'Lampiran tambahan'} <span className="text-xs text-muted">({item.mode})</span></p>
                {item.mode === 'teks' && <p className="whitespace-pre-wrap break-words text-muted">{item.isi_teks}</p>}
                {item.download_url && <a href={item.download_url} target="_blank" rel="noreferrer" className="inline-flex rounded text-primary underline focus:outline-none focus:ring-2 focus:ring-primary">{item.mode === 'tautan' ? 'Buka tautan' : 'Unduh berkas'}</a>}
            </li>)}
        </ul>}
    </div>;
}
