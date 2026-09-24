import React, { useState, useMemo } from 'react';
import { 
    Calculator, 
    CheckCircle2, 
    AlertTriangle, 
    Play, 
    HelpCircle,
    Info,
    RefreshCw
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/Card';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { Input } from '@/Components/Input';

interface FormulaCardProps {
    tipePerhitungan: string;
    formulaText: string;
    isValid: boolean;
    validationMessages: string[];
    komponenList: Array<{
        id: string;
        kode: string;
        label: string;
        peran: string;
        bobot: number;
        urutan: number;
        aktif: boolean;
        satuan?: string | null;
    }>;
}

export function FormulaCard({
    tipePerhitungan,
    formulaText,
    isValid,
    validationMessages = [],
    komponenList = [],
}: FormulaCardProps) {
    const [simulasiValues, setSimulasiValues] = useState<Record<string, number | ''>>({});
    const [showSimulator, setShowSimulator] = useState(false);

    const aktifKomponen = useMemo(() => {
        return komponenList.filter(k => k.aktif);
    }, [komponenList]);

    const handleValueChange = (kode: string, value: string) => {
        if (value === '') {
            setSimulasiValues(prev => ({ ...prev, [kode]: '' }));
            return;
        }
        const num = parseFloat(value);
        if (!isNaN(num)) {
            setSimulasiValues(prev => ({ ...prev, [kode]: num }));
        }
    };

    const handleResetSimulasi = () => {
        setSimulasiValues({});
    };

    // Kalkulasi simulasi lokal untuk preview interaktif
    const simulasiResult = useMemo(() => {
        if (!isValid || aktifKomponen.length === 0) return null;

        // Cek apakah ada input
        const hasInput = aktifKomponen.some(k => simulasiValues[k.kode] !== undefined && simulasiValues[k.kode] !== '');
        if (!hasInput) return null;

        if (tipePerhitungan === 'rasio_persen') {
            const pembilangKomponen = aktifKomponen.filter(k => k.peran === 'pembilang');
            const penyebutKomponen = aktifKomponen.find(k => k.peran === 'penyebut');

            if (!penyebutKomponen) return { error: 'Penyebut tidak ditemukan' };

            const rawPenyebut = simulasiValues[penyebutKomponen.kode];
            const valPenyebut = typeof rawPenyebut === 'number' ? rawPenyebut : 0;
            const effectivePenyebut = valPenyebut * Number(penyebutKomponen.bobot || 1);

            if (effectivePenyebut === 0) {
                return { value: null, note: 'Nilai tidak dapat dihitung (pembagian dengan nol / penyebut 0)' };
            }

            let sumPembilang = 0;
            pembilangKomponen.forEach(k => {
                const val = typeof simulasiValues[k.kode] === 'number' ? simulasiValues[k.kode] : 0;
                sumPembilang += (val as number) * Number(k.bobot || 1);
            });

            const hasil = (sumPembilang / effectivePenyebut) * 100;
            return { value: hasil, note: `${sumPembilang} / ${effectivePenyebut} × 100%` };
        }

        if (tipePerhitungan === 'penjumlahan') {
            let total = 0;
            aktifKomponen.forEach(k => {
                const val = typeof simulasiValues[k.kode] === 'number' ? simulasiValues[k.kode] : 0;
                const bobot = Number(k.bobot || 1);
                total += (val as number) * bobot;
            });
            return { value: total, note: 'Penjumlahan tertimbang komponen aktif' };
        }

        return null;
    }, [tipePerhitungan, isValid, aktifKomponen, simulasiValues]);

    const getTipeBadge = (tipe: string) => {
        switch (tipe) {
            case 'rasio_persen':
                return <Badge variant="primary" size="sm">Rasio Persen (%)</Badge>;
            case 'penjumlahan':
                return <Badge variant="success" size="sm">Penjumlahan Tertimbang</Badge>;
            case 'manual':
                return <Badge variant="secondary" size="sm">Manual</Badge>;
            default:
                return <Badge variant="muted" size="sm">{tipe}</Badge>;
        }
    };

    return (
        <Card className="border border-border/80 bg-surface shadow-xs transition-shadow">
            <CardHeader className="border-b border-border/60 pb-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2.5">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Calculator className="h-5 w-5" aria-hidden="true" />
                        </span>
                        <div>
                            <CardTitle className="text-base font-semibold text-ink">
                                Kontrak & Evaluasi Formula Server
                            </CardTitle>
                            <p className="text-xs text-muted">
                                Dihitung oleh domain engine server (sumber kebenaran tunggal).
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {getTipeBadge(tipePerhitungan)}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-4 space-y-4">
                {/* Visual Box Formula */}
                <div className="rounded-lg border border-border bg-soft/60 p-3.5">
                    <div className="flex items-center justify-between mb-1.5">
                        <span className="text-xs font-medium uppercase tracking-wider text-muted">
                            Formula Representatif
                        </span>
                        <span className="text-[11px] text-muted italic">
                            Evaluasi server-side
                        </span>
                    </div>
                    <div className="font-mono text-sm font-semibold text-ink bg-surface rounded-md px-3.5 py-2.5 border border-border/70 overflow-x-auto">
                        {formulaText || 'Belum ada formula aktif yang terdefinisi.'}
                    </div>
                </div>

                {/* Validasi Komponen Status */}
                {isValid ? (
                    <div className="flex items-start gap-2.5 rounded-lg border border-success/30 bg-success/5 p-3 text-sm text-success-dark">
                        <CheckCircle2 className="h-5 w-5 shrink-0 text-success mt-0.5" aria-hidden="true" />
                        <div>
                            <span className="font-semibold text-success-dark">Struktur Komponen Valid</span>
                            <p className="text-xs text-muted mt-0.5">
                                Definisi komponen aktif memenuhi seluruh aturan kalkulasi untuk tipe perhitungan <span className="font-medium text-ink">{tipePerhitungan}</span>.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-start gap-2.5 rounded-lg border border-warning/30 bg-warning/5 p-3 text-sm text-warning-dark">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-warning mt-0.5" aria-hidden="true" />
                        <div className="space-y-1">
                            <span className="font-semibold text-warning-dark">Konfigurasi Komponen Belum Lengkap</span>
                            <ul className="list-disc list-inside text-xs text-ink/80 space-y-0.5">
                                {validationMessages.map((msg, idx) => (
                                    <li key={idx}>{msg}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* Toggle Simulator */}
                {isValid && aktifKomponen.length > 0 && (
                    <div className="border-t border-border/60 pt-3">
                        <div className="flex items-center justify-between">
                            <button
                                type="button"
                                onClick={() => setShowSimulator(prev => !prev)}
                                className="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:text-primary/80 transition-colors cursor-pointer"
                            >
                                <Play className="h-3.5 w-3.5" aria-hidden="true" />
                                {showSimulator ? 'Sembunyikan Simulator Cepat' : 'Buka Simulator Perhitungan Nilai'}
                            </button>
                            {showSimulator && (
                                <button
                                    type="button"
                                    onClick={handleResetSimulasi}
                                    className="inline-flex items-center gap-1 text-[11px] text-muted hover:text-ink transition-colors cursor-pointer"
                                >
                                    <RefreshCw className="h-3 w-3" />
                                    Reset Nilai
                                </button>
                            )}
                        </div>

                        {showSimulator && (
                            <div className="mt-3 rounded-lg border border-border bg-soft/40 p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-muted">
                                        Simulasi Input Komponen
                                    </h4>
                                    <span className="text-[11px] text-muted">
                                        {aktifKomponen.length} Komponen Aktif
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    {aktifKomponen.map(k => (
                                        <div key={k.id} className="space-y-1">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-mono font-medium text-ink">
                                                    {k.kode}
                                                    <span className="text-muted font-sans ml-1">
                                                        ({k.peran}, bobot {Number(k.bobot)})
                                                    </span>
                                                </span>
                                                {k.satuan && (
                                                    <span className="text-[11px] text-muted">{k.satuan}</span>
                                                )}
                                            </div>
                                            <Input
                                                type="number"
                                                step="any"
                                                placeholder={`Nilai ${k.kode}...`}
                                                value={simulasiValues[k.kode] ?? ''}
                                                onChange={e => handleValueChange(k.kode, e.target.value)}
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                    ))}
                                </div>

                                {/* Hasil Simulasi */}
                                <div className="mt-3 rounded-md border border-border bg-surface p-3 flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span className="text-xs text-muted block">Hasil Simulasi Evaluasi:</span>
                                        {simulasiResult ? (
                                            simulasiResult.value === null ? (
                                                <span className="text-sm font-semibold text-danger">
                                                    {simulasiResult.note}
                                                </span>
                                            ) : (
                                                <div className="flex items-baseline gap-2">
                                                    <span className="text-lg font-bold text-primary">
                                                        {Number(simulasiResult.value).toFixed(2)}
                                                        {tipePerhitungan === 'rasio_persen' && '%'}
                                                    </span>
                                                    {simulasiResult.note && (
                                                        <span className="text-xs text-muted">({simulasiResult.note})</span>
                                                    )}
                                                </div>
                                            )
                                        ) : (
                                            <span className="text-xs text-muted italic">
                                                Masukkan nilai pada komponen di atas untuk melihat simulasi hasil.
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-right">
                                        <Badge variant="muted" size="sm">
                                            Simulasi Klien
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
