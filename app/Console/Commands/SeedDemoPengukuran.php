<?php

namespace App\Console\Commands;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
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

    protected $description = 'Menyusun 1 set data simulasi operasional pengukuran kinerja aktif untuk pengujian.';

    public function handle(): int
    {
        $this->info('Memulai penyusunan data simulasi pengukuran kinerja...');

        DB::beginTransaction();

        try {
            // 1. Ambil Pengguna Aktif
            $user = User::where('email', 'dayensite@gmail.com')->first()
                ?? User::where('is_active', true)->first();

            if (! $user) {
                $this->error('Pengguna aktif tidak ditemukan di sistem.');
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

            // 3. Ambil Indikator Kinerja yang dibuat user
            $indikator = IndikatorKinerja::where('id', '01a0d6aa-c11d-73a0-adbf-c62ccd51f9b5')->first()
                ?? IndikatorKinerja::first();

            if (! $indikator) {
                $this->error('Indikator Kinerja tidak ditemukan. Buat indikator terlebih dahulu.');
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
            } else {
                $jadwal->update(['status' => 'aktif', 'renstra_pk_id' => $pk->id, 'activated_at' => now()]);
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

            // 8. Buat Target Kinerja 2026
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

            // 9. Buat Jadwal Snapshot Indikator
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

            // 10. Tetapkan Penugasan Indikator (PIC) ke Pengguna
            PenugasanIndikator::firstOrCreate(
                ['indikator_id' => $indikator->id, 'user_id' => $user->id],
                [
                    'tanggal_mulai_berlaku' => '2026-01-01',
                    'ditetapkan_oleh' => $user->id,
                    'created_at' => now(),
                ]
            );

            // 11. Buat Rencana Aksi Sah
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
                                'komponen' => [],
                            ],
                        ],
                    ],
                    'disahkan_by' => $user->id,
                    'disahkan_at' => now(),
                ]
            );

            // 12. Buat Baris Pengukuran Kinerja (Draft)
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
                    'sumber_nilai' => 'manual',
                    'versi' => 1,
                    'created_by' => $user->id,
                ]
            );

            DB::commit();

            $this->info('Data simulasi pengukuran kinerja berhasil dibuat!');
            $this->table(
                ['Entitas', 'Nilai'],
                [
                    ['Pengguna / PIC', $user->nama.' ('.$user->email.')'],
                    ['Renstra', $renstra->kode.' - '.$renstra->nama],
                    ['Tahun & Periode', '2026 - '.$tw3->nama],
                    ['Indikator', $indikator->kode.' - '.$indikator->nama],
                    ['Target Triwulan III', '70 '.$indikator->satuan],
                    ['ID Pengukuran', $pengukuran->id],
                    ['Status Alur', $pengukuran->status_alur],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Gagal membuat data simulasi: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
