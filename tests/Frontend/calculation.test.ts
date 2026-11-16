import { describe, it, expect } from 'vitest';

function calculateCapaian(target: number, realisasi: number, tipe: 'naik_baik' | 'turun_baik'): number {
    if (target <= 0) return realisasi > 0 ? 100 : 0;
    if (tipe === 'turun_baik') {
        const res = ((2 * target - realisasi) / target) * 100;
        return Math.max(0, Math.round(res * 100) / 100);
    } else {
        const res = (realisasi / target) * 100;
        return Math.max(0, Math.round(res * 100) / 100);
    }
}

describe('Frontend Calculation Formula Tests', () => {
    it('correctly calculates naik_baik indicator capaian', () => {
        // Target: 70%, Realisasi: 70% => 100%
        expect(calculateCapaian(70, 70, 'naik_baik')).toBe(100);

        // Target: 70%, Realisasi: 84% => 120%
        expect(calculateCapaian(70, 84, 'naik_baik')).toBe(120);

        // Target: 70%, Realisasi: 35% => 50%
        expect(calculateCapaian(70, 35, 'naik_baik')).toBe(50);
    });

    it('correctly calculates turun_baik indicator capaian', () => {
        // Target: 8%, Realisasi: 4% => ((16 - 4) / 8) * 100 = 150%
        expect(calculateCapaian(8, 4, 'turun_baik')).toBe(150);

        // Target: 8%, Realisasi: 8% => ((16 - 8) / 8) * 100 = 100%
        expect(calculateCapaian(8, 8, 'turun_baik')).toBe(100);

        // Target: 8%, Realisasi: 10% => ((16 - 10) / 8) * 100 = 75%
        expect(calculateCapaian(8, 10, 'turun_baik')).toBe(75);

        // Target: 8%, Realisasi: 20% => ((16 - 20) / 8) * 100 = -50% => clamped to 0%
        expect(calculateCapaian(8, 20, 'turun_baik')).toBe(0);
    });
});
