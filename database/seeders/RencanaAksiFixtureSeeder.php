<?php

namespace Database\Seeders;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\JenisBerkas;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fixture sintetis deterministik untuk header + target manual rencana aksi.
 *
 * Berdiri sendiri dan TIDAK didaftarkan pada DatabaseSeeder (tidak ikut
 * seed produksi). Aman dijalankan ulang: lookup selalu memakai kunci tetap
 * yang stabil (bukan first() buta), baris existing dipertahankan —
 * nilai target buatan pengguna tidak pernah ditimpa ulang.
 */
class RencanaAksiFixtureSeeder extends Seeder
{
    private const FIXTURE_USER_EMAIL = 'perencanaan@sakip.local';

    private const FIXTURE_USER_KEYCLOAK_ID = 'fixture-perencanaan-sakip-local';

    private const FIXTURE_USER_NAMA = 'Perencanaan SAKIP';

    private const FIXTURE_UNIT_NAMA = 'Unit Fixture Rencana Aksi';

    private const FIXTURE_RENSTRA_KODE = 'RENSTRA-2025-2029';

    private const FIXTURE_SASARAN_KODE = 'SS-RA-FIXTURE';

    private const FIXTURE_INDIKATOR_KODE = 'IKU-RA-FIXTURE';

    private const FIXTURE_TAHUN = 2026;

    private const FIXTURE_LAMA = 'Fixture Rencana Aksi versi lama terdeteksi (jadwal belum aktif atau snapshot belum final); seed ulang dari database kosong.';

    /**
     * Satu transaksi: jadwal aktif tidak pernah terlihat tanpa jendela,
     * snapshot, penugasan, dan header, dan penolakan fixture lama tidak
     * meninggalkan sisa.
     */
    public function run(): void
    {
        DB::transaction(fn () => $this->susun());
    }

