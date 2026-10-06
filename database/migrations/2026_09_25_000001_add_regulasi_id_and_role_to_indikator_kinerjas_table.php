<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar kode role resmi — salinan beku dari
     * App\Services\Authorization\RoleCatalog::ROLES per 2026-09-29.
     * Disalin eksplisit agar fresh-migrate masa depan deterministik
     * meskipun katalog mutable berubah. Jangan import App\... di migrasi.
     *
     * @var list<string>
     */
    private const FROZEN_ROLE_CODES = [
        'superadmin',
        'admin',
        'perencanaan',
        'pimpinan',
        'pegawai',
    ];

    /**
     * Sentinel provenance legacy — salinan beku dari
     * App\Models\IndikatorKinerja::PROVENANCE_LEGACY_UNKNOWN.
     */
    private const LEGACY_UNKNOWN = 'legacy_unknown';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('indikator_kinerjas', 'created_by_role')) {
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->string('created_by_role', 50)
                    ->nullable()
                    ->after('is_aktif');
            });
        }

        // 1. Derivasikan created_by_role dari audit trail jika tersedia;
        // jika tidak ada bukti historis, tandai eksplisit sebagai 'legacy_unknown'.
        $legacyRows = DB::table('indikator_kinerjas')
            ->whereNull('created_by_role')
            ->get(['id']);

        foreach ($legacyRows as $row) {
            $derivedRole = null;
            if (Schema::hasTable('audit_log')) {
                $audit = DB::table('audit_log')
                    ->where('objek_tipe', 'indikator')
                    ->where('objek_id', (string) $row->id)
                    ->where('tindakan', 'indikator.buat')
                    ->first();

                if ($audit) {
                    if (! empty($audit->nilai_baru)) {
                        $nilaiBaru = is_string($audit->nilai_baru) ? json_decode($audit->nilai_baru, true) : (array) $audit->nilai_baru;
                        if (! empty($nilaiBaru['created_by_role']) && in_array($nilaiBaru['created_by_role'], self::FROZEN_ROLE_CODES, true)) {
                            $derivedRole = $nilaiBaru['created_by_role'];
                        }
                    }

                    if (! $derivedRole && ! empty($audit->dasar_izin)) {
                        $dasarIzin = is_string($audit->dasar_izin) ? json_decode($audit->dasar_izin, true) : (array) $audit->dasar_izin;
                        $roleIds = $dasarIzin['sumber_allow']['roles'] ?? $dasarIzin['roles'] ?? [];
                        if (! empty($roleIds)) {
                            $derivedRole = DB::table('roles')
                                ->whereIn('id', (array) $roleIds)
                                ->whereIn('kode', self::FROZEN_ROLE_CODES)
                                ->orderBy('urutan')
                                ->value('kode');
                        }
                    }
                }
            }

            $roleToSet = $derivedRole ?? self::LEGACY_UNKNOWN;

            DB::table('indikator_kinerjas')
                ->where('id', $row->id)
                ->update(['created_by_role' => $roleToSet]);
        }

        // 2. Normalisasi nilai di luar katalog role resmi dan sentinel legacy ke 'legacy_unknown'
        $allowedRoles = [...self::FROZEN_ROLE_CODES, self::LEGACY_UNKNOWN];
        DB::table('indikator_kinerjas')
            ->whereNull('created_by_role')
            ->orWhereNotIn('created_by_role', $allowedRoles)
            ->update(['created_by_role' => self::LEGACY_UNKNOWN]);

        // 3. Wajibkan non-null pada kolom created_by_role tanpa default (fail-closed)
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->string('created_by_role', 50)
                ->nullable(false)
                ->change();
        });

        // 4. Tambahkan check constraint terhadap katalog role resmi dan sentinel legacy_unknown
        DB::statement('ALTER TABLE indikator_kinerjas DROP CONSTRAINT IF EXISTS indikator_kinerjas_created_by_role_check');
        $validRoles = implode("', '", $allowedRoles);
        DB::statement("ALTER TABLE indikator_kinerjas ADD CONSTRAINT indikator_kinerjas_created_by_role_check CHECK (created_by_role IN ('{$validRoles}'));");

        // 5. Tambahkan trigger PostgreSQL untuk mengunci sifat immutable kolom created_by_role setelah INSERT
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_indikator_created_by_role_mutation() RETURNS trigger AS $$
            BEGIN
                IF OLD.created_by_role IS NOT NULL AND NEW.created_by_role IS DISTINCT FROM OLD.created_by_role THEN
                    RAISE EXCEPTION 'created_by_role pada indikator_kinerjas bersifat immutable dan tidak boleh diubah.' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS indikator_kinerjas_created_by_role_immutable ON indikator_kinerjas;
            CREATE TRIGGER indikator_kinerjas_created_by_role_immutable
                BEFORE UPDATE ON indikator_kinerjas
                FOR EACH ROW EXECUTE FUNCTION reject_indikator_created_by_role_mutation();
        SQL);

        // 6. Tambahkan trigger PostgreSQL untuk menolak penggunaan sentinel legacy_unknown pada INSERT record baru
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_indikator_legacy_unknown_insert() RETURNS trigger AS $$
            BEGIN
                IF NEW.created_by_role = 'legacy_unknown' THEN
                    RAISE EXCEPTION 'Sentinel legacy_unknown tidak diizinkan pada INSERT indikator baru.' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS indikator_kinerjas_no_legacy_unknown_insert ON indikator_kinerjas;
            CREATE TRIGGER indikator_kinerjas_no_legacy_unknown_insert
                BEFORE INSERT ON indikator_kinerjas
                FOR EACH ROW EXECUTE FUNCTION reject_indikator_legacy_unknown_insert();
        SQL);

        // 7. Tambahkan trigger PostgreSQL untuk menegakkan invariant bahwa indikator tidak boleh dipindahkan lintas Renstra
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_indikator_same_renstra() RETURNS trigger AS $$
            DECLARE
                old_renstra uuid;
                new_renstra uuid;
            BEGIN
                IF OLD.sasaran_strategis_id IS NOT NULL AND NEW.sasaran_strategis_id IS DISTINCT FROM OLD.sasaran_strategis_id THEN
                    SELECT renstra_id INTO old_renstra FROM sasaran_strategis WHERE id = OLD.sasaran_strategis_id;
                    SELECT renstra_id INTO new_renstra FROM sasaran_strategis WHERE id = NEW.sasaran_strategis_id;
                    IF old_renstra IS DISTINCT FROM new_renstra THEN
                        RAISE EXCEPTION 'Indikator kinerja tidak boleh dipindahkan ke sasaran strategis pada Renstra yang berbeda.' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS indikator_kinerjas_same_renstra_guard ON indikator_kinerjas;
            CREATE TRIGGER indikator_kinerjas_same_renstra_guard
                BEFORE UPDATE OF sasaran_strategis_id ON indikator_kinerjas
                FOR EACH ROW EXECUTE FUNCTION check_indikator_same_renstra();
        SQL);

        // 8. Tambahkan trigger PostgreSQL untuk menegakkan invariant bahwa sasaran strategis yang memiliki indikator tidak boleh dipindahkan ke Renstra lain
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION check_sasaran_renstra_immutability() RETURNS trigger AS $$
            BEGIN
                IF OLD.renstra_id IS NOT NULL AND NEW.renstra_id IS DISTINCT FROM OLD.renstra_id THEN
                    IF EXISTS (SELECT 1 FROM indikator_kinerjas WHERE sasaran_strategis_id = OLD.id) THEN
                        RAISE EXCEPTION 'Sasaran strategis yang memiliki indikator kinerja tidak boleh dipindahkan ke Renstra lain.' USING ERRCODE = '23514';
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS sasaran_strategis_renstra_guard ON sasaran_strategis;
            CREATE TRIGGER sasaran_strategis_renstra_guard
                BEFORE UPDATE OF renstra_id ON sasaran_strategis
                FOR EACH ROW EXECUTE FUNCTION check_sasaran_renstra_immutability();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('indikator_kinerjas', 'created_by_role')) {
            DB::statement('DROP TRIGGER IF EXISTS sasaran_strategis_renstra_guard ON sasaran_strategis');
            DB::statement('DROP FUNCTION IF EXISTS check_sasaran_renstra_immutability()');
            DB::statement('DROP TRIGGER IF EXISTS indikator_kinerjas_same_renstra_guard ON indikator_kinerjas');
            DB::statement('DROP FUNCTION IF EXISTS check_indikator_same_renstra()');
            DB::statement('DROP TRIGGER IF EXISTS indikator_kinerjas_no_legacy_unknown_insert ON indikator_kinerjas');
            DB::statement('DROP FUNCTION IF EXISTS reject_indikator_legacy_unknown_insert()');
            DB::statement('DROP TRIGGER IF EXISTS indikator_kinerjas_created_by_role_immutable ON indikator_kinerjas');
            DB::statement('DROP FUNCTION IF EXISTS reject_indikator_created_by_role_mutation()');
            DB::statement('ALTER TABLE indikator_kinerjas DROP CONSTRAINT IF EXISTS indikator_kinerjas_created_by_role_check');
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->dropColumn('created_by_role');
            });
        }
    }
};
