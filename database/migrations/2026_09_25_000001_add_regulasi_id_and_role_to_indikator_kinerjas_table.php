<?php

use App\Models\IndikatorKinerja;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
                        if (! empty($nilaiBaru['created_by_role']) && in_array($nilaiBaru['created_by_role'], RoleCatalog::codes(), true)) {
                            $derivedRole = $nilaiBaru['created_by_role'];
                        }
                    }

                    if (! $derivedRole && ! empty($audit->actor_id)) {
                        $derivedRole = DB::table('user_roles')
                            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                            ->where('user_roles.user_id', $audit->actor_id)
                            ->where('roles.aktif', true)
                            ->whereIn('roles.kode', RoleCatalog::codes())
                            ->orderBy('roles.urutan')
                            ->value('roles.kode');
                    }
                }
            }

            $roleToSet = $derivedRole ?? IndikatorKinerja::PROVENANCE_LEGACY_UNKNOWN;

            DB::table('indikator_kinerjas')
                ->where('id', $row->id)
                ->update(['created_by_role' => $roleToSet]);
        }

        // 2. Normalisasi nilai di luar katalog role resmi dan sentinel legacy ke 'legacy_unknown'
        $allowedRoles = [...RoleCatalog::codes(), IndikatorKinerja::PROVENANCE_LEGACY_UNKNOWN];
        DB::table('indikator_kinerjas')
            ->whereNull('created_by_role')
            ->orWhereNotIn('created_by_role', $allowedRoles)
            ->update(['created_by_role' => IndikatorKinerja::PROVENANCE_LEGACY_UNKNOWN]);

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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('indikator_kinerjas', 'created_by_role')) {
            DB::statement('ALTER TABLE indikator_kinerjas DROP CONSTRAINT IF EXISTS indikator_kinerjas_created_by_role_check');
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->dropColumn('created_by_role');
            });
        }
    }
};
