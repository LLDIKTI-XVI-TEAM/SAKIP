<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\Regulasi;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Kinerja\KomponenMutationService;
use App\Support\AlasanAudit;
use App\Support\AuditReason;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChangeIndicatorFormula
{
    public function __construct(
        private AuditLogger $auditLogger,
        private ResolveLockedActor $lockedActor,
        private KomponenMutationService $komponen,
    ) {}

    /**
     * Memiliki pembaruan metadata dan definisi atomik: otorisasi live, transaksi,
     * kunci, delta, penyimpanan, revisi serta audit. Omission mempertahankan row;
     * nonaktivasi dan hapus harus eksplisit. Tidak menulis snapshot historis.
     * Urutan kunci: aktor/ACL → Regulasi → Indikator → Sasaran terurut → child.
     */
    public function handle(User $actor, IndikatorKinerja $indikator, array $data): array
    {
        foreach (['regulasi_id', 'sasaran_strategis_id', 'unit_id'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = strtolower($data[$field]);
            }
        }
        if (array_key_exists('hapus_komponen_ids', $data)) {
            $data['hapus_komponen_ids'] = array_map('strtolower', $data['hapus_komponen_ids']);
        }
        foreach ($data['komponen'] ?? [] as $index => $item) {
            if (isset($item['id'])) {
                $data['komponen'][$index]['id'] = strtolower($item['id']);
            }
        }
        $basis = [];
        try {
            return DB::transaction(function () use ($actor, $indikator, $data, &$basis) {
                $decisions = [];
                foreach (['indikator:update', 'komponen:read', 'komponen:create', 'komponen:update', 'komponen:delete', 'indikator:read', 'regulasi:read'] as $permission) {
                    $locked = $this->lockedActor->handle($actor, $permission);
                    $actor = $locked['aktor'] ?? $actor;
                    $decisions[$permission] = $locked['keputusan'];
                }
                $require = function (string $permission) use ($decisions, &$basis): void {
                    $basis = $decisions[$permission]->toAuditBasis();
                    if (! $decisions[$permission]->allowed) {
                        throw new AuthorizationException($permission === 'regulasi:read' ? 'Penautan regulasi ditolak karena Anda tidak berwenang membaca data regulasi yang dirujuk.' : 'Perubahan ditolak karena wewenang tidak lagi berlaku saat transaksi.');
                    }
                };
                $definitionIntent = array_key_exists('komponen', $data) || array_key_exists('hapus_komponen_ids', $data);
                $require($definitionIntent ? 'komponen:read' : 'indikator:read');
                // Tujuan respons juga harus boleh dibuka, sebelum perubahan disimpan.
                $returnTo = $data['return_to'] ?? (array_key_exists('nama', $data) ? 'sasaran-indikator' : 'komponen');
                $require($returnTo === 'sasaran-indikator' ? 'indikator:read' : 'komponen:read');
                $ignoreRegulasi = false;
                if (array_key_exists('regulasi_id', $data) && ! $decisions['regulasi:read']->allowed) {
                    if (($data['regulasi_id'] ?? '') !== '') {
                        $require('regulasi:read');
                    }
                    $ignoreRegulasi = true;
                }
                $regulasi = ! $ignoreRegulasi && ! empty($data['regulasi_id'])
                    ? Regulasi::whereKey($data['regulasi_id'])->sharedLock()->first() : null;
                $parent = IndikatorKinerja::whereKey($indikator->id)->lockForUpdate()->firstOrFail();
                if (trim((string) ($data['expected_updated_at'] ?? '')) === '' || Carbon::parse($data['expected_updated_at'])->toISOString() !== $parent->updated_at?->toISOString()) {
                    throw ValidationException::withMessages(['konflik' => 'Data indikator kinerja telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.'])->status(409);
                }
                $oldParent = $parent->withoutRelations()->toArray();
                $sasaranIds = array_unique([$parent->sasaran_strategis_id, $data['sasaran_strategis_id'] ?? $parent->sasaran_strategis_id]);
                sort($sasaranIds);
                $sasarans = SasaranStrategis::whereIn('id', $sasaranIds)->orderBy('id')->sharedLock()->get()->keyBy('id');
                $currentSasaran = $sasarans->get($parent->sasaran_strategis_id);
                $targetSasaran = $sasarans->get($data['sasaran_strategis_id'] ?? $parent->sasaran_strategis_id);
                if (! $currentSasaran || ! $targetSasaran || $currentSasaran->renstra_id !== $targetSasaran->renstra_id) {
                    throw ValidationException::withMessages(['sasaran_strategis_id' => 'Pemindahan indikator ke sasaran strategis di luar Renstra asal tidak diizinkan.']);
                }
                if (isset($data['unit_id']) && $data['unit_id'] !== $parent->unit_id) {
                    throw ValidationException::withMessages(['unit_id' => 'Unit penanggung jawab tidak dapat diubah melalui edit umum. Gunakan endpoint pindah unit khusus.']);
                }
                foreach (['sasaran_strategis_id', 'kode', 'nama', 'satuan', 'arah', 'tipe_perhitungan', 'definisi_operasional'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $parent->$field = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                    }
                }
                foreach (['presisi', 'desimal_tampilan', 'wajib_catatan'] as $field) {
                    if (isset($data[$field])) {
                        $parent->$field = $data[$field];
                    }
                }
                if (array_key_exists('regulasi_id', $data) && ! $ignoreRegulasi) {
                    $id = $data['regulasi_id'] ?: null;
                    if ($id !== null && (! $regulasi || ($id !== $parent->regulasi_id && ! $regulasi->aktif))) {
                        throw ValidationException::withMessages(['regulasi_id' => 'Rujukan regulasi tidak valid atau sudah nonaktif.']);
                    }
                    $parent->regulasi_id = $id;
                }
                $parentChanged = $parent->isDirty();
                if ($parentChanged) {
                    $require('indikator:update');
                }
                $existing = $parent->komponen()->reorder()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $candidate = $existing->map(fn ($row) => clone $row);
                $deleted = [];
                foreach ($data['hapus_komponen_ids'] ?? [] as $id) {
                    if (isset($deleted[$id])) {
                        throw ValidationException::withMessages(['hapus_komponen_ids' => 'Identitas komponen yang dihapus tidak boleh duplikat.']);
                    }
                    if (! $existing->has($id)) {
                        throw ValidationException::withMessages(['hapus_komponen_ids' => 'Komponen yang dihapus bukan milik indikator ini.']);
                    }
                    $deleted[$id] = $existing[$id];
                    $candidate->forget($id);
                }
                $new = [];
                $changed = [];
                $seen = [];
                foreach ($data['komponen'] ?? [] as $index => $item) {
                    $id = $item['id'] ?? null;
                    if ($id !== null && (isset($seen[$id]) || isset($deleted[$id]) || ! $existing->has($id))) {
                        throw ValidationException::withMessages(["komponen.{$index}.id" => 'Identitas komponen duplikat, dihapus, atau bukan milik indikator ini.']);
                    }
                    if ($id === null) {
                        $row = $this->komponen->modelKandidat($parent->id, $item, "komponen.{$index}");
                        $new[] = $row;
                        $candidate->push($row);
                    } else {
                        $this->komponen->validasiSintaksKandidat($item, "komponen.{$index}");
                        $seen[$id] = true;
                        $row = clone $existing[$id];
                        $row->fill($this->komponen->normalisasiInput($item));
                        $candidate->put($id, $row);
                        if ($row->isDirty()) {
                            $changed[$id] = $row;
                        }
                    }
                }
                if ($new) {
                    $require('komponen:create');
                }
                if ($changed) {
                    $require('komponen:update');
                }
                if ($deleted) {
                    $require('komponen:delete');
                }
                if ($candidate->pluck('kode')->duplicatesStrict()->isNotEmpty()) {
                    throw ValidationException::withMessages(['kode' => $this->komponen->pesanKodeDuplikat()]);
                }
                $this->komponen->pastikanDefinisiValid($parent, $candidate->values(), 'tipe_perhitungan');
                $alasan = trim(AuditReason::sanitize(AlasanAudit::sanitasi($data['alasan'] ?? null, '')));
                if (($changed || $deleted || $parent->isDirty('tipe_perhitungan')) && mb_strlen($alasan) < 5) {
                    throw ValidationException::withMessages(['alasan' => 'Alasan perubahan minimal 5 karakter.']);
                }
                $alasan = $alasan !== '' ? $alasan : ($parentChanged ? "Memperbarui indikator kinerja '{$parent->kode}'." : 'Menambah komponen indikator.');
                foreach ($deleted as $row) {
                    foreach (['jadwal_snapshot_komponen', 'rencana_aksi_target', 'pengukuran_komponen', 'klaim_kegiatan'] as $table) {
                        if (DB::table($table)->where('komponen_id', $row->id)->exists()) {
                            throw ValidationException::withMessages(['hapus_komponen_ids' => 'Komponen masih direferensikan data historis dan tidak dapat dihapus. Nonaktifkan komponen bila diperlukan.']);
                        }
                    }
                }
                if (! $parentChanged && ! $new && ! $changed && ! $deleted) {
                    return ['indikator' => $parent, 'renstraId' => $targetSasaran->renstra_id, 'status' => 'unchanged', 'returnTo' => $returnTo];
                }
                // Bebaskan kode dalam transaksi agar swap dan penggunaan ulang kode aman.
                foreach ($deleted as $row) {
                    $row->delete();
                }
                foreach ($changed as $row) {
                    if ($row->isDirty('kode')) {
                        DB::table('indikator_komponen')->where('id', $row->id)->update(['kode' => '__'.str_replace('-', '', (string) Str::uuid())]);
                    }
                }
                foreach ($changed as $row) {
                    $row->save();
                }
                foreach ($new as $index => $row) {
                    $new[$index] = $this->komponen->buat($parent->id, $row->getAttributes(), $actor->id);
                }
                $this->komponen->bumpVersiFormula($parent);
                if ($parentChanged) {
                    $parentReason = $parent->wasChanged('tipe_perhitungan') ? $alasan : "Memperbarui indikator kinerja '{$parent->kode}'.";
                    if ($parent->wasChanged('regulasi_id')) {
                        $parentReason .= ' Perubahan regulasi_id: '.($oldParent['regulasi_id'] ?? 'kosong').' -> '.($parent->regulasi_id ?? 'kosong').'.';
                    }
                    $this->auditLogger->catat(actor: $actor, tindakan: 'indikator.ubah', objekTipe: 'indikator', objekId: $parent->id,
                        nilaiLama: $oldParent, nilaiBaru: $parent->withoutRelations()->toArray(),
                        alasan: $parentReason, dasarIzin: $decisions['indikator:update']->toAuditBasis());
                }
                foreach (['buat' => $new, 'ubah' => $changed, 'hapus' => $deleted] as $operation => $rows) {
                    $permission = ['buat' => 'komponen:create', 'ubah' => 'komponen:update', 'hapus' => 'komponen:delete'][$operation];
                    foreach ($rows as $row) {
                        $this->auditLogger->catat(actor: $actor, tindakan: 'komponen.'.$operation, objekTipe: 'indikator_komponen', objekId: $row->id,
                            nilaiLama: $operation === 'buat' ? null : $this->komponen->formatAuditSnapshot($existing[$row->id]),
                            nilaiBaru: $operation === 'hapus' ? null : $this->komponen->formatAuditSnapshot($row), alasan: $alasan, dasarIzin: $decisions[$permission]->toAuditBasis());
                    }
                }

                return ['indikator' => $parent, 'renstraId' => $targetSasaran->renstra_id, 'status' => 'saved', 'returnTo' => $returnTo];
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Kesalahan sintaks/alasan tidak menghasilkan audit mutation baru.
            $domainErrors = $exception instanceof ValidationException
                ? array_intersect(['konflik', 'tipe_perhitungan', 'hapus_komponen_ids', 'sasaran_strategis_id', 'unit_id', 'regulasi_id'], array_keys($exception->errors())) : [];
            if ($exception instanceof AuthorizationException || $domainErrors !== [] || ($exception instanceof ValidationException && preg_grep('/^komponen\.\d+\.id$/', array_keys($exception->errors())) !== [])) {
                $this->auditLogger->catat(actor: $actor, tindakan: 'indikator.ubah_ditolak', objekTipe: 'indikator', objekId: $indikator->id,
                    alasan: $exception instanceof AuthorizationException ? $exception->getMessage() : 'Perubahan definisi ditolak oleh validasi atau konflik revisi.', dasarIzin: $basis);
            }
            throw $exception;
        } catch (QueryException $exception) {
            if ($this->komponen->isReferenceConstraintViolation($exception)) {
                $this->auditLogger->catat(actor: $actor, tindakan: 'indikator.ubah_ditolak', objekTipe: 'indikator', objekId: $indikator->id, alasan: 'Penghapusan ditolak karena komponen masih direferensikan.', dasarIzin: $basis);
                throw ValidationException::withMessages(['hapus_komponen_ids' => 'Komponen masih direferensikan data historis dan tidak dapat dihapus.']);
            }
            if ($this->komponen->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages(['kode' => $this->komponen->pesanKodeDuplikat()]);
            }
            throw $exception;
        }
    }
}
