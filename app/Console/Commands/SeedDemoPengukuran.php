<?php

namespace App\Console\Commands;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\TargetKinerja;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoPengukuran extends Command
{
    protected $signature = 'sakip:seed-demo-pengukuran';

    protected $description = 'Menyusun dan menyinkronkan data operasional pengukuran kinerja aktif untuk semua indikator aktif.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Command seeding demo pengukuran hanya boleh dijalankan di lingkungan non-produksi (local/testing).');

            return self::FAILURE;
        }

        $this->info('Memulai sinkronisasi data operasional pengukuran kinerja...');

        DB::beginTransaction();

        try {
            // 1. Ambil Pengguna Aktif (utamakan yang memiliki peran perencanaan atau superadmin)
            $user = User::where('email', 'dayensite@gmail.com')
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('kode', ['perencanaan', 'superadmin']))
                ->first()
                ?? User::where('is_active', true)
                    ->whereHas('roles', fn ($q) => $q->whereIn('kode', ['perencanaan', 'superadmin']))
                    ->first()
                ?? User::where('is_active', true)->first();

            if (! $user) {
                $this->error('Pengguna aktif dengan wewenang yang sesuai tidak ditemukan di sistem.');
                DB::rollBack();

                return self::FAILURE;
            }

            // 2. Ambil Renstra Aktif
            $renstra = Renstra::where('is_aktif', true)->first();
            if (! $renstra) {
                $this->error('Renstra aktif tidak ditemukan. Pastikan Renstra sudah dibuat.');
                DB::rollBack();

                return self::FAILURE;
            }

            // 3. Ambil Seluruh Indikator Kinerja Aktif di bawah Renstra terpilih
            $indikators = IndikatorKinerja::where('is_aktif', true)
                ->whereHas('sasaranStrategis', function ($query) use ($renstra) {
                    $query->where('renstra_id', $renstra->id);
                })
                ->get();

            if ($indikators->isEmpty()) {
                $this->error("Tidak ada Indikator Kinerja aktif di bawah Renstra '{$renstra->nama}'. Buat indikator terlebih dahulu di Perencanaan.");
                DB::rollBack();

                return self::FAILURE;
            }

            // 4. Buat / Ambil Master Periode
            $tw1 = Periode::firstOrCreate(['urutan' => 1], ['nama' => 'Triwulan I', 'aktif' => true, 'is_nilai_akhir' => false]);
            $tw2 = Periode::firstOrCreate(['urutan' => 2], ['nama' => 'Triwulan II', 'aktif' => true, 'is_nilai_akhir' => false]);
            $tw3 = Periode::firstOrCreate(['urutan' => 3], ['nama' => 'Triwulan III', 'aktif' => true, 'is_nilai_akhir' => false]);
            $tw4 = Periode::firstOrCreate(['urutan' => 4], ['nama' => 'Triwulan IV', 'aktif' => true, 'is_nilai_akhir' => true]);

            // 5. Buat Perjanjian Kinerja (PK) 2026
            $pk = RenstraPk::firstOrCreate(
                ['renstra_id' => $renstra->id, 'tahun' => 2026],
                [
                    'nomor_pk' => 'PK/LLDIKTI16/2026/001',
                    'tanggal_pk' => '2026-01-05',
                    'created_by' => $user->id,
                ]
            );

            // 6. Buat Jadwal Tahunan 2026 (Aktif)
            $jadwal = JadwalTahunan::where('renstra_id', $renstra->id)->where('tahun', 2026)->first();
            if (! $jadwal) {
                $jadwal = JadwalTahunan::create([
                    'renstra_id' => $renstra->id,
                    'tahun' => 2026,
                    'status' => 'aktif',
                    'renstra_pk_id' => $pk->id,
                    'rencana_aksi_mulai' => '2026-01-01',
                    'rencana_aksi_selesai' => '2026-01-31',
                    'penutupan' => '2026-12-31',
                    'activated_at' => now(),
                ]);
            } elseif ($jadwal->status === 'ditutup') {
                $this->error("Jadwal tahunan 2026 sudah berstatus 'ditutup'. Command demo tidak diizinkan membuka kembali jadwal final secara sepihak.");
                DB::rollBack();

                return self::FAILURE;
            } else {
                $jadwal->update(['renstra_pk_id' => $pk->id]);
            }

            // 7. Buat Jendela Waktu Pengisian & Reviu per Triwulan
            PeriodeJadwal::updateOrCreate(
                ['jadwal_id' => $jadwal->id, 'periode_id' => $tw1->id],
                ['pengisian_mulai' => '2026-03-01', 'pengisian_selesai' => '2026-03-31', 'reviu_mulai' => '2026-04-01', 'reviu_selesai' => '2026-04-30']
            );
            PeriodeJadwal::updateOrCreate(
                ['jadwal_id' => $jadwal->id, 'periode_id' => $tw2->id],
                ['pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-30', 'reviu_mulai' => '2026-07-01', 'reviu_selesai' => '2026-07-31']
            );
            PeriodeJadwal::updateOrCreate(
                ['jadwal_id' => $jadwal->id, 'periode_id' => $tw3->id],
                ['pengisian_mulai' => '2026-09-01', 'pengisian_selesai' => '2026-10-31', 'reviu_mulai' => '2026-10-01', 'reviu_selesai' => '2026-11-30']
            );
            PeriodeJadwal::updateOrCreate(
                ['jadwal_id' => $jadwal->id, 'periode_id' => $tw4->id],
                ['pengisian_mulai' => '2026-12-01', 'pengisian_selesai' => '2026-12-31', 'reviu_mulai' => '2027-01-01', 'reviu_selesai' => '2027-01-31']
            );

            $rows = [];

            // 8. Sinkronkan Setiap Indikator ke Pengukuran Kinerja Aktif
            foreach ($indikators as $indikator) {
                // A. Target Kinerja 2026
                TargetKinerja::updateOrCreate(
                    ['indikator_kinerja_id' => $indikator->id, 'tahun' => 2026],
                    [
                        'target_tahunan' => 85.00,
                        'target_tw1' => 20.00,
                        'target_tw2' => 45.00,
                        'target_tw3' => 70.00,
                        'target_tw4' => 85.00,
                    ]
                );

                // B. Snapshot Jadwal
                $snapshot = JadwalSnapshot::where('jadwal_id', $jadwal->id)
                    ->where('indikator_id', $indikator->id)
                    ->first();

                if (! $snapshot) {
                    $snapshot = JadwalSnapshot::create([
                        'jadwal_id' => $jadwal->id,
                        'indikator_id' => $indikator->id,
                        'periode_mulai_id' => $tw1->id,
                        'unit_id' => $indikator->unit_id,
                        'nama' => $indikator->nama,
                        'definisi' => $indikator->definisi_operasional ?: 'Definisi operasional indikator kinerja.',
                        'satuan' => $indikator->satuan,
                        'presisi' => $indikator->presisi,
                        'desimal_tampilan' => $indikator->desimal_tampilan,
                        'arah' => $indikator->arah,
                        'tipe_perhitungan' => $indikator->tipe_perhitungan,
                        'target' => 70.00,
                    ]);
                }

                // Salin definisi komponen aktif ke jadwal_snapshot_komponen
                $activeKomponens = $indikator->komponen()->where('aktif', true)->get();
                foreach ($activeKomponens as $komponen) {
                    JadwalSnapshotKomponen::firstOrCreate(
                        [
                            'jadwal_snapshot_id' => $snapshot->id,
                            'komponen_id' => $komponen->id,
                        ],
                        [
                            'kode' => $komponen->kode,
                            'label' => $komponen->label,
                            'peran' => $komponen->peran,
                            'bobot' => $komponen->bobot,
                            'urutan' => $komponen->urutan,
                        ]
                    );
                }

                // C. Penugasan PIC ke Pengguna
                PenugasanIndikator::firstOrCreate(
                    ['indikator_id' => $indikator->id, 'user_id' => $user->id],
                    [
                        'tanggal_mulai_berlaku' => '2026-01-01',
                        'ditetapkan_oleh' => $user->id,
                        'created_at' => now(),
                    ]
                );

                // D. Rencana Aksi Sah
                $ra = RencanaAksi::firstOrCreate(
                    ['indikator_id' => $indikator->id, 'tahun' => 2026],
                    [
                        'unit_id' => $indikator->unit_id,
                        'jadwal_tahunan_id' => $jadwal->id,
                        'jadwal_snapshot_id' => $snapshot->id,
                        'penanggung_jawab_id' => $user->id,
                        'created_by' => $user->id,
                        'status_alur' => 'disahkan',
                        'disahkan_by' => $user->id,
                        'disahkan_at' => now(),
                    ]
                );

                RencanaAksiVersi::firstOrCreate(
                    ['rencana_aksi_id' => $ra->id, 'nomor' => 1],
                    [
                        'jadwal_snapshot_id' => $snapshot->id,
                        'diajukan_by' => $user->id,
                        'diajukan_at' => now(),
                        'jalur_pengajuan' => 'perencanaan',
                        'dasar_izin_pengajuan' => ['keterangan' => 'Rencana aksi disahkan awal tahun'],
                        'snapshot' => [
                            'target_periode' => [
                                [
                                    'periode_id' => $tw3->id,
                                    'nilai' => 70,
                                    'status_perhitungan' => 'terhitung',
                                    'komponen' => $activeKomponens->map(fn ($k) => [
                                        'komponen_id' => $k->id,
                                        'kode' => $k->kode,
                                        'target' => 70,
                                    ])->toArray(),
                                ],
                            ],
                        ],
                        'disahkan_by' => $user->id,
                        'disahkan_at' => now(),
                    ]
                );

                // E. Baris Pengukuran Kinerja
                $isFormula = $indikator->tipe_perhitungan !== 'manual';
                $sumberNilai = $isFormula ? 'komponen' : 'manual';

                $pengukuran = PengukuranKinerja::firstOrCreate(
                    [
                        'indikator_id' => $indikator->id,
                        'tahun' => 2026,
                        'periode_id' => $tw3->id,
                        'jadwal_snapshot_id' => $snapshot->id,
                    ],
                    [
                        'status_alur' => 'draft',
                        'nilai' => null,
                        'status_perhitungan' => 'belum_diisi',
                        'sumber_nilai' => $sumberNilai,
                        'versi' => 1,
                        'created_by' => $user->id,
                    ]
                );

                if (! $pengukuran->wasRecentlyCreated && $pengukuran->sumber_nilai !== $sumberNilai) {
                    $pengukuran->update(['sumber_nilai' => $sumberNilai]);
                }

                $rows[] = [
                    $indikator->kode,
                    $indikator->nama,
                    '70 '.$indikator->satuan,
                    $pengukuran->id,
                    $pengukuran->status_alur,
                ];
            }

            DB::commit();

            $this->info('Sinkronisasi data pengukuran kinerja berhasil!');
            $this->table(
                ['Kode IKU', 'Nama Indikator', 'Target TW3', 'ID Pengukuran', 'Status'],
                $rows
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal sinkronisasi data: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
