<?php

namespace Tests\Feature\TargetRenstra;

use App\Actions\TargetRenstra\SimpanRevisiTargetRenstra;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use App\Services\Authorization\PermissionCatalog;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TargetRenstraFixtures;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class RevisiTargetRenstraTest extends TestCase
{
    use RefreshDatabase, TargetRenstraFixtures, TargetTahunanFixtures;

    private const KONFLIK = 'Draf revisi sudah berubah atau tidak lagi terbuka. Muat ulang untuk melanjutkan.';

    private const HANYA_AKTIF = 'Mode revisi hanya tersedia untuk Renstra aktif.';

    /** @return array{0: User, 1: Renstra, 2: IndikatorKinerja, 3: IndikatorKinerja} */
    private function renstraAktif(): array
    {
        $aktor = $this->calendarActor(['indikator:read', 'target:update']);
        $renstra = $this->calendarRenstra($aktor, Renstra::STATUS_AKTIF);
        $satu = $this->targetIndicator($aktor, $renstra);
        $dua = $this->targetIndicator($aktor, $renstra);
        TargetRenstra::create(['indikator_id' => $satu->id, 'tahun' => 2026, 'nilai' => '10', 'updated_by' => $aktor->id]);

        return [$aktor, $renstra, $satu, $dua];
    }

    private function draf(string $renstraId): object
    {
        return DB::table('revisi_target_renstra')->where('renstra_id', $renstraId)->sole();
    }

    /** @return array<string, ?string> */
    private function selDraf(string $revisiId): array
    {
        return DB::table('revisi_target_renstra_nilai')->where('revisi_id', $revisiId)->get()
            ->mapWithKeys(fn (object $row): array => ["{$row->indikator_id}:{$row->tahun}" => $row->nilai_baru])->all();
    }

    /** @return array<string, ?string> */
    private function targetBerlaku(): array
    {
        return DB::table('target_renstra')->get()->mapWithKeys(fn (object $row): array => ["{$row->indikator_id}:{$row->tahun}" => $row->nilai])->all();
    }

    private function auditDitolak(string $aksi, string $alasan): int
    {
        return AuditLog::where('tindakan', 'target_renstra.revisi_ditolak')->get()
            ->filter(fn (AuditLog $audit): bool => $audit->nilai_baru['aksi'] === $aksi && $audit->nilai_baru['alasan_penolakan'] === $alasan)->count();
    }

    private function halaman(User $pembaca, Renstra $renstra): TestResponse
    {
        return $this->actingAs($pembaca)->get('/perencanaan/sasaran-indikator?renstra_id='.$renstra->id)->assertOk();
    }

    public function test_mulai_membentuk_draf_di_atas_nilai_berlaku_dan_pengguna_lain_melanjutkan_draf_yang_sama(): void
    {
        [$aktor, $renstra, $satu] = $this->renstraAktif();
        $kedua = $this->calendarActor(['indikator:read', 'target:update']);
        $jumlahAudit = AuditLog::count();

        $this->mulaiRevisi($aktor, $renstra->id)->assertRedirect('/perencanaan/sasaran-indikator?renstra_id='.$renstra->id)->assertSessionHasNoErrors();
        $draf = $this->draf($renstra->id);
        $this->assertSame(['draf', 1, $aktor->id, null], [$draf->status, $draf->versi, $draf->dibuat_oleh, $draf->nomor]);
        $this->assertSame([], $this->selDraf($draf->id));

        $this->mulaiRevisi($kedua, $renstra->id)->assertSessionHasNoErrors();
        $this->assertSame([$draf->id, 1], [$this->draf($renstra->id)->id, $this->draf($renstra->id)->versi]);
        $this->assertSame($jumlahAudit, AuditLog::count());

        $this->halaman($kedua, $renstra)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('revisi_target.id', $draf->id)->where('revisi_target.versi', 1)->where('revisi_target.dibuat_oleh', $aktor->nama)
            ->where('revisi_target.justifikasi', null)->where('revisi_target.nilai', [])
            ->where('sasarans', fn (Collection $sasarans): bool => collect($sasarans->toArray())->flatMap(fn (array $s): array => $s['indikator_kinerjas'])
                ->firstWhere('id', $satu->id)['target_renstra'] === [['tahun' => 2026, 'nilai' => '10']]));
    }

    public function test_autosave_menyimpan_sel_dan_justifikasi_yang_bertahan_saat_halaman_dimuat_ulang(): void
    {
        [$aktor, $renstra, $satu, $dua] = $this->renstraAktif();
        $this->mulaiRevisi($aktor, $renstra->id);
        $jumlahAudit = AuditLog::count();

        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '12,5', 2027 => null], $dua->id => [2028 => '7']], ['justifikasi' => 'Target 2026 terlampaui.'])
            ->assertRedirect('/perencanaan/sasaran-indikator?renstra_id='.$renstra->id)->assertSessionHasNoErrors();
        $draf = $this->draf($renstra->id);
        $this->assertSame([2, 'Target 2026 terlampaui.'], [$draf->versi, $draf->justifikasi]);
        $this->assertEquals(["{$satu->id}:2026" => '12.500000000000', "{$satu->id}:2027" => null, "{$dua->id}:2028" => '7.000000000000'], $this->selDraf($draf->id));

        $this->simpanDrafRevisi($aktor, $renstra->id, 2, [$satu->id => [2026 => '13']])->assertSessionHasNoErrors();
        $draf = $this->draf($renstra->id);
        $this->assertSame([3, 'Target 2026 terlampaui.'], [$draf->versi, $draf->justifikasi]);
        $this->assertEquals(["{$satu->id}:2026" => '13.000000000000', "{$satu->id}:2027" => null, "{$dua->id}:2028" => '7.000000000000'], $this->selDraf($draf->id));

        // Nilai berlaku tidak tersentuh dan autosave tidak diaudit.
        $this->assertSame([2026 => '10.000000000000'], DB::table('target_renstra')->pluck('nilai', 'tahun')->all());
        $this->assertSame($jumlahAudit, AuditLog::count());

        $this->halaman($aktor, $renstra)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('revisi_target.versi', 3)->where('revisi_target.justifikasi', 'Target 2026 terlampaui.')
            ->where("revisi_target.nilai.{$satu->id}.2026", '13')->where("revisi_target.nilai.{$satu->id}.2027", null)
            ->where("revisi_target.nilai.{$dua->id}.2028", '7'));
    }

    /** Draf pengganti mulai lagi dari versi 1, sehingga tab dari draf lama dapat membawa nomor versi yang sama. */
    #[DataProvider('aksiDariDrafLama')]
    public function test_aksi_dari_draf_yang_sudah_diganti_ditolak_409_walau_nomor_versinya_sama(string $aksi): void
    {
        [$aktor, $renstra, $satu] = $this->renstraAktif();
        $kedua = $this->calendarActor(['indikator:read', 'target:update']);
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '11']])->assertSessionHasNoErrors();
        $lama = $this->draf($renstra->id)->id;
        $this->batalRevisi($kedua, $renstra->id, 2)->assertSessionHasNoErrors();
        $this->mulaiRevisi($kedua, $renstra->id)->assertSessionHasNoErrors();
        $this->simpanDrafRevisi($kedua, $renstra->id, 1, [$satu->id => [2026 => '50']], ['justifikasi' => 'Draf B.'])->assertSessionHasNoErrors();
        $baru = $this->draf($renstra->id);
        $this->assertSame([2, false], [(int) $baru->versi, $baru->id === $lama]);

        $respons = match ($aksi) {
            'draf' => $this->simpanDrafRevisi($aktor, $renstra->id, 2, [$satu->id => [2026 => '77']], ['revisi_id' => $lama, 'justifikasi' => 'Tab A.']),
            'simpan' => $this->simpanRevisi($aktor, $renstra->id, 2, 'Justifikasi dari tab A.', ['revisi_id' => $lama]),
            'batal' => $this->batalRevisi($aktor, $renstra->id, 2, ['revisi_id' => $lama]),
        };

        $respons->assertSessionHasErrors(['versi' => self::KONFLIK]);
        $this->assertSame(1, $this->auditDitolak($aksi, 'versi_usang'));
        $draf = $this->draf($renstra->id);
        $this->assertSame([$baru->id, 'draf', 2, 'Draf B.'], [$draf->id, $draf->status, (int) $draf->versi, $draf->justifikasi]);
        $this->assertEquals(["{$satu->id}:2026" => '50.000000000000'], $this->selDraf($baru->id));
        $this->assertEquals(["{$satu->id}:2026" => '10.000000000000'], $this->targetBerlaku());

        // Token tanpa identitas draf ditolak; token draf yang berlaku tetap diterima.
        $this->simpanDrafRevisi($kedua, $renstra->id, 2, [$satu->id => [2026 => '51']], ['revisi_id' => null])->assertSessionHasErrors(['revisi_id']);
        $this->simpanDrafRevisi($kedua, $renstra->id, 2, [$satu->id => [2026 => '51']])->assertSessionHasNoErrors();
        $this->assertEquals(["{$satu->id}:2026" => '51.000000000000'], $this->selDraf($baru->id));
    }

    public static function aksiDariDrafLama(): array
    {
        return ['autosave' => ['draf'], 'simpan' => ['simpan'], 'batal' => ['batal']];
    }

    public function test_versi_usang_atau_draf_yang_tidak_ada_ditolak_409_tanpa_mengubah_draf(): void
    {
        [$aktor, $renstra, $satu] = $this->renstraAktif();
        $kedua = $this->calendarActor(['indikator:read', 'target:update']);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '11']])->assertSessionHasErrors(['versi' => self::KONFLIK]);
        $this->assertSame(0, DB::table('revisi_target_renstra')->count());

        $this->mulaiRevisi($aktor, $renstra->id);
        $this->mulaiRevisi($kedua, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '11']])->assertSessionHasNoErrors();
        $this->actingAs($kedua)->putJson("/perencanaan/renstra/{$renstra->id}/revisi-target", ['revisi_id' => $this->idDrafTerbuka($renstra->id), 'versi' => 1, 'nilai' => [$satu->id => [2026 => '99']]])
            ->assertStatus(409)->assertJsonValidationErrors(['versi' => self::KONFLIK]);
        $this->withHeader('X-Inertia', 'true');
        $this->simpanDrafRevisi($kedua, $renstra->id, 1, [$satu->id => [2026 => '99']])->assertStatus(303)->assertSessionHasErrors(['versi']);
        $this->flushHeaders();

        $draf = $this->draf($renstra->id);
        $this->assertSame(2, $draf->versi);
        $this->assertEquals(["{$satu->id}:2026" => '11.000000000000'], $this->selDraf($draf->id));
        $this->assertSame(3, $this->auditDitolak('draf', 'versi_usang'));

        // Kontrol positif: pengguna kedua melanjutkan dari versi terbaru.
        $this->simpanDrafRevisi($kedua, $renstra->id, 2, [$satu->id => [2026 => '12']])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->draf($renstra->id)->versi);
    }

    public function test_sel_di_luar_renstra_tahun_berlaku_format_presisi_atau_indikator_arsip_ditolak_utuh(): void
    {
        [$aktor, $renstra, $satu, $dua] = $this->renstraAktif();
        $tiga = $this->targetIndicator($aktor, $renstra);
        $luar = $this->targetIndicator($aktor);
        $this->mulaiRevisi($aktor, $renstra->id);
        $dua->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

        foreach ([
            [[$satu->id => [2030 => '1']], "nilai.{$satu->id}.2030"],
            [[$satu->id => [2025 => '1']], "nilai.{$satu->id}.2025"],
            [[$satu->id => [2026 => 'abc']], "nilai.{$satu->id}.2026"],
            [[$satu->id => [2026 => '1.234']], "nilai.{$satu->id}.2026"],
            [[$luar->id => [2026 => '1']], "nilai.{$luar->id}"],
            [[$dua->id => [2026 => '1']], "nilai.{$dua->id}"],
            [['bukan-uuid' => [2026 => '1']], 'nilai'],
        ] as [$nilai, $kunci]) {
            $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$tiga->id => [2026 => '5'], ...$nilai])->assertSessionHasErrors([$kunci]);
        }
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$tiga->id => [2026 => '5']], ['alasan' => 'Revisi'])
            ->assertSessionHasErrors(['nilai' => 'Permintaan memuat field yang tidak didukung.']);

        $draf = $this->draf($renstra->id);
        $this->assertSame(1, $draf->versi);
        $this->assertSame([], $this->selDraf($draf->id));
        $this->assertSame(8, $this->auditDitolak('draf', 'input_atau_state_tidak_valid'));

        // Kontrol positif: sel sah yang sama diterima.
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$tiga->id => [2026 => '5']])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->draf($renstra->id)->versi);
    }

    public function test_simpan_menerapkan_seluruh_perubahan_sekaligus_ke_histori_bernomor_dengan_audit_per_sel(): void
    {
        [$aktor, $renstra, $satu, $dua] = $this->renstraAktif();
        TargetRenstra::create(['indikator_id' => $satu->id, 'tahun' => 2027, 'nilai' => '20', 'updated_by' => $aktor->id]);
        $justifikasi = "Target 2026 terlampaui sebelum akhir periode.\nRujukan: notulen rapat evaluasi.";
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '12', 2027 => '20,0', 2028 => null], $dua->id => [2026 => '5']])->assertSessionHasNoErrors();
        $sebelum = AuditLog::pluck('id');

        $this->simpanRevisi($aktor, $renstra->id, 2, $justifikasi)->assertRedirect('/perencanaan/sasaran-indikator?renstra_id='.$renstra->id)
            ->assertSessionHasNoErrors()->assertSessionHas('inertia.flash_data.success', 'Revisi ke-1 target Renstra tersimpan.');

        $this->assertEquals(["{$satu->id}:2026" => '12.000000000000', "{$satu->id}:2027" => '20.000000000000', "{$dua->id}:2026" => '5.000000000000'], $this->targetBerlaku());
        $revisi = DB::table('revisi_target_renstra')->where('renstra_id', $renstra->id)->sole();
        $this->assertSame(['disimpan', 1, $justifikasi, $aktor->id], [$revisi->status, $revisi->nomor, $revisi->justifikasi, $revisi->disimpan_oleh]);
        $this->assertNotNull($revisi->disimpan_pada);
        $this->assertEquals(["{$satu->id}:2026" => ['10.000000000000', '12.000000000000'], "{$dua->id}:2026" => [null, '5.000000000000']],
            DB::table('revisi_target_renstra_nilai')->where('revisi_id', $revisi->id)->get()
                ->mapWithKeys(fn (object $r): array => ["{$r->indikator_id}:{$r->tahun}" => [$r->nilai_lama, $r->nilai_baru]])->all());

        $audit = AuditLog::where('tindakan', 'target_renstra.simpan')->whereNotIn('id', $sebelum)->get();
        $this->assertCount(2, $audit);
        $this->assertSame(2, AuditLog::whereNotIn('id', $sebelum)->count());
        $perSel = $audit->keyBy(fn (AuditLog $a): string => $a->nilai_baru['indikator_id']);
        $this->assertEquals(['indikator_id' => $satu->id, 'tahun' => 2026, 'nilai' => '10'], $perSel[$satu->id]->nilai_lama);
        $this->assertEquals(['indikator_id' => $satu->id, 'tahun' => 2026, 'nilai' => '12', 'revisi_nomor' => 1], $perSel[$satu->id]->nilai_baru);
        $this->assertNull($perSel[$dua->id]->nilai_lama);
        foreach ($audit as $baris) {
            $this->assertSame([$aktor->id, 'target_renstra', $justifikasi], [$baris->actor_id, $baris->objek_tipe, $baris->alasan]);
            $this->assertArrayHasKey('indikator:read', $baris->dasar_izin);
            $this->assertArrayHasKey('target:update', $baris->dasar_izin);
        }

        // Revisi berikutnya membuka draf baru dan bernomor 2.
        $this->mulaiRevisi($aktor, $renstra->id)->assertSessionHasNoErrors();
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '13']])->assertSessionHasNoErrors();
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Penyesuaian lanjutan.')->assertSessionHas('inertia.flash_data.success', 'Revisi ke-2 target Renstra tersimpan.');
        $this->assertSame([1, 2], DB::table('revisi_target_renstra')->where('renstra_id', $renstra->id)->orderBy('nomor')->pluck('nomor')->all());
        $this->assertSame('13.000000000000', DB::table('target_renstra')->where('indikator_id', $satu->id)->where('tahun', 2026)->value('nilai'));
    }

    public function test_simpan_tanpa_justifikasi_ditolak_diaudit_dan_target_tidak_berubah(): void
    {
        [$aktor, $renstra, $satu] = $this->renstraAktif();
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '12']])->assertSessionHasNoErrors();

        foreach ([null, '', "  \n\t "] as $kosong) {
            $this->simpanRevisi($aktor, $renstra->id, 2, $kosong)->assertSessionHasErrors(['justifikasi' => 'Justifikasi revisi wajib diisi.']);
        }
        $this->simpanRevisi($aktor, $renstra->id, 2, "Teks\x07rusak")->assertSessionHasErrors(['justifikasi']);
        $this->simpanRevisi($aktor, $renstra->id, 2, str_repeat('a', 1001))->assertSessionHasErrors(['justifikasi']);
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Sah', ['alasan' => 'Lain'])->assertSessionHasErrors(['nilai' => 'Permintaan memuat field yang tidak didukung.']);

        $this->assertSame([2026 => '10.000000000000'], DB::table('target_renstra')->pluck('nilai', 'tahun')->all());
        $this->assertSame(['draf', 2], [$this->draf($renstra->id)->status, $this->draf($renstra->id)->versi]);
        $this->assertSame(6, $this->auditDitolak('simpan', 'input_atau_state_tidak_valid'));
        $this->assertSame(0, AuditLog::where('tindakan', 'target_renstra.simpan')->count());

        // Kontrol positif: justifikasi terisi menyimpan revisi.
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Target terlampaui.')->assertSessionHasNoErrors();
        $this->assertSame([2026 => '12.000000000000'], DB::table('target_renstra')->pluck('nilai', 'tahun')->all());
    }

    public function test_simpan_tanpa_perubahan_nilai_ditolak(): void
    {
        [$aktor, $renstra, $satu] = $this->renstraAktif();
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanRevisi($aktor, $renstra->id, 1, 'Tidak ada yang diubah.')->assertSessionHasErrors(['nilai' => 'Tidak ada nilai yang berubah, revisi tidak dapat disimpan.']);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '10,00', 2027 => null]])->assertSessionHasNoErrors();
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Nilai sama dengan yang berlaku.')->assertSessionHasErrors(['nilai' => 'Tidak ada nilai yang berubah, revisi tidak dapat disimpan.']);

        $this->assertSame(['draf', 2], [$this->draf($renstra->id)->status, $this->draf($renstra->id)->versi]);
        $this->assertSame(2, $this->auditDitolak('simpan', 'input_atau_state_tidak_valid'));
        $this->assertSame(0, AuditLog::where('tindakan', 'target_renstra.simpan')->count());
    }

    public function test_simpan_dari_versi_usang_atau_dengan_indikator_yang_diarsipkan_ditolak_utuh(): void
    {
        [$aktor, $renstra, $satu, $dua] = $this->renstraAktif();
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '12'], $dua->id => [2026 => '5']])->assertSessionHasNoErrors();

        $this->simpanRevisi($aktor, $renstra->id, 1, 'Dari tampilan lama.')->assertSessionHasErrors(['versi' => self::KONFLIK]);
        $dua->update(['status' => IndikatorKinerja::STATUS_ARSIP]);
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Indikator diarsipkan.')->assertSessionHasErrors(["nilai.{$dua->id}" => 'Indikator arsip hanya dapat dibaca.']);

        $this->assertSame([2026 => '10.000000000000'], DB::table('target_renstra')->pluck('nilai', 'tahun')->all());
        $this->assertSame(['draf', 2], [$this->draf($renstra->id)->status, $this->draf($renstra->id)->versi]);
        $this->assertSame([1, 1], [$this->auditDitolak('simpan', 'versi_usang'), $this->auditDitolak('simpan', 'input_atau_state_tidak_valid')]);

        // Revisi yang sudah disimpan tidak dapat disimpan ulang dari versi yang sama.
        $dua->update(['status' => IndikatorKinerja::STATUS_AKTIF]);
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Indikator aktif kembali.')->assertSessionHasNoErrors();
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Kirim ulang.')->assertSessionHasErrors(['versi' => self::KONFLIK]);
        $this->assertSame(1, DB::table('revisi_target_renstra')->count());
    }

    public function test_batal_membuang_draf_beserta_isiannya_tercatat_di_audit_dan_target_tidak_berubah(): void
    {
        [$aktor, $renstra, $satu, $dua] = $this->renstraAktif();
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '12'], $dua->id => [2027 => '3']], ['justifikasi' => 'Draf justifikasi.'])->assertSessionHasNoErrors();
        $draf = $this->draf($renstra->id);

        $this->batalRevisi($aktor, $renstra->id, 1)->assertSessionHasErrors(['versi' => self::KONFLIK]);
        $this->batalRevisi($aktor, $renstra->id, 2, ['alasan' => 'Lain'])->assertSessionHasErrors(['nilai' => 'Permintaan memuat field yang tidak didukung.']);
        $this->assertSame(1, DB::table('revisi_target_renstra')->count());
        $this->assertSame([1, 1], [$this->auditDitolak('batal', 'versi_usang'), $this->auditDitolak('batal', 'input_atau_state_tidak_valid')]);
        $sebelum = AuditLog::pluck('id');

        $this->batalRevisi($aktor, $renstra->id, 2)->assertRedirect('/perencanaan/sasaran-indikator?renstra_id='.$renstra->id)
            ->assertSessionHasNoErrors()->assertSessionHas('inertia.flash_data.success', 'Draf revisi target Renstra dibatalkan.');
        $this->assertSame([0, 0], [DB::table('revisi_target_renstra')->count(), DB::table('revisi_target_renstra_nilai')->count()]);
        $this->assertEquals(["{$satu->id}:2026" => '10.000000000000'], $this->targetBerlaku());

        $audit = AuditLog::whereNotIn('id', $sebelum)->sole();
        $this->assertSame(['target_renstra.revisi_batal', 'revisi_target_renstra', $draf->id, $aktor->id], [$audit->tindakan, $audit->objek_tipe, $audit->objek_id, $audit->actor_id]);
        $sel = collect([['indikator_id' => $satu->id, 'tahun' => 2026, 'nilai' => '12'], ['indikator_id' => $dua->id, 'tahun' => 2027, 'nilai' => '3']])
            ->sortBy(fn (array $s): string => $s['indikator_id'].$s['tahun'])->values()->all();
        $this->assertEquals(['renstra_id' => $renstra->id, 'versi' => 2, 'justifikasi' => 'Draf justifikasi.', 'sel' => $sel], $audit->nilai_lama);
        $this->assertArrayHasKey('target:update', $audit->dasar_izin);

        // Draf yang sudah dibuang tidak dapat dibatalkan lagi; Mulai membuka draf baru yang kosong.
        $this->batalRevisi($aktor, $renstra->id, 2)->assertSessionHasErrors(['versi' => self::KONFLIK]);
        $this->mulaiRevisi($aktor, $renstra->id)->assertSessionHasNoErrors();
        $baru = $this->draf($renstra->id);
        $this->assertNotSame($draf->id, $baru->id);
        $this->assertSame([1, null, []], [$baru->versi, $baru->justifikasi, $this->selDraf($baru->id)]);
    }

    public function test_selama_draf_terbuka_halaman_dan_pembanding_target_pk_memakai_nilai_berlaku(): void
    {
        [$aktor, $renstra, $satu] = $this->renstraAktif();
        $satu->update(['arah' => 'naik_baik']);
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '99']])->assertSessionHasNoErrors();

        $this->halaman($aktor, $renstra)->assertInertia(fn (AssertableInertia $page) => $page
            ->where("revisi_target.nilai.{$satu->id}.2026", '99')
            ->where('sasarans', fn (Collection $sasarans): bool => collect($sasarans->toArray())->flatMap(fn (array $s): array => $s['indikator_kinerjas'])
                ->firstWhere('id', $satu->id)['target_renstra'] === [['tahun' => 2026, 'nilai' => '10']]));
        $editor = $this->actingAs($aktor)->getJson($this->targetPkUrl($satu, '/editor'))->assertOk()
            ->assertJsonPath('target_renstra', '10')->assertJsonPath('status_deviasi', 'tidak_dinilai');

        // 50 menyimpang terhadap draf (99) tetapi tidak terhadap nilai berlaku (10), sehingga tersimpan tanpa alasan deviasi.
        $this->putJson($this->targetPkUrl($satu), ['baseline' => null, 'target_tahunan' => '50', 'expected_state' => $editor->json('expected_state'),
            'operation_id' => (string) Str::uuid()])->assertOk();
        $this->assertSame(['50.000000000000', null], [DB::table('target_kinerjas')->value('target_tahunan'), DB::table('target_kinerjas')->value('alasan_deviasi_renstra')]);
        $this->get('/perjanjian-kinerja/'.RenstraPk::sole()->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('target_pk', fn (Collection $kelompok): bool => collect($kelompok->toArray())->flatMap(fn (array $k): array => $k['indikator'])
                ->firstWhere('id', $satu->id)['target_renstra'] === '10'));
        $this->assertSame(['draf', 2], [$this->draf($renstra->id)->status, $this->draf($renstra->id)->versi]);
    }

    public function test_histori_memuat_revisi_tersimpan_terbaru_lebih_dulu_tanpa_draf_dan_data_sensitif(): void
    {
        [$aktor, $renstra, $satu, $dua] = $this->renstraAktif();
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '12'], $dua->id => [2027 => '3']])->assertSessionHasNoErrors();
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Revisi pertama.')->assertSessionHasNoErrors();
        $this->travel(1)->minutes();
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '13']])->assertSessionHasNoErrors();
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Revisi kedua.')->assertSessionHasNoErrors();
        $this->mulaiRevisi($aktor, $renstra->id);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '99']], ['justifikasi' => 'Draf belum disimpan.'])->assertSessionHasNoErrors();
        // Role bersama disinkron ulang setelah semua tulisan: pembaca hanya memegang indikator:read.
        $pembaca = $this->calendarActor(['indikator:read']);

        $response = $this->actingAs($pembaca)->getJson("/perencanaan/renstra/{$renstra->id}/revisi-target/histori")->assertOk()
            ->assertJsonCount(2, 'items')->assertJsonPath('terpotong', false);
        $items = $response->json('items');
        $this->assertSame(['nomor', 'waktu', 'aktor', 'justifikasi', 'perubahan'], array_keys($items[0]));
        $this->assertSame([2, $aktor->nama, 'Revisi kedua.'], [$items[0]['nomor'], $items[0]['aktor'], $items[0]['justifikasi']]);
        $this->assertSame([['indikator' => ['kode' => $satu->kode, 'nama' => $satu->nama, 'desimal_tampilan' => $satu->fresh()->desimal_tampilan], 'tahun' => 2026, 'nilai_lama' => '12', 'nilai_baru' => '13']], $items[0]['perubahan']);
        $this->assertSame([1, 'Revisi pertama.'], [$items[1]['nomor'], $items[1]['justifikasi']]);
        $this->assertEqualsCanonicalizing([
            ['indikator' => ['kode' => $satu->kode, 'nama' => $satu->nama, 'desimal_tampilan' => $satu->fresh()->desimal_tampilan], 'tahun' => 2026, 'nilai_lama' => '10', 'nilai_baru' => '12'],
            ['indikator' => ['kode' => $dua->kode, 'nama' => $dua->nama, 'desimal_tampilan' => $dua->fresh()->desimal_tampilan], 'tahun' => 2027, 'nilai_lama' => null, 'nilai_baru' => '3'],
        ], $items[1]['perubahan']);
        $this->assertGreaterThan($items[1]['waktu'], $items[0]['waktu']);
        foreach ([$aktor->email, 'dasar_izin', 'Draf belum disimpan.'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $response->getContent());
        }

        $this->actingAs($pembaca)->getJson('/perencanaan/renstra/'.Str::uuid().'/revisi-target/histori')->assertNotFound();
        $this->actingAs($this->calendarActor([]))->getJson("/perencanaan/renstra/{$renstra->id}/revisi-target/histori")->assertForbidden();
    }

    public function test_riwayat_per_indikator_tetap_memuat_perubahan_sebelum_dan_sesudah_mode_revisi(): void
    {
        $aktor = $this->calendarActor(['indikator:read', 'target:update']);
        $indikator = $this->targetIndicator($aktor);
        $renstraId = $indikator->sasaranStrategis->renstra_id;
        $this->simpanTargetRenstra($aktor, $indikator, [2026 => '10'])->assertSessionHasNoErrors();
        $this->travel(1)->minutes();
        $this->ubahStatusRenstra($indikator, Renstra::STATUS_AKTIF);
        $this->mulaiRevisi($aktor, $renstraId);
        $this->simpanDrafRevisi($aktor, $renstraId, 1, [$indikator->id => [2026 => '12']])->assertSessionHasNoErrors();
        $this->simpanRevisi($aktor, $renstraId, 2, 'Justifikasi tidak tampil di riwayat.')->assertSessionHasNoErrors();

        $response = $this->actingAs($aktor)->getJson("/perencanaan/indikator/{$indikator->id}/target-renstra/riwayat")->assertOk()->assertJsonCount(2, 'items');
        $this->assertSame([['12', '10'], ['10', null]], array_map(fn (array $i): array => [$i['nilai_baru'], $i['nilai_lama']], $response->json('items')));
        $this->assertStringNotContainsString('Justifikasi tidak tampil', $response->getContent());
    }

    public function test_mode_revisi_hanya_untuk_renstra_aktif(): void
    {
        [$aktor, $renstra, $satu] = $this->renstraAktif();
        $this->mulaiRevisi($aktor, (string) Str::uuid())->assertNotFound();
        foreach ([Renstra::STATUS_DRAFT, Renstra::STATUS_NONAKTIF] as $status) {
            $this->ubahStatusRenstra($satu, $status);
            $this->mulaiRevisi($aktor, $renstra->id)->assertSessionHasErrors(['renstra' => self::HANYA_AKTIF]);
        }
        $this->assertSame(0, DB::table('revisi_target_renstra')->count());

        $this->ubahStatusRenstra($satu, Renstra::STATUS_AKTIF);
        $this->mulaiRevisi($aktor, $renstra->id)->assertSessionHasNoErrors();
        $this->ubahStatusRenstra($satu, Renstra::STATUS_NONAKTIF);
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '1']])->assertSessionHasErrors(['renstra' => self::HANYA_AKTIF]);
        $this->assertSame(1, $this->draf($renstra->id)->versi);
        $this->assertSame(2, $this->auditDitolak('mulai', 'input_atau_state_tidak_valid'));
        $this->assertSame(1, $this->auditDitolak('draf', 'input_atau_state_tidak_valid'));
    }

    /** @param list<string> $izin */
    #[DataProvider('izinKurang')]
    public function test_mulai_dan_autosave_membutuhkan_baca_indikator_dan_ubah_target(array $izin): void
    {
        [$penulis, $renstra, $satu] = $this->renstraAktif();
        $this->mulaiRevisi($penulis, $renstra->id)->assertSessionHasNoErrors();
        // Role bersama disinkron ulang di sini: penulis tidak dipakai lagi setelah baris ini.
        $aktor = $this->calendarActor($izin);

        $this->mulaiRevisi($aktor, $renstra->id)->assertForbidden();
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '1']])->assertForbidden();
        $this->simpanRevisi($aktor, $renstra->id, 1, 'Tanpa izin.')->assertForbidden();
        $this->batalRevisi($aktor, $renstra->id, 1)->assertForbidden();
        $this->assertSame(['draf', 1, []], [$this->draf($renstra->id)->status, $this->draf($renstra->id)->versi, $this->selDraf($this->draf($renstra->id)->id)]);
        $this->assertSame([1, 1, 1, 1], [$this->auditDitolak('mulai', 'izin_ditolak'), $this->auditDitolak('draf', 'izin_ditolak'),
            $this->auditDitolak('simpan', 'izin_ditolak'), $this->auditDitolak('batal', 'izin_ditolak')]);
        if (in_array('indikator:read', $izin, true)) {
            $this->halaman($aktor, $renstra)->assertInertia(fn (AssertableInertia $page) => $page->where('revisi_target', null));
        }
    }

    /** @return list<list<list<string>>> */
    public static function izinKurang(): array
    {
        return [[[]], [['indikator:read']], [['target:update']]];
    }

    #[DataProvider('izinRevisi')]
    public function test_deny_eksplisit_yang_cocok_menang_di_semua_endpoint_revisi(string $izin): void
    {
        [$aktor, $renstra, $satu] = $this->renstraAktif();
        $this->mulaiRevisi($aktor, $renstra->id)->assertSessionHasNoErrors();
        $this->simpanDrafRevisi($aktor, $renstra->id, 1, [$satu->id => [2026 => '12']])->assertSessionHasNoErrors();
        UserPermissionDeny::create(['user_id' => $aktor->id, 'permission_id' => Permission::where('kode', $izin)->sole()->id, 'alasan' => 'Fixture penolakan', 'ditetapkan_oleh' => $aktor->id]);

        $this->mulaiRevisi($aktor, $renstra->id)->assertForbidden();
        $this->simpanDrafRevisi($aktor, $renstra->id, 2, [$satu->id => [2026 => '1']])->assertForbidden();
        $this->simpanRevisi($aktor, $renstra->id, 2, 'Ditolak deny.')->assertForbidden();
        $this->batalRevisi($aktor, $renstra->id, 2)->assertForbidden();
        // Deny juga menang saat Action dipanggil langsung, tanpa HTTP.
        $this->assertThrows(fn () => app(SimpanRevisiTargetRenstra::class)->handle($aktor, $renstra->id, ['versi' => 2, 'justifikasi' => 'Langsung.']), AuthorizationException::class);

        $this->assertSame(['draf', 2], [$this->draf($renstra->id)->status, $this->draf($renstra->id)->versi]);
        $this->assertEquals(["{$satu->id}:2026" => '12.000000000000'], $this->selDraf($this->draf($renstra->id)->id));
        $this->assertEquals(["{$satu->id}:2026" => '10.000000000000'], $this->targetBerlaku());
        $ditolak = AuditLog::where('tindakan', 'target_renstra.revisi_ditolak')->get();
        $this->assertCount(5, $ditolak);
        foreach ($ditolak as $audit) {
            $this->assertSame('izin_ditolak', $audit->nilai_baru['alasan_penolakan']);
            $this->assertNotSame('diizinkan', $audit->dasar_izin[$izin]['keputusan']);
        }

        // Histori hanya butuh indikator:read, sehingga deny target:update tidak menutupnya (kontrol positif).
        $histori = $this->actingAs($aktor)->getJson("/perencanaan/renstra/{$renstra->id}/revisi-target/histori");
        $izin === 'indikator:read' ? $histori->assertForbidden() : $histori->assertOk();
        if ($izin === 'target:update') {
            $this->halaman($aktor, $renstra)->assertInertia(fn (AssertableInertia $page) => $page->where('revisi_target', null));
        }
    }

    /** @return list<list<string>> */
    public static function izinRevisi(): array
    {
        return [['indikator:read'], ['target:update']];
    }

    public function test_pj_pegawai_dengan_seluruh_izin_unit_ditolak_di_semua_endpoint_revisi(): void
    {
        [$penulis, $renstra, $satu] = $this->renstraAktif();
        $this->mulaiRevisi($penulis, $renstra->id)->assertSessionHasNoErrors();
        $this->simpanDrafRevisi($penulis, $renstra->id, 1, [$satu->id => [2026 => '12']])->assertSessionHasNoErrors();
        $this->seed(AccessCatalogSeeder::class);
        $pic = User::factory()->create(['status' => 'aktif']);
        $pic->roles()->attach(Role::where('kode', 'pegawai')->sole()->id, ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $penulis->id, 'created_at' => now()]);
        PenugasanIndikator::create(['indikator_id' => $satu->id, 'user_id' => $pic->id, 'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $penulis->id, 'created_at' => now()]);
        foreach (PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS as $kode) {
            UserPermissionGrant::create(['user_id' => $pic->id, 'permission_id' => Permission::where('kode', $kode)->sole()->id, 'unit_id' => $satu->unit_id,
                'alasan' => 'Fixture izin unit PJ', 'diberikan_oleh' => $penulis->id]);
        }

        $this->mulaiRevisi($pic, $renstra->id)->assertForbidden();
        $this->simpanDrafRevisi($pic, $renstra->id, 2, [$satu->id => [2026 => '99']])->assertForbidden();
        $this->simpanRevisi($pic, $renstra->id, 2, 'Oleh PJ.')->assertForbidden();
        $this->batalRevisi($pic, $renstra->id, 2)->assertForbidden();
        $this->actingAs($pic)->getJson("/perencanaan/renstra/{$renstra->id}/revisi-target/histori")->assertForbidden();

        $this->assertSame(['draf', 2], [$this->draf($renstra->id)->status, $this->draf($renstra->id)->versi]);
        $this->assertEquals(["{$satu->id}:2026" => '12.000000000000'], $this->selDraf($this->draf($renstra->id)->id));
        $this->assertEquals(["{$satu->id}:2026" => '10.000000000000'], $this->targetBerlaku());
        $this->assertSame(4, AuditLog::where('tindakan', 'target_renstra.revisi_ditolak')->where('actor_id', $pic->id)->count());
    }
}
