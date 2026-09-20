<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('entitas', 80)->nullable()->after('guard_name');
            $table->string('aksi', 80)->nullable()->after('entitas');
            $table->enum('butuh_scope', ['global', 'unit'])->default('global')->after('aksi');
            $table->boolean('sensitif')->default(false)->after('butuh_scope');
            $table->boolean('aktif')->default(true)->after('sensitif');
            $table->text('keterangan')->nullable()->after('aktif');
        });

        Schema::create('regulasi', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['kepmen', 'permen', 'perpres', 'keputusan_lainnya']);
            $table->string('nomor');
            $table->unsignedSmallInteger('tahun');
            $table->text('tentang');
            $table->date('tanggal')->nullable();
            $table->string('tautan_sumber', 2048)->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['jenis', 'nomor', 'tahun'], 'regulasi_jenis_nomor_tahun_unique');
        });

        Schema::create('berkas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jenis_berkas_id')->nullable()->index();
            $table->string('berkasable_type', 50);
            $table->unsignedBigInteger('berkasable_id');
            $table->enum('mode', ['file', 'tautan', 'teks']);
            $table->string('nama_asli')->nullable();
            $table->string('path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('ukuran_bytes')->nullable();
            $table->string('tautan', 2048)->nullable();
            $table->text('isi_teks')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('dihapus_pada')->nullable();
            $table->foreignId('dihapus_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['berkasable_type', 'berkasable_id'], 'berkas_parent_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE berkas
                ADD CONSTRAINT berkas_mode_payload_check CHECK (
                    (mode = 'file' AND nama_asli IS NOT NULL AND path IS NOT NULL AND mime IS NOT NULL AND ukuran_bytes IS NOT NULL AND tautan IS NULL AND isi_teks IS NULL)
                    OR (mode = 'tautan' AND nama_asli IS NULL AND path IS NULL AND mime IS NULL AND ukuran_bytes IS NULL AND tautan IS NOT NULL AND isi_teks IS NULL)
                    OR (mode = 'teks' AND nama_asli IS NULL AND path IS NULL AND mime IS NULL AND ukuran_bytes IS NULL AND tautan IS NULL AND isi_teks IS NOT NULL)
                )
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE berkas
                ADD CONSTRAINT berkas_parent_type_check CHECK (
                    berkasable_type IN ('rencana_aksi', 'pengukuran', 'kegiatan', 'renstra', 'renstra_pk', 'regulasi')
                )
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE berkas
                ADD CONSTRAINT berkas_dokumen_dasar_lampiran_bebas_check CHECK (
                    berkasable_type NOT IN ('renstra', 'renstra_pk', 'regulasi') OR jenis_berkas_id IS NULL
                )
            SQL);
        }

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waktu')->useCurrent();
            $table->string('tindakan', 120);
            $table->string('objek_tipe', 80);
            $table->unsignedBigInteger('objek_id');
            $table->jsonb('nilai_lama')->nullable();
            $table->jsonb('nilai_baru')->nullable();
            $table->text('alasan')->nullable();
            $table->jsonb('dasar_izin')->nullable();

            $table->index(['objek_tipe', 'objek_id'], 'audit_log_object_index');
            $table->index(['actor_id', 'waktu'], 'audit_log_actor_time_index');
        });

        Schema::create('user_permission_denials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('unit_kerjas')->cascadeOnDelete();
            $table->text('alasan');
            $table->foreignId('ditetapkan_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX user_permission_denials_scope_unique '
                .'ON user_permission_denials (user_id, permission_id, COALESCE(unit_id, 0))'
            );
        }

        Schema::table('renstras', function (Blueprint $table) {
            $table->foreignId('regulasi_id')
                ->nullable()
                ->after('id')
                ->constrained('regulasi')
                ->nullOnDelete();
        });

        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->foreignId('regulasi_id')
                ->nullable()
                ->after('sasaran_strategis_id')
                ->constrained('regulasi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regulasi_id');
        });

        Schema::table('renstras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regulasi_id');
        });

        Schema::dropIfExists('user_permission_denials');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('berkas');
        Schema::dropIfExists('regulasi');

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn([
                'entitas',
                'aksi',
                'butuh_scope',
                'sensitif',
                'aktif',
                'keterangan',
            ]);
        });
    }
};