    private function susun(): void
    {
        $creator = User::where('email', self::FIXTURE_USER_EMAIL)->orderBy('id')->first();

        if (! $creator instanceof User) {
            $creator = User::create([
                'keycloak_id' => self::FIXTURE_USER_KEYCLOAK_ID,
                'nama' => self::FIXTURE_USER_NAMA,
                'email' => self::FIXTURE_USER_EMAIL,
                'status' => 'aktif',
            ]);
        }

        $creator->loadMissing('roles');

        if ($creator->roles->isEmpty()) {
            $perencanaanRole = Role::where('kode', 'perencanaan')->where('aktif', true)->first();

            if ($perencanaanRole !== null) {
                $creator->roles()->attach($perencanaanRole->id, [
                    'id' => (string) Str::uuid(),
                    'sumber_pemberian' => 'manual',
                    'diberikan_oleh' => $creator->id,
                    'created_at' => now(),
                ]);
                $creator->load('roles');
            }
        }

        $creatorRole = $creator->roles->first()?->kode;

        if ($creatorRole === null) {
            throw new \LogicException('Seeder membutuhkan minimal satu peran aktif pada katalog (jalankan AccessCatalogSeeder dulu).');
        }

        $unit = Unit::where('nama', self::FIXTURE_UNIT_NAMA)->first() ?? Unit::create([
            'nama' => self::FIXTURE_UNIT_NAMA,
            'status' => 'aktif',
            'created_by' => $creator->id,
        ]);

        $renstra = Renstra::where('kode', self::FIXTURE_RENSTRA_KODE)->first() ?? Renstra::create([
            'kode' => self::FIXTURE_RENSTRA_KODE,
            'nama' => 'Rencana Strategis LLDIKTI Wilayah XVI 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'created_by' => $creator->id,
        ]);

        $sasaran = SasaranStrategis::where('renstra_id', $renstra->id)->where('kode', self::FIXTURE_SASARAN_KODE)->first()
            ?? SasaranStrategis::create([
                'renstra_id' => $renstra->id,
                'kode' => self::FIXTURE_SASARAN_KODE,
                'deskripsi' => 'Sasaran fixture rencana aksi (sintetis, bukan data produksi)',
                'urutan' => 99,
            ]);

        $indikator = $this->syncIndikator($sasaran->id, $unit->id, $renstra->tahun_mulai, $creator, $creatorRole);

        $periodes = $this->ensurePeriodes();

        // Header Rencana Aksi hanya sah di atas jadwal aktif dengan snapshot
        // beku; jendela pengisian dan RA dibuka sepanjang tahun fixture.
        $jadwal = JadwalTahunan::where('renstra_id', $renstra->id)->where('tahun', self::FIXTURE_TAHUN)->first()
            ?? JadwalTahunan::create([
                'renstra_id' => $renstra->id,
                'tahun' => self::FIXTURE_TAHUN,
                'rencana_aksi_mulai' => sprintf('%d-01-01', self::FIXTURE_TAHUN),
                'rencana_aksi_selesai' => sprintf('%d-12-31', self::FIXTURE_TAHUN),
                'penutupan' => sprintf('%d-12-31', self::FIXTURE_TAHUN),
                'status' => 'aktif',
                'activated_at' => now(),
            ]);
        // Fixture lama tidak di-upgrade diam-diam: header di atasnya ditolak
        // fail-closed, jadi lebih jelas gagal dan minta seed ulang.
        if ($jadwal->status !== 'aktif') {
            throw new \LogicException(self::FIXTURE_LAMA);
        }

        foreach ($periodes as $periode) {
            PeriodeJadwal::where('jadwal_id', $jadwal->id)->where('periode_id', $periode->id)->first() ?? PeriodeJadwal::create([
                'jadwal_id' => $jadwal->id,
                'periode_id' => $periode->id,
                'pengisian_mulai' => sprintf('%d-01-01', self::FIXTURE_TAHUN),
                'pengisian_selesai' => sprintf('%d-06-30', self::FIXTURE_TAHUN),
                'reviu_mulai' => sprintf('%d-07-01', self::FIXTURE_TAHUN),
                'reviu_selesai' => sprintf('%d-12-31', self::FIXTURE_TAHUN),
            ]);
        }

        $snapshot = JadwalSnapshot::where('jadwal_id', $jadwal->id)->where('indikator_id', $indikator->id)->orderByDesc('nomor_versi')->first()
            ?? JadwalSnapshot::create([
                'jadwal_id' => $jadwal->id,
                'indikator_id' => $indikator->id,
                'periode_mulai_id' => $periodes->first()?->id,
                'unit_id' => $indikator->unit_id,
                'nama' => $indikator->nama,
                'definisi' => 'Definisi beku fixture (sintetis).',
                'satuan' => $indikator->satuan,
                'presisi' => $indikator->presisi,
                'desimal_tampilan' => $indikator->desimal_tampilan,
                'arah' => $indikator->arah,
                'tipe_perhitungan' => $indikator->tipe_perhitungan,
                'target' => 100,
                // Jadwal dibuat langsung aktif sehingga trigger finalisasi
                // (hanya saat transisi status) tidak berjalan; tandai final
                // seperti hasil aktivasi.
                'komposisi_final' => true,
            ]);
        if (! $snapshot->komposisi_final) {
            throw new \LogicException(self::FIXTURE_LAMA);
        }

        PenugasanIndikator::where('indikator_id', $indikator->id)
            ->where('user_id', $creator->id)
            ->whereDate('tanggal_mulai_berlaku', '2026-01-01')
            ->first() ?? PenugasanIndikator::create([
                'indikator_id' => $indikator->id,
                'user_id' => $creator->id,
                'tanggal_mulai_berlaku' => '2026-01-01',
                'ditetapkan_oleh' => $creator->id,
                'alasan' => 'PIC fixture rencana aksi.',
                'created_at' => now(),
            ]);

        // Header bersifat catatan historis penyusunan: existing tidak ditulis
        // ulang (unit/PIC tersimpan apa adanya seperti saat disusun).
        $rencanaAksi = RencanaAksi::where('indikator_id', $indikator->id)->where('tahun', self::FIXTURE_TAHUN)->first()
            ?? RencanaAksi::create([
                'indikator_id' => $indikator->id,
                'tahun' => self::FIXTURE_TAHUN,
                'unit_id' => $unit->id,
                'jadwal_tahunan_id' => $jadwal->id,
                'snapshot_draf_id' => $snapshot->id,
                'penanggung_jawab_id' => $creator->id,
                'uraian' => 'Header fixture rencana aksi (sintetis).',
                'status_alur' => RencanaAksi::STATUS_DRAFT,
                'versi' => 1,
                'created_by' => $creator->id,
            ]);

        // Satu baris NULL per periode untuk indikator manual; nilai pengguna
        // dipertahankan (hanya diisi saat baris belum ada).
        foreach ($periodes as $periode) {
            RencanaAksiTarget::where('rencana_aksi_id', $rencanaAksi->id)
                ->where('periode_id', $periode->id)
                ->whereNull('komponen_id')
                ->first() ?? RencanaAksiTarget::create([
                    'rencana_aksi_id' => $rencanaAksi->id,
                    'periode_id' => $periode->id,
                    'komponen_id' => null,
                    'nilai' => 25 * $periode->urutan,
                    'keterangan' => null,
                    'updated_by' => $creator->id,
                    'updated_at' => now(),
                ]);
        }

        // Persyaratan bukti dukung tahap rencana aksi, dibatasi pada indikator
        // fixture agar tidak menggerbangi pengajuan rencana aksi lain.
        JenisBerkas::where('tahap', 'rencana_aksi')->where('indikator_id', $indikator->id)->where('nama', 'Dokumen Kerangka Acuan Kerja (KAK)')->first()
            ?? JenisBerkas::create([
                'nama' => 'Dokumen Kerangka Acuan Kerja (KAK)',
                'keterangan' => 'KAK yang memuat rincian aktivitas dan anggaran pelaksanaan.',
                'tahap' => 'rencana_aksi',
                'indikator_id' => $indikator->id,
                'wajib' => true,
                'semua_mode_wajib' => false,
                'izinkan_file' => true,
                'izinkan_tautan' => true,
                'izinkan_teks' => true,
                'aktif' => true,
                'urutan' => 1,
                'created_by' => $creator->id,
            ]);
    }

