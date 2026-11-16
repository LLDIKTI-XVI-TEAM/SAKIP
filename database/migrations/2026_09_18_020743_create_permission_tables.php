<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode')->unique();
            $table->string('entitas');
            $table->string('aksi');
            $table->text('keterangan')->nullable();
            $table->enum('butuh_scope', ['global', 'unit'])->default('global');
            $table->boolean('sensitif')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->boolean('is_sistem')->default(true);
            $table->integer('urutan');
            $table->boolean('aktif')->default(true);
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['role_id', 'permission_id']);
        });
        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('actor_type', ['user', 'system', 'operator']);
            $table->enum('sumber', ['manual', 'sso_onboarding', 'bootstrap']);
            $table->string('operator_reference')->nullable();
            $table->string('runtime_identity')->nullable();
            $table->timestampTz('waktu');
            $table->string('tindakan');
            $table->string('objek_tipe');
            $table->uuid('objek_id');
            $table->jsonb('nilai_lama')->nullable();
            $table->jsonb('nilai_baru')->nullable();
            $table->text('alasan')->nullable();
            $table->jsonb('dasar_izin')->nullable();
            $table->index(['objek_tipe', 'objek_id', 'waktu']);
        });
        DB::statement("ALTER TABLE audit_log ADD CONSTRAINT audit_actor_provenance CHECK (
            (actor_type = 'user' AND sumber = 'manual' AND actor_id IS NOT NULL AND operator_reference IS NULL AND runtime_identity IS NULL)
            OR (actor_type = 'system' AND sumber = 'sso_onboarding' AND actor_id IS NULL AND operator_reference IS NULL AND runtime_identity IS NULL AND tindakan = 'user_roles.tambah')
            OR (actor_type = 'operator' AND sumber = 'bootstrap' AND actor_id IS NULL AND operator_reference IS NOT NULL AND runtime_identity IS NOT NULL AND length(trim(operator_reference)) > 0 AND length(trim(runtime_identity)) > 0)
        )");
        // Query builder dan SQL langsung tidak boleh melewati sifat final jejak audit.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_audit_log_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Audit bersifat append-only.' USING ERRCODE = '23514';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER audit_log_immutable
                BEFORE UPDATE OR DELETE ON audit_log
                FOR EACH ROW EXECUTE FUNCTION reject_audit_log_mutation();
            SQL);
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignUuid('diberikan_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('sumber_pemberian', ['manual', 'sso_onboarding', 'bootstrap']);
            $table->foreignUuid('audit_id')->nullable()->constrained('audit_log')->restrictOnDelete();
            $table->timestamp('created_at');
        });
        DB::statement("ALTER TABLE user_roles ADD CONSTRAINT user_roles_provenance CHECK ((sumber_pemberian = 'manual' AND diberikan_oleh IS NOT NULL) OR (sumber_pemberian = 'sso_onboarding' AND diberikan_oleh IS NULL) OR (sumber_pemberian = 'bootstrap' AND diberikan_oleh IS NULL AND audit_id IS NOT NULL))");
        foreach (['user_permission_granted' => 'diberikan_oleh', 'user_permission_denied' => 'ditetapkan_oleh'] as $name => $actor) {
            Schema::create($name, function (Blueprint $table) use ($actor) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
                $table->foreignUuid('permission_id')->constrained('permissions')->restrictOnDelete();
                $table->foreignUuid('unit_id')->nullable()->constrained('unit')->restrictOnDelete();
                $table->text('alasan');
                $table->foreignUuid($actor)->constrained('users')->restrictOnDelete();
                $table->timestamp('created_at');
            });
            // Index parsial menegakkan scope NULL tunggal tanpa mengarang UUID sentinel.
            DB::statement("CREATE UNIQUE INDEX {$name}_scoped_unique ON {$name} (user_id, permission_id, unit_id) WHERE unit_id IS NOT NULL");
            DB::statement("CREATE UNIQUE INDEX {$name}_global_unique ON {$name} (user_id, permission_id) WHERE unit_id IS NULL");
            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_reason CHECK (length(trim(alasan)) > 0)");
        }
        Schema::create('auth_bootstraps', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('audit_id')->constrained('audit_log')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['auth_bootstraps', 'user_permission_denied', 'user_permission_granted', 'user_roles', 'audit_log', 'role_permissions', 'roles', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::unprepared('DROP FUNCTION IF EXISTS reject_audit_log_mutation()');
    }
};
