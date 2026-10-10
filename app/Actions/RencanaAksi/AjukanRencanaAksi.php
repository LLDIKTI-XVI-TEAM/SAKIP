<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
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
     * dinilai dengan `periodeIds` kosong: pengajuan menyangkut seluruh RA dan
     * lingkup periode koreksi sudah dijaga saat simpan. Klaim kegiatan belum
     * dibekukan karena belum ada writer klaim rencana aksi.
     *
     * Keterbatasan lintas modul yang sudah ada: `SubmitPengukuran` mengunci
     * indikator lalu `rencana_aksi`, berlawanan dengan writer RA (header lalu
     * indikator); race RA dan Pengukuran pada indikator yang sama dapat
     * berakhir deadlock 40P01.
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

                $alasanJendela = $this->jendela->alasanTolak($pengunci, $keputusan, $indikator, $jadwal, 'pengajuan', []);
                if ($alasanJendela !== null) {
                    throw ValidationException::withMessages(['jendela' => $alasanJendela]);
                }

                $presisi = (int) $snapshot->presisi;
                $definisi = $this->konteks->komponen($snapshot, kunci: true);
                $jendela = $this->konteks->jendela($jadwal->id, kunci: true);
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
                    $this->konteks->periodeEfektif($snapshot, $jendela),
                    $this->kesiapan->petaTarget($targets),
                );
                $deviasi = $this->kesiapan->deviasi($baris, $snapshot);
                $persyaratan = $this->evaluator->untuk('rencana_aksi', (string) $indikator->id, $bukti);
                $alasan = $this->kesiapan->alasanTolak($baris, $deviasi, $header->alasan_deviasi_pk, $persyaratan);
                if ($alasan !== []) {
                    throw ValidationException::withMessages(['pengajuan' => $alasan]);
                }

                // PJ efektif hari ini (zona bisnis) dibekukan sebagai provenance;
                // boleh null pada jalur Perencanaan. Pergantian PJ, peran, atau
                // grant setelahnya tidak menulis ulang versi ini.
                $penugasan = PenugasanIndikator::effectiveOn(today(config('app.business_timezone'))->toDateString())
                    ->where('indikator_id', $indikator->id)
                    ->first();
                $sebelum = $header->only(['status_alur', 'versi', 'snapshot_draf_id']);

                $versi = RencanaAksiVersi::create([
                    'rencana_aksi_id' => $header->id,
                    'jadwal_snapshot_id' => $snapshot->id,
                    'nomor' => (int) RencanaAksiVersi::where('rencana_aksi_id', $header->id)->max('nomor') + 1,
                    'diajukan_by' => $pengunci->id,
                    'diajukan_at' => now(),
                    'jalur_pengajuan' => $this->jendela->jalurPerencanaan($keputusan) ? 'perencanaan' : 'pic',
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
                        'persyaratan_bukti' => $persyaratan,
                        // `path` privat hanya untuk unduhan beku di server; tidak pernah masuk props.
                        'bukti_dukungs' => $bukti->map(fn (BuktiDukung $item): array => $item->only([
                            'id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks', 'path',
                        ]))->values()->all(),
                    ],
                ]);

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