    /**
     * @return Collection<int, Periode>
     */
    private function ensurePeriodes(): Collection
    {
        if (Periode::exists()) {
            return Periode::orderBy('urutan')->orderBy('id')->get();
        }

        foreach (['Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV'] as $index => $nama) {
            Periode::create([
                'nama' => $nama,
                'urutan' => $index + 1,
                'aktif' => true,
                'is_nilai_akhir' => $index === 3,
            ]);
        }

        return Periode::orderBy('urutan')->orderBy('id')->get();
    }

    private function syncIndikator(string $sasaranId, string $unitId, int $tahunMulai, User $creator, string $creatorRole): IndikatorKinerja
    {
        $mutable = [
            'sasaran_strategis_id' => $sasaranId,
            'unit_id' => $unitId,
            'nama' => 'Indikator Manual Fixture Rencana Aksi',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'tahun_mulai_berlaku' => $tahunMulai,
        ];
        $createOnly = [
            'status' => 'aktif',
            'created_by' => $creator->id,
            'created_by_role' => $creatorRole,
        ];

        $existing = IndikatorKinerja::where('kode', self::FIXTURE_INDIKATOR_KODE)->first();

        if ($existing instanceof IndikatorKinerja) {
            $existing->update($mutable);

            return $existing->refresh();
        }

        return IndikatorKinerja::create(array_merge(['kode' => self::FIXTURE_INDIKATOR_KODE], $mutable, $createOnly));
    }
}
