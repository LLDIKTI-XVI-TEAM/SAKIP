<?php

namespace App\Actions\Periode;

use App\Http\Requests\Periode\StorePeriodeRequest;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Jadwal\JadwalDraftRules;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SavePeriode
{
    public function __construct(private readonly PermissionResolver $resolver, private readonly AuditLogger $audit, private readonly JadwalDraftRules $rules) {}

    /** Menjaga tepat satu final aktif dan makna seluruh draft sebelum metadata global berubah. @param array<string, mixed> $data */
    public function handle(User $actor, array $data, ?Periode $periode = null): Periode
    {
        $permission = $periode ? PermissionCodes::PERIODE_UPDATE : PermissionCodes::PERIODE_CREATE;
        $event = $periode ? 'periode.ubah' : 'periode.tambah';
        $id = $periode?->id ?? (string) Str::uuid();
        $decision = null;
        try {
            return DB::transaction(function () use (&$actor, $data, $periode, $permission, $event, $id, &$decision): Periode {
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                $actor->lockActiveRoles();
                Permission::where('kode', $permission)->orderBy('id')->sharedLock()->get();
                $decision = $this->resolver->resolve($actor, $permission);
                if (! $decision->allowed) {
                    throw new AuthorizationException;
                }
                $data['nama'] = is_string($data['nama'] ?? null) ? trim($data['nama']) : ($data['nama'] ?? null);
                $request = new StorePeriodeRequest;
                $data = Validator::make($data, StorePeriodeRequest::inputRules($periode !== null), $request->messages(), $request->attributes())->validate();
                Periode::lockConfiguration(exclusive: true);
                $current = $periode ? Periode::lockForUpdate()->findOrFail($id) : new Periode;
                $before = $periode ? $current->masterAttributes() : null;
                if ($periode && $current->revisi !== (int) $data['revisi']) {
                    throw ValidationException::withMessages(['periode' => 'Periode sudah berubah. Periksa data terbaru sebelum menyimpan kembali.']);
                }
                if ($periode && $current->metadataLocked()) {
                    foreach (['urutan', 'is_nilai_akhir'] as $field) {
                        if ((int) $current->$field !== (int) $data[$field]) {
                            throw ValidationException::withMessages([$field => 'Metadata terkunci karena periode dirujuk jadwal aktif atau ditutup.']);
                        }
                    }
                }
                $otherFinals = Periode::where('aktif', true)->where('is_nilai_akhir', true)->when($periode, fn ($query) => $query->whereKeyNot($id))->count();
                if ($otherFinals + ((bool) $data['aktif'] && (bool) $data['is_nilai_akhir'] ? 1 : 0) !== 1) {
                    throw ValidationException::withMessages(['is_nilai_akhir' => 'Konfigurasi harus memiliki tepat satu periode final aktif. Gunakan pergantian nilai akhir untuk mengganti final.']);
                }
                if ($periode && $current->urutan !== (int) $data['urutan']) {
                    $this->validateReferencedDrafts($current, (int) $data['urutan']);
                }
                $current->fill(array_intersect_key($data, array_flip(['nama', 'urutan', 'aktif', 'is_nilai_akhir'])));
                if ($periode && ! $current->isDirty()) {
                    return $current;
                }
                $current->id = $id;
                $current->revisi = $periode ? $current->revisi + 1 : 1;
                $current->save();
                $this->audit->catat(actor: $actor, tindakan: $event, objekTipe: 'periode', objekId: $id,
                    nilaiLama: $before, nilaiBaru: $current->masterAttributes(), dasarIzin: $decision->toAuditBasis());

                return $current;
            }, attempts: 3);
        } catch (AuthorizationException|ValidationException $exception) {
            $this->audit->catat(actor: $actor, tindakan: $event.'_ditolak', objekTipe: 'periode', objekId: $id,
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_periode'], dasarIzin: $decision?->toAuditBasis());
            throw $exception;
        }
    }

    private function validateReferencedDrafts(Periode $periode, int $order): void
    {
        // Advisory eksklusif menahan writer kalender; tidak mengambil lock jadwal setelah lock master.
        JadwalTahunan::where('status', 'draft')->whereHas('periode', fn ($query) => $query->where('periode_id', $periode->id))
            ->with('periode.periode')->chunkById(100, function ($jadwals) use ($periode, $order): void {
                foreach ($jadwals as $jadwal) {
                    $metadata = [];
                    foreach ($jadwal->periode as $window) {
                        $metadata[$window->periode_id] = ['urutan' => $window->periode_id === $periode->id ? $order : $window->periode->urutan];
                    }
                    try {
                        $this->rules->validateSelectedMetadata($jadwal->draftAttributes(), $metadata);
                    } catch (ValidationException) {
                        throw ValidationException::withMessages(['urutan' => 'Perubahan urutan membuat jadwal '.$jadwal->id.' tidak valid. Perbaiki pilihan/tanggal draft tersebut terlebih dahulu.']);
                    }
                }
            });
    }
}
