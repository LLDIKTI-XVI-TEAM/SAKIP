<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PenugasanIndikator;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\RencanaAksiVersi;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Perencanaan\IndikatorArsipGuard;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Services\RencanaAksi\KesiapanPengajuan;
use App\Services\RencanaAksi\KonteksBekuRencanaAksi;
use App\Support\AlasanAudit;
use App\Support\PerbandinganNilaiRencanaAksi;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AjukanRencanaAksi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
        private readonly IndikatorArsipGuard $arsipGuard,
        private readonly AuditLogger $audit,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly KonteksBekuRencanaAksi $konteks,
        private readonly KesiapanPengajuan $kesiapan,
        private readonly EvaluateEvidence $evaluator,
    ) {}

    /**
     * Mengajukan rencana aksi: mengevaluasi ulang seluruh gerbang pada state
     * terkunci, membekukan satu baris `rencana_aksi_versi` (immutable lewat
     * trigger), mengubah header ke `diajukan`, dan mengaudit di transaksi yang
     * sama. Reviewer membaca versi beku ini, bukan draf hidup.
     *
     * Urutan kunci sama dengan `SimpanTargetPeriode` (anti-deadlock antar-writer
     * RA): aktor + ACL, header, indikator, jadwal, unit (berbagi), snapshot
     * terbaru, komponen snapshot, jendela periode, target, lalu bukti current.
     * Header `FOR UPDATE` menserialkan semua writer anak RA (simpan target,
     * tambah/hapus bukti), sehingga target dan bukti tidak berubah selama
     * evaluasi. Klik ganda antre di kunci yang sama lalu ditolak gerbang
     * status (status diperiksa sebelum `expected_versi`); unique
     * `(rencana_aksi_id, nomor)` adalah penjaga terakhir.
     *
     * Jepit snapshot draf harus sama dengan snapshot terbaru; bila snapshot
     * koreksi terbit setelah simpan terakhir, tolak 409 tanpa rekonsiliasi
     * otomatis agar pengaju menyimpan ulang di konteks baru. Karena jepit =
     * terbaru, tidak ada baris basi transisi yang perlu disaring. Jendela
     * dinilai terhadap periode yang berubah dibanding versi sah terakhir
     * (Q41 §41.2): untuk PIC hanya periode terkunci yang nilainya berubah
     * yang menolak pengajuan; periode terkunci yang nilainya sama tetap
     * lolos agar alur buka-kembali lalu ubah periode terbuka lalu ajukan
     * ulang tidak mati; tanpa versi sah sebelumnya tidak ada pembanding
     * sehingga dinilai sebagai daftar kosong (guard simpan sudah mencegah
     * perubahan sejak awal). Dalam sesi koreksi, lingkup dinilai terhadap
     * periode yang berubah pula, berlaku untuk PIC maupun Perencanaan.
     * Klaim kegiatan tahap RA (`sumber_klaim = rencana_aksi`) dibekukan ke
     * snapshot versi dan wajib minimal satu kecuali pengecualian Q46. Pengajuan
     * menilai periode yang berubah dari target dan klaim tahap RA terhadap versi
     * sah, selain pemeriksaan periode kegiatan oleh writer klaim saat mutasi.
     *
     * Pengajuan Pengukuran juga mengunci header RA sebelum indikator agar
     * tidak membentuk siklus kunci dengan writer/reviewer RA.
     *
     * @param  array<string, mixed>  $data  Hanya `expected_versi`.
     */
    public function handle(User $actor, string $id, array $data): RencanaAksiVersi
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;

        try {
            return DB::transaction(function () use ($actor, $id, $data, &$dasarIzin): RencanaAksiVersi {
                $kunci = $this->lockedActor->handle($actor, PermissionCodes::RENCANA_AKSI_AJUKAN);
                /** @var User|null $pengunci */
                $pengunci = $kunci['aktor'];
                if (! $pengunci instanceof User || $pengunci->status !== 'aktif') {
                    $dasarIzin = $kunci['keputusan']->toAuditBasis();
                    throw new AuthorizationException('Akun pengguna tidak aktif.');
                }

                $header = RencanaAksi::lockForUpdate()->findOrFail($id);
                $indikator = IndikatorKinerja::lockForUpdate()->findOrFail($header->indikator_id);
                $jadwal = JadwalTahunan::lockForUpdate()->findOrFail($header->jadwal_tahunan_id);

                $unitHeader = Unit::whereKey((string) $header->unit_id)->sharedLock()->first();
                if (! $unitHeader instanceof Unit || $unitHeader->status !== 'aktif') {
                    throw ValidationException::withMessages(['unit_id' => 'Unit pemilik rencana aksi berstatus nonaktif.']);
                }

                $keputusan = $this->resolver->resolve($pengunci, PermissionCodes::RENCANA_AKSI_AJUKAN, (string) $header->unit_id);
                $dasarIzin = $keputusan->toAuditBasis();
                if (! $keputusan->allowed) {
                    throw new AuthorizationException('Izin pengajuan rencana aksi tidak tersedia atau telah dicabut.');
                }

                $this->arsipGuard->pastikanDapatDibuatkan($indikator, 'rencana_aksi');

                if (! in_array($header->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true)) {
                    throw ValidationException::withMessages(['status_alur' => "Rencana aksi berstatus {$header->status_alur} tidak dapat diajukan."]);
                }
                if ($header->versi !== (int) ($data['expected_versi'] ?? 0)) {
                    throw ValidationException::withMessages(['expected_versi' => 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.'])->status(409);
                }

                $snapshot = $this->konteks->snapshotTerbaru($jadwal->id, $indikator->id, kunci: true)
                    ?? throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia; pengajuan ditolak.']);
                if ((string) $snapshot->unit_id !== (string) $header->unit_id) {
                    throw ValidationException::withMessages(['snapshot' => 'Unit pemilik rencana aksi tidak selaras dengan konteks beku terbaru; pengajuan ditolak sampai snapshot koreksi tersedia.']);
                }
                if ((string) $header->snapshot_draf_id !== (string) $snapshot->id) {
                    throw ValidationException::withMessages(['expected_snapshot_id' => 'Konteks indikator berubah (snapshot koreksi baru terbit). Muat ulang dan simpan target sebelum mengajukan.'])->status(409);
                }

                // Titik keputusan pengajuan: satu waktu untuk gerbang jendela
                // (penutupan, sesi koreksi, jendela PIC), PJ efektif, dan
                // `diajukan_at` (Data Model §2.34: PJ pada tanggal submit), diambil
                // sebelum tunggu kunci berikutnya yang dapat melewati tengah malam.
                // PJ dibaca sekali setelah indikator dikunci (writer PJ ikut mengunci
                // indikator) dan dipakai gerbang serta provenance. Boleh null pada
                // jalur Perencanaan; pergantian PJ, peran, atau grant setelahnya
                // tidak menulis ulang versi.
                $waktuAjukan = now();
                $hariIni = $waktuAjukan->copy()->setTimezone(config('app.business_timezone'))->toDateString();
                $penugasan = PenugasanIndikator::effectiveOn($hariIni)
                    ->where('indikator_id', $indikator->id)
                    ->first();
                $jendelaPeriode = $this->konteks->jendela($jadwal->id, kunci: true);
                $periodeEfektif = $this->konteks->periodeEfektif($snapshot, $jendelaPeriode);

                $presisi = (int) $snapshot->presisi;
                $definisi = $this->konteks->komponen($snapshot, kunci: true);
                $jendela = $jendelaPeriode;
                $targets = RencanaAksiTarget::where('rencana_aksi_id', $header->id)
                    ->orderBy('periode_id')
                    ->orderBy('komponen_id')
                    ->lockForUpdate()
                    ->get();
                $bukti = $header->buktiDukungs()->current()->orderBy('berkas.id')->lockForUpdate()->get();

                $baris = $this->kesiapan->barisPeriode(
                    (string) $snapshot->tipe_perhitungan,
                    $presisi,
                    $definisi,
                    $jendela,
                    $periodeEfektif,
                    $this->kesiapan->petaTarget($targets),
                    $snapshot->setRelation('jadwal', $jadwal),
                );
                // Kunci berbasis perubahan nilai: periode yang dinilai
                // terhadap lingkup koreksi maupun kunci tanggal adalah
                // periode yang berubah dibanding versi sah terakhir; tanpa
                // pembanding dinilai sebagai daftar kosong. Hasil terstruktur
                // membedakan penutupan, PIC, dan kunci tanpa mencocokkan teks.
                $berubah = PerbandinganNilaiRencanaAksi::periodeBerubahVsVersiSah($header->id, $baris);
                $penilaian = $this->jendela->penilaian($pengunci, $keputusan, $indikator, $jadwal, 'pengajuan', $berubah, $penugasan?->user_id, pada: $waktuAjukan);
                if ($penilaian['pesan'] !== null) {
                    throw ValidationException::withMessages(['jendela' => $penilaian['pesan']]);
                }
                $deviasi = $this->kesiapan->deviasi($baris, $snapshot);
                $persyaratan = $this->evaluator->untuk('rencana_aksi', (string) $indikator->id, $bukti);
                $jalurPerencanaan = $this->jendela->jalurPerencanaan($keputusan);
                // Periode terkunci bagi PIC pada tanggal bisnis keputusan; Perencanaan tidak terkena kunci tanggal.
                $periodeTerkunci = $jalurPerencanaan ? [] : array_column($this->jendela->rincianTerkunci($jadwal, $periodeEfektif->all(), $hariIni), 'id');
                $alasan = $this->kesiapan->alasanTolak($baris, $deviasi, $header->alasan_deviasi_pk, $persyaratan, (int) $header->tahun, $jalurPerencanaan, $periodeTerkunci);
                // Urutan header → klaim → kegiatan sama dengan pengajuan Pengukuran; writer klaim juga mengunci header lebih dulu.
                $klaim = KlaimKegiatan::where('rencana_aksi_id', $header->id)->where('sumber_klaim', 'rencana_aksi')->orderBy('id')->lockForUpdate()->get();
                $kegiatan = Kegiatan::whereIn('id', $klaim->pluck('kegiatan_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                // Snapshot koreksi dapat terbit setelah klaim dibuat; klaim yang tidak lagi cocok tidak boleh dibekukan.
                $bacaKegiatan = $this->resolver->allows($pengunci, PermissionCodes::KEGIATAN_READ, (string) $header->unit_id);
                $alasan = [...$alasan, ...$this->kesiapan->alasanKlaim($klaim, $kegiatan, $header, $periodeEfektif, $definisi->pluck('komponen_id')->all(), $bacaKegiatan)];
                // Berkas kegiatan dikunci setelah kegiatan, searah writer berkas kegiatan, agar RAB yang dinilai sama dengan yang dibekukan.
                $buktiKegiatan = BuktiDukung::where('berkasable_type', 'kegiatan')->whereIn('berkasable_id', $kegiatan->keys()->all())->current()
                    ->orderBy('berkas.id')->sharedLock()->get()->groupBy(fn (BuktiDukung $item): string => (string) $item->berkasable_id);
                $perencanaanKegiatan = $this->kesiapan->perencanaanKegiatan($kegiatan, (string) $indikator->id, (int) $header->tahun,
                    $jalurPerencanaan, $jadwal, $jendelaPeriode, $buktiKegiatan, $bacaKegiatan);
                $alasan = [...$alasan, ...$perencanaanKegiatan['alasan']];
                $alasanKegiatan = $this->kesiapan->alasanKegiatan($klaim->count(), (int) $header->tahun, $jalurPerencanaan);
                if ($alasanKegiatan !== null) {
                    $alasan[] = $alasanKegiatan;
                }
                if ($alasan !== []) {
                    throw ValidationException::withMessages(['pengajuan' => $alasan]);
                }
                $klaimBeku = $this->kesiapan->klaimBeku($klaim, $kegiatan);
                // Q41 §41.2: daftar kegiatan periode terkunci tidak boleh berubah lewat pengajuan PIC, dinilai seperti target terhadap versi sah.
                $berubahKlaim = PerbandinganNilaiRencanaAksi::periodeKlaimBerubahVsVersiSah($header->id, $klaimBeku);
                if ($berubahKlaim !== []) {
                    $penilaianKlaim = $this->jendela->penilaian($pengunci, $keputusan, $indikator, $jadwal, 'pengajuan', $berubahKlaim, $penugasan?->user_id, pada: $waktuAjukan);
                    if ($penilaianKlaim['pesan'] !== null) {
                        throw ValidationException::withMessages(['jendela' => $penilaianKlaim['pesan']]);
                    }
                }

                $sebelum = $header->only(['status_alur', 'versi', 'snapshot_draf_id']);

                $versi = RencanaAksiVersi::create([
                    'rencana_aksi_id' => $header->id,
                    'jadwal_snapshot_id' => $snapshot->id,
                    'nomor' => (int) RencanaAksiVersi::where('rencana_aksi_id', $header->id)->max('nomor') + 1,
                    'diajukan_by' => $pengunci->id,
                    'diajukan_at' => $waktuAjukan,
                    'jalur_pengajuan' => $jalurPerencanaan ? 'perencanaan' : 'pic',
                    'dasar_izin_pengajuan' => [
                        ...$keputusan->toAuditBasis(),
                        'unit_id' => (string) $header->unit_id,
                        'penugasan_id' => $penugasan?->id,
                        'pic_id' => $penugasan?->user_id,
                    ],
                    'snapshot' => [
                        'indikator' => ['kode' => $indikator->kode, 'nama' => (string) $snapshot->nama],
                        'unit_kerja' => $unitHeader->only(['id', 'nama']),
                        'pic' => $penugasan?->pic?->only(['id', 'nama']),
                        'uraian' => $header->uraian,
                        'alasan_deviasi_pk' => $header->alasan_deviasi_pk,
                        'deviasi_pk' => $deviasi,
                        'target_periode' => $baris,
                        'klaim' => $klaimBeku,
                        // Evaluasi persyaratan tahap perencanaan kegiatan beserta berkasnya (Q42 §42.4); contoh berkas tidak ikut dibekukan.
                        'persyaratan_kegiatan' => array_map(fn (array $item): array => $item + [
                            'berkas' => $buktiKegiatan->get($item['kegiatan_id'], collect())->map(fn (BuktiDukung $berkas): array => $berkas->only([
                                'id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks',
                            ]))->values()->all(),
                        ], $perencanaanKegiatan['evaluasi']),
                        'persyaratan_bukti' => $persyaratan,
                        // `path` privat hanya untuk unduhan beku di server; tidak pernah masuk props.
                        'bukti_dukungs' => $bukti->map(fn (BuktiDukung $item): array => $item->only([
                            'id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks', 'path',
                        ]))->values()->all(),
                    ],
                ]);

                // PRD §18.7: waiver file tercatat di audit_log bersama versi (format sama
                // dengan pengajuan Pengukuran); kegagalan audit membatalkan pengajuan.
                $pengecualian = collect($persyaratan)
                    ->filter(fn (array $item): bool => $item['wajib'] && $item['pemenuhan']['mode_dikecualikan'] !== [])
                    ->map(fn (array $item): array => ['jenis_berkas_id' => $item['id'], 'nama' => $item['nama'],
                        'mode' => $item['pemenuhan']['mode_dikecualikan'], 'alasan' => $item['pemenuhan']['alasan_pengecualian']])
                    ->values()
                    ->all();
                if ($pengecualian !== []) {
                    $this->audit->catat(
                        actor: $pengunci,
                        tindakan: 'berkas.tandai_tidak_dapat_dipenuhi',
                        objekTipe: 'rencana_aksi',
                        objekId: (string) $header->id,
                        nilaiBaru: ['versi_pengajuan_id' => $versi->id, 'nomor_pengajuan' => $versi->nomor, 'pengecualian' => $pengecualian],
                        alasan: 'Kewajiban mode file dikecualikan karena unggahan dinonaktifkan.',
                        dasarIzin: $dasarIzin,
                    );
                }

                $header->status_alur = RencanaAksi::STATUS_DIAJUKAN;
                $header->versi++;
                $header->save();

                $this->audit->catat(
                    actor: $pengunci,
                    tindakan: 'rencana_aksi.ajukan',
                    objekTipe: 'rencana_aksi',
                    objekId: (string) $header->id,
                    nilaiLama: $sebelum,
                    nilaiBaru: [
                        ...$header->only(['status_alur', 'versi']),
                        'versi_pengajuan' => $versi->only(['id', 'nomor', 'jalur_pengajuan', 'diajukan_by', 'jadwal_snapshot_id']),
                    ],
                    alasan: 'Mengajukan rencana aksi untuk verifikasi.',
                    dasarIzin: $dasarIzin,
                );

                return $versi;
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException)
                && RencanaAksi::whereKey($id)->exists()) {
                // Pesan pertama `errors()`, bukan `getMessage()` yang menambah
                // "(and N more errors)" berbahasa Inggris.
                $teks = $exception instanceof ValidationException
                    ? collect($exception->errors())->flatten()->first()
                    : $exception->getMessage();
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'rencana_aksi.ajukan_ditolak',
                    objekTipe: 'rencana_aksi',
                    objekId: $id,
                    alasan: AlasanAudit::sanitasi($teks, 'Pengajuan rencana aksi ditolak.'),
                    dasarIzin: $dasarIzin,
                );
            }

            throw $exception;
        }
    }
}
