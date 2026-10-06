<?php

namespace App\Actions\Periode;

use App\Http\Requests\Periode\ReplaceFinalPeriodeRequest;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReplaceFinalPeriode
{
    public function __construct(private readonly PermissionResolver $resolver, private readonly AuditLogger $audit) {}

    /** Flag final lama dipertahankan untuk histori; dua row dan audit berubah dalam satu transaksi. @param array<string, mixed> $data */
    public function handle(User $actor, array $data): Periode
    {
        $decision = null;
        $attemptId = (string) Str::uuid();
        try {
            return DB::transaction(function () use (&$actor, $data, &$decision): Periode {
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                $actor->lockActiveRoles();
                Permission::where('kode', PermissionCodes::PERIODE_UPDATE)->orderBy('id')->sharedLock()->get();
                $decision = $this->resolver->resolve($actor, PermissionCodes::PERIODE_UPDATE);
                if (! $decision->allowed) {
                    throw new AuthorizationException;
                }
                $request = new ReplaceFinalPeriodeRequest;
                $data = Validator::make($data, $request->rules(), $request->messages(), $request->attributes())->validate();
                $oldId = strtolower($data['periode_lama_id']);
                $newId = strtolower($data['periode_pengganti_id']);
                Periode::lockConfiguration(exclusive: true);
                $periods = Periode::whereIn('id', [$oldId, $newId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $old = $periods->get($oldId);
                $new = $periods->get($newId);
                if ($oldId === $newId || ! $old || ! $new || ! $old->aktif || ! $old->is_nilai_akhir
                    || $old->revisi !== (int) $data['revisi_lama'] || $new->revisi !== (int) $data['revisi_pengganti']) {
                    throw ValidationException::withMessages(['periode' => 'Periode sudah berubah. Periksa data terbaru sebelum menyimpan kembali.']);
                }
                if ($new->metadataLocked()) {
                    throw ValidationException::withMessages(['periode_pengganti_id' => 'Pengganti tidak boleh dirujuk jadwal aktif atau ditutup.']);
                }
                $before = ['lama' => $old->masterAttributes(), 'pengganti' => $new->masterAttributes()];
                $old->aktif = false;
                $old->revisi++;
                $old->save();
                $new->aktif = true;
                $new->is_nilai_akhir = true;
                $new->revisi++;
                $new->save();
                $this->audit->catat(actor: $actor, tindakan: 'periode.ganti_nilai_akhir', objekTipe: 'periode', objekId: $new->id,
                    nilaiLama: $before, nilaiBaru: ['lama' => $old->masterAttributes(), 'pengganti' => $new->masterAttributes()], dasarIzin: $decision->toAuditBasis());

                return $new;
            }, attempts: 3);
        } catch (AuthorizationException|ValidationException $exception) {
            $this->audit->catat(actor: $actor, tindakan: 'periode.ganti_nilai_akhir_ditolak', objekTipe: 'periode', objekId: $attemptId,
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_pergantian_final'], dasarIzin: $decision?->toAuditBasis());
            throw $exception;
        }
    }
}
