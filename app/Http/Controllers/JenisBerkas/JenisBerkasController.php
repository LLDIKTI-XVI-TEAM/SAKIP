<?php

namespace App\Http\Controllers\JenisBerkas;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteJenisBerkasRequest;
use App\Http\Requests\StoreJenisBerkasRequest;
use App\Http\Requests\UpdateJenisBerkasRequest;
use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JenisBerkasController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request, PermissionResolver $resolver): Response
    {
        $actor = $request->user()->fresh();
        abort_unless($resolver->allows($actor, 'jenis_berkas:read'), 403);

        $jenisBerkasList = JenisBerkas::with('indikator')
            ->orderBy('tahap')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        $referencedIndikatorIds = JenisBerkas::whereNotNull('indikator_id')->pluck('indikator_id')->all();

        $indikators = IndikatorKinerja::where('is_aktif', true)
            ->orWhereIn('id', $referencedIndikatorIds)
            ->select('id', 'kode', 'nama', 'is_aktif')
            ->orderBy('kode')
            ->get();

        $isUnggahanAktif = filter_var(
            Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        return Inertia::render('JenisBerkas/Index', [
            'jenisBerkasList' => $jenisBerkasList,
            'indikators' => $indikators,
            'unggahanAktif' => $isUnggahanAktif,
            'can' => [
                'create' => $resolver->allows($actor, 'jenis_berkas:create'),
                'update' => $resolver->allows($actor, 'jenis_berkas:update'),
                'delete' => $resolver->allows($actor, 'jenis_berkas:delete'),
                'pengaturan_update' => $resolver->allows($actor, 'pengaturan:update'),
            ],
        ]);
    }

    public function store(StoreJenisBerkasRequest $request, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'jenis_berkas:create');
        abort_unless($decision['allowed'], 403);

        $data = $request->validated();
        $isUnggahanAktif = filter_var(
            Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        DB::transaction(function () use ($data, $actor, $decision) {
            $data['created_by'] = $actor->id;

            $jb = JenisBerkas::create($data);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.buat',
                objekTipe: 'jenis_berkas',
                objekId: $jb->id,
                nilaiLama: null,
                nilaiBaru: $jb->toArray(),
                alasan: 'Penambahan persyaratan jenis berkas: '.$jb->nama,
                dasarIzin: $decision
            );
        });

        $redirect = redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil ditambahkan.');

        $this->flashUploadWarningIfNeeded($redirect, $isUnggahanAktif, $data);

        return $redirect;
    }

    public function update(UpdateJenisBerkasRequest $request, string $id, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'jenis_berkas:update');
        if (! $decision['allowed']) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.ubah_ditolak',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                alasan: $request->input('alasan') ?: 'Percobaan pembaruan persyaratan jenis berkas ditolak karena tidak memiliki izin.',
                dasarIzin: $decision
            );
            abort(403);
        }

        $data = $request->validated();
        $isUnggahanAktif = filter_var(
            Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        DB::transaction(function () use ($data, $id, $actor, $decision) {
            $jb = JenisBerkas::where('id', $id)->lockForUpdate()->firstOrFail();

            $expectedUpdatedAt = (string) $data['expected_updated_at'];
            $currentTimestamp = $jb->updated_at ?? $jb->created_at;
            if ($currentTimestamp !== null) {
                try {
                    $expectedIso = Carbon::parse($expectedUpdatedAt)->toISOString();
                    if ($currentTimestamp->toISOString() !== $expectedIso) {
                        throw ValidationException::withMessages([
                            'konflik' => 'Data persyaratan telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                        ]);
                    }
                } catch (\Exception $e) {
                    if ($e instanceof ValidationException) {
                        throw $e;
                    }
                    throw ValidationException::withMessages([
                        'expected_updated_at' => 'Format timestamp versi tidak valid.',
                    ]);
                }
            }

            $nilaiLama = $jb->toArray();
            $alasan = $data['alasan'];
            unset($data['alasan'], $data['expected_updated_at']);

            $jb->update($data);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.ubah',
                objekTipe: 'jenis_berkas',
                objekId: $jb->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $jb->fresh()->toArray(),
                alasan: $alasan,
                dasarIzin: $decision
            );
        });

        $redirect = redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil diperbarui.');

        $this->flashUploadWarningIfNeeded($redirect, $isUnggahanAktif, $data);

        return $redirect;
    }

    public function destroy(DeleteJenisBerkasRequest $request, string $id, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'jenis_berkas:delete');
        if (! $decision['allowed']) {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.hapus_ditolak',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                alasan: $request->input('alasan') ?: 'Percobaan penghapusan persyaratan jenis berkas ditolak karena tidak memiliki izin.',
                dasarIzin: $decision
            );
            abort(403);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $id, $actor, $decision) {
            $jb = JenisBerkas::where('id', $id)->lockForUpdate()->firstOrFail();

            $expectedUpdatedAt = (string) $data['expected_updated_at'];
            $currentTimestamp = $jb->updated_at ?? $jb->created_at;
            if ($currentTimestamp !== null) {
                try {
                    $expectedIso = Carbon::parse($expectedUpdatedAt)->toISOString();
                    if ($currentTimestamp->toISOString() !== $expectedIso) {
                        throw ValidationException::withMessages([
                            'konflik' => 'Data persyaratan telah diperbarui oleh pengguna lain. Silakan muat ulang halaman sebelum menghapus.',
                        ]);
                    }
                } catch (\Exception $e) {
                    if ($e instanceof ValidationException) {
                        throw $e;
                    }
                    throw ValidationException::withMessages([
                        'expected_updated_at' => 'Format timestamp versi tidak valid.',
                    ]);
                }
            }

            if (DB::table('berkas')->where('jenis_berkas_id', $jb->id)->exists()) {
                throw ValidationException::withMessages([
                    'alasan' => 'Persyaratan jenis berkas ini tidak dapat dihapus karena telah digunakan pada berkas bukti dukung. Anda dapat menonaktifkannya melalui opsi ubah.',
                ]);
            }

            $nilaiLama = $jb->toArray();
            $alasan = $data['alasan'];

            $jb->delete();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'jenis_berkas.hapus',
                objekTipe: 'jenis_berkas',
                objekId: $id,
                nilaiLama: $nilaiLama,
                nilaiBaru: null,
                alasan: $alasan,
                dasarIzin: $decision
            );
        });

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil dihapus.');
    }

    private function flashUploadWarningIfNeeded(RedirectResponse $redirect, bool $isUnggahanAktif, array $data): void
    {
        if ($isUnggahanAktif) {
            return;
        }

        $wajib = $data['wajib'] ?? false;
        $izinkanFile = $data['izinkan_file'] ?? false;
        $izinkanTautan = $data['izinkan_tautan'] ?? false;
        $izinkanTeks = $data['izinkan_teks'] ?? false;
        $semuaModeWajib = $data['semua_mode_wajib'] ?? false;

        $isFileOnlyWajib = $wajib && $izinkanFile && ! $izinkanTautan && ! $izinkanTeks;
        $isSemuaModeWajibWithFile = $wajib && $semuaModeWajib && $izinkanFile;

        if ($isFileOnlyWajib) {
            $redirect->with('warning', 'Peringatan: Mode unggahan file sedang dinonaktifkan pada setelan aplikasi (berkas.unggahan_aktif = false). Persyaratan wajib ini berpotensi tidak dapat dipenuhi PIC atau ditandai tidak dapat dipenuhi.');
        } elseif ($isSemuaModeWajibWithFile) {
            $redirect->with('warning', 'Peringatan: Mode unggahan file sedang dinonaktifkan pada setelan aplikasi (berkas.unggahan_aktif = false). Persyaratan "semua mode wajib" ini akan mengecualikan kewajiban file (waiver) saat evaluasi bukti.');
        }
    }
}
