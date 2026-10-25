<?php

use App\Models\Periode;
use Tests\TestCase;

uses(TestCase::class);

function periodeBertanggal(int $bulanMulai, int $hariMulai, int $bulanSelesai, int $hariSelesai): Periode
{
    return new Periode(['nama' => 'Periode uji', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false,
        'bulan_mulai' => $bulanMulai, 'hari_mulai' => $hariMulai, 'bulan_selesai' => $bulanSelesai, 'hari_selesai' => $hariSelesai]);
}

test('rentang periode dibentuk pada tahun yang diminta', function (): void {
    expect(periodeBertanggal(1, 1, 3, 31)->rentangPadaTahun(2027))->toBe(['mulai' => '2027-01-01', 'selesai' => '2027-03-31']);
    expect(periodeBertanggal(10, 1, 12, 31)->rentangPadaTahun(2026))->toBe(['mulai' => '2026-10-01', 'selesai' => '2026-12-31']);
});

test('batas awal pengisian adalah tanggal 1 bulan terakhir periode pada tahun jadwal', function (): void {
    expect(periodeBertanggal(10, 1, 12, 31)->batasAwalPengisian(2026))->toBe('2026-12-01');
    expect(periodeBertanggal(1, 1, 3, 31)->batasAwalPengisian(2027))->toBe('2027-03-01');
    expect(periodeBertanggal(2, 15, 5, 14)->batasAwalPengisian(2027))->toBe('2027-05-01');
});

test('periode tanpa tanggal tidak memiliki rentang maupun batas awal pengisian', function (): void {
    $periode = new Periode(['nama' => 'Tanpa tanggal', 'urutan' => 1, 'aktif' => false, 'is_nilai_akhir' => false]);

    expect($periode->rentangPadaTahun(2027))->toBeNull();
    expect($periode->batasAwalPengisian(2027))->toBeNull();
});
