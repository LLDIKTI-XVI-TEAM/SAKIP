<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai lifecycle indikator yang dibekukan saat cutover (pengganti is_aktif).
     */
    private const STATUS_AKTIF = 'aktif';

    private const STATUS_ARSIP = 'arsip';

    /**
     * Jejak audit sumber backfill created_by (objek_tipe + tindakan).
     */
    private const AUDIT_OBJEK_TIPE = 'indikator';

    private const AUDIT_TINDAKAN_BUAT = 'indikator.buat';

    /**
     * Tabel backup nilai is_aktif asli per baris. Dibuat di up() SEBELUM
     * kolom is_aktif di-drop; dipakai down() untuk restore eksak.
     */
    private const BACKUP_TABLE = '_backup_indikator_is_aktif_20260930';

    /**
     * Batas contoh id dalam pesan gagal backfill/rollback agar pesan tetap
     * terbaca pada dataset besar (total baris selalu ditampilkan).
     */
    private const MAX_SAMPLE_IDS = 20;

    /**
     * Cutover lifecycle indikator: status + tahun_mulai_berlaku + created_by,
     * lalu sunset kolom is_aktif.
     *
     * Fail-closed: is_aktif NULL, tahun_mulai_berlaku NULL, dan created_by
     * tanpa jejak audit membuat migrasi THROW — tidak ada yang diam-diam
     * diasumsikan aktif atau diisi pengguna fallback. Operator perbaiki data
     * manual lalu jalankan ulang migrate.
     */
    public function up(): void
    {
        // 1. Kolom status (pengganti is_aktif) + CHECK eksplisit.
        if (! Schema::hasColumn('indikator_kinerjas', 'status')) {
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->string('status', 20)->default(self::STATUS_AKTIF);
            });
        }

        if (Schema::hasColumn('indikator_kinerjas', 'is_aktif')) {
            // Fail-closed: is_aktif NULL tidak diasumsikan aktif.
            $nullIsAktifCount = DB::table('indikator_kinerjas')->whereNull('is_aktif')->count();

            if ($nullIsAktifCount > 0) {
                $idList = DB::table('indikator_kinerjas')
                    ->whereNull('is_aktif')
                    ->limit(self::MAX_SAMPLE_IDS)
                    ->pluck('id')
                    ->map(fn ($id) => "'{$id}'")
                    ->implode(', ');

                throw new RuntimeException(sprintf(
                    'Backfill status gagal: %d indikator memiliki is_aktif NULL (contoh id: %s). '
                    .'Nilai NULL tidak diasumsikan aktif (fail-closed). Perbaiki manual, mis. '
                    .'UPDATE indikator_kinerjas SET is_aktif = true WHERE id IN (...); '
                    .'lalu jalankan ulang php artisan migrate.',
                    $nullIsAktifCount,
                    $idList
                ));
            }

            DB::table('indikator_kinerjas')
                ->where('is_aktif', true)
                ->update(['status' => self::STATUS_AKTIF]);

            DB::table('indikator_kinerjas')
                ->where('is_aktif', false)
                ->update(['status' => self::STATUS_ARSIP]);

            DB::table('indikator_kinerjas')
                ->whereNotIn('status', [self::STATUS_AKTIF, self::STATUS_ARSIP])
                ->update(['status' => self::STATUS_AKTIF]);
        }

        DB::statement('ALTER TABLE indikator_kinerjas DROP CONSTRAINT IF EXISTS indikator_kinerjas_status_check');
        DB::statement(sprintf(
            "ALTER TABLE indikator_kinerjas ADD CONSTRAINT indikator_kinerjas_status_check CHECK (status IN ('%s', '%s'))",
            self::STATUS_AKTIF,
            self::STATUS_ARSIP
        ));

        // 2. Tahun mulai berlaku, dibackfill dari renstra.tahun_mulai via sasaran.
        if (! Schema::hasColumn('indikator_kinerjas', 'tahun_mulai_berlaku')) {
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->integer('tahun_mulai_berlaku')->nullable();
            });
        }

        DB::statement(<<<'SQL'
            UPDATE indikator_kinerjas AS ik
            SET tahun_mulai_berlaku = r.tahun_mulai
            FROM sasaran_strategis AS s
            JOIN renstras AS r ON r.id = s.renstra_id
            WHERE s.id = ik.sasaran_strategis_id
            AND ik.tahun_mulai_berlaku IS NULL
            SQL);

        $nullTahunCount = DB::table('indikator_kinerjas')->whereNull('tahun_mulai_berlaku')->count();

        if ($nullTahunCount > 0) {
            $idList = DB::table('indikator_kinerjas')
                ->whereNull('tahun_mulai_berlaku')
                ->limit(self::MAX_SAMPLE_IDS)
                ->pluck('id')
                ->map(fn ($id) => "'{$id}'")
                ->implode(', ');

            throw new RuntimeException(sprintf(
                'Backfill tahun_mulai_berlaku gagal: %d indikator tanpa renstra.tahun_mulai via sasaran strategis (contoh id: %s). '
                .'Nilai NULL tidak diisi default (fail-closed). Perbaiki manual, mis. '
                .'UPDATE indikator_kinerjas SET tahun_mulai_berlaku = <tahun> WHERE id IN (...); '
                .'lalu jalankan ulang php artisan migrate.',
                $nullTahunCount,
                $idList
            ));
        }

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->integer('tahun_mulai_berlaku')->nullable(false)->change();
        });

        // 3. created_by wajib (FK users, RESTRICT), dibackfill dari audit tertua.
        //
        // Prosedur backfill manual bila migrasi throw (tanpa jejak audit):
        //   1. Catat daftar id indikator pada pesan error.
        //   2. Tentukan UUID pemilik yang benar per indikator dari arsip operasional.
        //   3. UPDATE indikator_kinerjas SET created_by = '<uuid-pemilik>' WHERE id IN (...);
        //      (satu UPDATE per pemilik bila pemiliknya berbeda).
        //   4. Jalankan ulang php artisan migrate.
        // Sengaja tanpa fallback pengguna otomatis: mengarang pemilik merusak
        // provenance created_by.
        if (! Schema::hasColumn('indikator_kinerjas', 'created_by')) {
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->uuid('created_by')->nullable();
            });
        }

        DB::statement(sprintf(
            <<<'SQL'
            UPDATE indikator_kinerjas AS ik
            SET created_by = audit_tertua.actor_id
            FROM (
                SELECT DISTINCT ON (objek_id) objek_id, actor_id
                FROM audit_log
                WHERE objek_tipe = '%s' AND tindakan = '%s' AND actor_id IS NOT NULL
                ORDER BY objek_id, waktu ASC, id ASC
            ) AS audit_tertua
            WHERE audit_tertua.objek_id = ik.id
            AND ik.created_by IS NULL
            SQL,
            self::AUDIT_OBJEK_TIPE,
            self::AUDIT_TINDAKAN_BUAT
        ));

        $nullCreatedByCount = DB::table('indikator_kinerjas')->whereNull('created_by')->count();

        if ($nullCreatedByCount > 0) {
            $idList = DB::table('indikator_kinerjas')
                ->whereNull('created_by')
                ->limit(self::MAX_SAMPLE_IDS)
                ->pluck('id')
                ->map(fn ($id) => "'{$id}'")
                ->implode(', ');

            throw new RuntimeException(sprintf(
                'Backfill created_by gagal: %d indikator tanpa jejak audit indikator.buat (contoh id: %s). '
                .'Tidak ada pengisian pengguna otomatis. Cara backfill manual: tentukan UUID pemilik yang benar '
                ."per indikator dari arsip operasional, lalu UPDATE indikator_kinerjas SET created_by = '<uuid-pemilik>' "
                .'WHERE id IN (%s); (satu UPDATE per pemilik bila pemiliknya berbeda). '
                .'Setelah data diperbaiki, jalankan ulang php artisan migrate.',
                $nullCreatedByCount,
                $idList,
                $idList
            ));
        }

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->uuid('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        // 4. Backup nilai is_aktif asli per baris SEBELUM drop; dipakai down()
        //    untuk restore eksak (bukan derivasi dari status).
        if (Schema::hasColumn('indikator_kinerjas', 'is_aktif')) {
            DB::statement(sprintf('DROP TABLE IF EXISTS "%s"', self::BACKUP_TABLE));
            DB::statement(sprintf(
                'CREATE TABLE "%s" AS SELECT id AS indikator_id, is_aktif FROM indikator_kinerjas',
                self::BACKUP_TABLE
            ));

            // 5. Sunset is_aktif: seluruh pembaca pindah ke status (R2-04c).
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->dropColumn('is_aktif');
            });
        }
    }

    /**
     * Kembalikan is_aktif per baris dari tabel backup, lalu hapus tabel
     * backup + kolom lifecycle baru.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('indikator_kinerjas', 'is_aktif')) {
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->boolean('is_aktif')->nullable();
            });
        }

        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            throw new RuntimeException(sprintf(
                'Rollback gagal: tabel backup "%s" tidak ditemukan. Tabel ini dibuat oleh up() '
                .'SEBELUM kolom is_aktif di-drop. Tanpa tabel backup, nilai is_aktif asli tidak dapat '
                .'dikembalikan (down() tidak menderivasi dari status). Pulihkan tabel backup '
                .'(kolom indikator_id + is_aktif) dari arsip database lalu jalankan ulang rollback.',
                self::BACKUP_TABLE
            ));
        }

        DB::statement(sprintf(
            <<<'SQL'
            UPDATE indikator_kinerjas AS ik
            SET is_aktif = backup.is_aktif
            FROM "%s" AS backup
            WHERE backup.indikator_id = ik.id
            SQL,
            self::BACKUP_TABLE
        ));

        // Fail-closed: baris tanpa pasangan backup (mis. dibuat setelah
        // cutover) tidak diderivasi dari status — operator tentukan manual.
        $tanpaBackupCount = DB::table('indikator_kinerjas')->whereNull('is_aktif')->count();

        if ($tanpaBackupCount > 0) {
            $idList = DB::table('indikator_kinerjas')
                ->whereNull('is_aktif')
                ->limit(self::MAX_SAMPLE_IDS)
                ->pluck('id')
                ->map(fn ($id) => "'{$id}'")
                ->implode(', ');

            throw new RuntimeException(sprintf(
                'Rollback gagal: %d indikator tanpa pasangan baris di tabel backup "%s" (contoh id: %s), '
                .'kemungkinan dibuat setelah cutover. down() tidak menderivasi is_aktif dari status. '
                .'Tentukan nilai yang benar manual, mis. UPDATE indikator_kinerjas SET is_aktif = true '
                .'WHERE id IN (...); lalu jalankan ulang rollback.',
                $tanpaBackupCount,
                self::BACKUP_TABLE,
                $idList
            ));
        }

        DB::statement(sprintf('DROP TABLE "%s"', self::BACKUP_TABLE));

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        DB::statement('ALTER TABLE indikator_kinerjas DROP CONSTRAINT IF EXISTS indikator_kinerjas_status_check');

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->dropColumn(['status', 'tahun_mulai_berlaku', 'created_by']);
        });

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->boolean('is_aktif')->nullable(false)->default(true)->change();
        });
    }
};
