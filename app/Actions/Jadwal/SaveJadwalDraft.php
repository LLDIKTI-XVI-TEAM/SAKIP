<?php

namespace App\Actions\Jadwal;

use App\Http\Requests\Jadwal\StoreJadwalRequest;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Jadwal\JadwalDraftRules;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveJadwalDraft
{
    public function __construct(private readonly PermissionResolver $resolver, private readonly AuditLogger $audit, private readonly JadwalDraftRules $rules) {}

    /** Simpan agregat lengkap; shared katalog → shared Renstra terurut → exclusive jadwal. @param array<string, mixed> $data */
    public function handle(User $actor, array $data, ?JadwalTahunan $jadwal = null): JadwalTahunan
    {
        $permission = $jadwal ? PermissionCodes::JADWAL_UPDATE : PermissionCodes::JADWAL_CREATE;
        $event = $jadwal ? 'jadwal.ubah' : 'jadwal.tambah';
        $id = $jadwal?->id ?? (string) Str::uuid();
        $decision = null;
        try {
            return DB::transaction(function () use (&$actor, $data, $jadwal, $permission, $event, $id, &$decision): JadwalTahunan {
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                $actor->lockActiveRoles();
                Permission::where('kode', $permission)->orderBy('id')->sharedLock()->get();
                $decision = $this->resolver->resolve($actor, $permission);
                if (! $decision->allowed) {
                    throw new AuthorizationException;
                }
                $inputRules = StoreJadwalRequest::inputRules($jadwal !== null);
                $allowed = array_map(fn (string $field): string => explode('.', $field)[0], array_keys($inputRules));
                if (array_diff(array_keys($data), $allowed) !== []) {
                    throw ValidationException::withMessages(['jadwal' => 'Permintaan memuat field yang tidak didukung.']);
                }
                $request = new StoreJadwalRequest;
                $data = Validator::make($data, $inputRules, $request->messages(), $request->attributes())->validate();
                $data['renstra_id'] = strtolower($data['renstra_id']);
                foreach ($data['periode'] as &$window) {
                    $window['periode_id'] = strtolower($window['periode_id']);
                }
                unset($window);
                Periode::lockConfiguration();
                $original = $jadwal ? JadwalTahunan::findOrFail($id) : null;
                $parentIds = array_unique(array_filter([$original?->renstra_id, $data['renstra_id']]));
                $parents = Renstra::whereIn('id', $parentIds)->orderBy('id')->sharedLock()->get()->keyBy('id');
                $current = $jadwal ? JadwalTahunan::lockForUpdate()->findOrFail($id) : new JadwalTahunan;
                if ($jadwal && ($current->renstra_id !== $original->renstra_id || $current->revisi !== (int) $data['revisi'])) {
                    throw ValidationException::withMessages(['jadwal' => 'Jadwal sudah berubah. Periksa data terbaru sebelum menyimpan kembali.']);
                }
                // Parent lama juga harus editable; pemindahan tidak menjadi jalan keluar dari parent baca saja.
                foreach ($parentIds as $parentId) {
                    $parent = $parents->get($parentId);
                    if (! $parent || ! in_array($parent->status, ['draft', 'aktif'], true)) {
                        throw ValidationException::withMessages(['renstra_id' => 'Renstra tidak tersedia untuk penyusunan jadwal. Renstra nonaktif atau diarsipkan hanya dapat dibaca.']);
                    }
                }
                if ($jadwal && ($current->status !== 'draft' || $current->activated_at !== null)) {
                    throw ValidationException::withMessages(['jadwal' => 'Hanya jadwal draft yang belum pernah diaktifkan dapat diubah.']);
                }
                if ($current->renstra_pk_id !== null) {
                    $pk = RenstraPk::findOrFail($current->renstra_pk_id);
                    if ($pk->renstra_id !== $data['renstra_id'] || (int) $pk->tahun !== (int) $data['tahun']) {
                        throw ValidationException::withMessages(['tahun' => 'Identitas jadwal harus tetap cocok dengan Perjanjian Kinerja yang dirujuk.']);
                    }
                }
                $existing = JadwalTahunan::where('renstra_id', $data['renstra_id'])->where('tahun', $data['tahun'])
                    ->when($jadwal, fn ($query) => $query->whereKeyNot($id))->first(['id']);
                if ($existing) {
                    throw ValidationException::withMessages(['jadwal' => 'Jadwal Renstra dan tahun tersebut sudah ada. Buka /jadwal/'.$existing->id.'.']);
                }
                $current->load('periode');
                $before = $jadwal ? $current->draftAttributes() : null;
                $selected = Periode::whereIn('id', array_column($data['periode'], 'periode_id'))->get()->keyBy('id');
                $oldWindows = $current->periode->keyBy('periode_id');
                foreach ($data['periode'] as $index => $window) {
                    $master = $selected->get($window['periode_id']);
                    if (! $master || (! $master->aktif && ! $oldWindows->has($master->id))) {
                        throw ValidationException::withMessages(["periode.$index.periode_id" => 'Pilih periode aktif; periode nonaktif hanya boleh dipertahankan bila sudah ada pada draft ini.']);
                    }
                    if ($master->revisi !== (int) $window['periode_revisi']) {
                        throw ValidationException::withMessages(["periode.$index.periode_revisi" => 'Periode sudah berubah. Periksa data terbaru sebelum menyimpan kembali.']);
                    }
                }
                $this->rules->validate($data, $selected->map(fn (Periode $master): array => ['urutan' => $master->urutan])->all());
                $current->fill(array_intersect_key($data, array_flip(['renstra_id', 'tahun', 'rencana_aksi_mulai', 'rencana_aksi_selesai', 'penutupan'])));
                $nextWindows = collect($data['periode'])->map(fn (array $window): array => [
                    'periode_id' => $window['periode_id'],
                    'pengisian_mulai' => $window['pengisian_mulai'],
                    'pengisian_selesai' => $window['pengisian_selesai'],
                    'reviu_mulai' => $window['reviu_mulai'],
                    'reviu_selesai' => $window['reviu_selesai'],
                ])->sortBy('periode_id')->values()->all();
                if ($jadwal && ! $current->isDirty() && $before['periode'] === $nextWindows) {
                    return $current;
                }
                $current->id = $id;
                $current->status = 'draft';
                $current->revisi = $jadwal ? $current->revisi + 1 : 1;
                $current->save();
                $current->periode()->whereNotIn('periode_id', array_column($nextWindows, 'periode_id'))->delete();
                foreach ($nextWindows as $window) {
                    $child = $oldWindows->get($window['periode_id']) ?? new PeriodeJadwal(['jadwal_id' => $id]);
                    $child->fill($window);
                    if (! $child->exists || $child->isDirty()) {
                        $child->save();
                    }
                }
                $current->load('periode');
                $this->audit->catat(actor: $actor, tindakan: $event, objekTipe: 'jadwal', objekId: $id,
                    nilaiLama: $before, nilaiBaru: $current->draftAttributes(), dasarIzin: $decision->toAuditBasis());

                return $current;
            }, attempts: 3);
        } catch (AuthorizationException|ValidationException|QueryException $exception) {
            if ($exception instanceof QueryException) {
                if (($exception->errorInfo[0] ?? null) !== '23505' || ! str_contains($exception->getMessage(), 'jadwal_tahunan_renstra_tahun_unik')) {
                    throw $exception;
                }
                $exception = ValidationException::withMessages(['jadwal' => 'Jadwal Renstra dan tahun tersebut sudah ada. Periksa daftar jadwal terbaru.']);
            }
            // Di luar rollback; tidak ada snapshot target atau input mentah pada audit penolakan.
            $this->audit->catat(actor: $actor, tindakan: $event.'_ditolak', objekTipe: 'jadwal', objekId: $id,
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_jadwal'], dasarIzin: $decision?->toAuditBasis());
            throw $exception;
        }
    }
}
