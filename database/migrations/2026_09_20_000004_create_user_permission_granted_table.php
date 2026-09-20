<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permission_granted', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('unit_kerjas')->cascadeOnDelete();
            $table->text('alasan');
            $table->foreignId('diberikan_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('permission_id');
            $table->index('unit_id');
        });

        // Di PostgreSQL, NULL diperlakukan unik per baris pada standard unique index.
        // Gunakan COALESCE(unit_id, 0) sesuai spesifikasi Data Model SAKIP §2.7.
        DB::statement('CREATE UNIQUE INDEX user_permission_granted_user_perm_unit_unique ON user_permission_granted (user_id, permission_id, COALESCE(unit_id, 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_granted');
    }
};
