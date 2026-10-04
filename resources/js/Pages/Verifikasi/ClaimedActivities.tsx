import { Card, CardContent, CardHeader, CardTitle } from '@/Components/Card';
import { useFormatTanggal } from '@/hooks/useFormatTanggal';
import type { Pengukuran } from '@/Pages/Pengukuran/types';

export default function ClaimedActivities({ pengukuran }: { pengukuran: Pengukuran }) {
    const formatTanggal = useFormatTanggal();
    const claims = pengukuran.klaim ?? [];
    const date = (value: string | null) => formatTanggal(value);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Kegiatan dalam Pengajuan</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
                <p className="text-xs text-muted">
                    Rincian klaim kegiatan dan bukti terlampir dibekukan pada versi pengajuan ini.
                </p>
                {!pengukuran.can.viewClaims ? (
                    <p className="text-muted">Anda tidak memiliki akses untuk melihat rincian kegiatan ini.</p>
                ) : claims.length === 0 ? (
                    <p className="text-muted">Tidak ada klaim kegiatan pada versi pengajuan ini.</p>
                ) : (
                    <ul className="space-y-4" aria-label="Klaim kegiatan pengajuan">
                        {claims.map((claim) => {
                            const activity = claim.kegiatan;
                            const component = pengukuran.komponen.find((item) => item.komponen_id === claim.komponen_id);
                            return (
                                <li key={claim.id} className="space-y-4 rounded-lg border border-border p-4 bg-surface">
                                    <div>
                                        <h3 className="break-words font-semibold text-ink">{activity.nama}</h3>
                                        <p className="mt-1 capitalize text-xs text-muted">
                                            Status: {activity.status.replaceAll('_', ' ')}
                                        </p>
                                    </div>
                                    <dl className="grid gap-3 sm:grid-cols-2 text-xs">
                                        <div>
                                            <dt className="text-muted">Dukungan klaim</dt>
                                            <dd className="mt-0.5 font-medium text-ink">
                                                {component ? (
                                                    <>
                                                        <span className="font-mono">{component.kode}</span> · {component.label}
                                                    </>
                                                ) : (
                                                    'Pendukung rencana aksi'
                                                )}{' '}
                                                · {claim.arah_dampak || '-'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted">Sumber klaim</dt>
                                            <dd className="mt-0.5 font-medium text-ink">
                                                {claim.sumber_klaim === 'rencana_aksi' ? 'Rencana aksi' : 'Pengukuran'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted">Tanggal rencana / realisasi</dt>
                                            <dd className="mt-0.5 font-medium text-ink">
                                                {date(activity.tanggal_rencana)} / {date(activity.tanggal_realisasi)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-muted">Sasaran / realisasi peserta</dt>
                                            <dd className="mt-0.5 font-medium text-ink">
                                                <span className="font-mono">{activity.sasaran_peserta ?? '-'}</span> /{' '}
                                                <span className="font-mono">{activity.realisasi_peserta ?? '-'}</span>
                                            </dd>
                                        </div>
                                    </dl>
                                    <dl className="space-y-2.5 text-xs">
                                        {([
                                            ['Tujuan', activity.tujuan],
                                            ['Catatan klaim', claim.catatan],
                                            ['Justifikasi status', activity.justifikasi],
                                            ['Uraian pelaksanaan', activity.uraian_pelaksanaan],
                                            ['Kendala', activity.kendala],
                                            ['Strategi tindak lanjut', activity.strategi_tindaklanjut],
                                        ] as const).map(
                                            ([label, value]) =>
                                                value && (
                                                    <div key={label}>
                                                        <dt className="font-semibold text-ink">{label}</dt>
                                                        <dd className="mt-0.5 whitespace-pre-wrap break-words text-muted">
                                                            {value}
                                                        </dd>
                                                    </div>
                                                )
                                        )}
                                    </dl>
                                    <div className="space-y-2">
                                        <h4 className="text-xs font-semibold text-ink">Bukti kegiatan</h4>
                                        {!pengukuran.can.claimEvidence ? (
                                            <p className="text-xs text-muted">Anda tidak memiliki akses untuk melihat bukti kegiatan ini.</p>
                                        ) : activity.bukti_dukungs.length === 0 ? (
                                            <p className="text-xs text-muted">Tidak ada bukti kegiatan pada versi ini.</p>
                                        ) : (
                                            <ul className="space-y-2 text-xs" aria-label={`Bukti kegiatan ${activity.nama}`}>
                                                {activity.bukti_dukungs.map((item) => (
                                                    <li key={item.id} className="space-y-1 rounded-lg bg-soft p-3">
                                                        <p className="font-medium text-ink">{item.nama_asli || `Bukti ${item.mode}`}</p>
                                                        {item.menggantikan_id && (
                                                            <p className="break-words text-xs text-muted">Bukti koreksi · {item.alasan_koreksi}</p>
                                                        )}
                                                        {item.mode === 'teks' && (
                                                            <p className="whitespace-pre-wrap break-words text-muted">{item.isi_teks}</p>
                                                        )}
                                                        {item.download_url && (
                                                            <a
                                                                href={item.download_url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="inline-flex rounded text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/20 font-medium"
                                                            >
                                                                {item.mode === 'tautan' ? 'Buka tautan kegiatan' : 'Unduh bukti kegiatan'}
                                                            </a>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
