<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Satu berkas kegiatan paling banyak punya satu pengganti yang berlaku (Q25). Pengganti yang dihapus lunak
     * dikecualikan karena berkas lamanya kembali berlaku dan boleh dikoreksi lagi.
     */
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX berkas_kegiatan_satu_pengganti ON berkas (menggantikan_id) WHERE menggantikan_id IS NOT NULL AND berkasable_type = 'kegiatan' AND dihapus_pada IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX berkas_kegiatan_satu_pengganti');
    }
};
