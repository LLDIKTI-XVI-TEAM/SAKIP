<?php

namespace App\Actions\RencanaAksi;

use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PresentRencanaAksi
{
    /**
     * Penanda field beku yang hilang dari snapshot — tidak pernah diganti nilai
     * live dan tidak menyebabkan 500.
     */
    public const KONTEKS_TIDAK_LENGKAP = 'konteks tidak lengkap';

    /**
     * Ringkas (daftar) hanya membawa can.view agar kontrak tidak bertentangan
     * dengan detail; Index tidak memakai can.ratify. Detail membawa
     * can.view/ratify/evidence via Gate; bukti beku tanpa live fallback.
     *
     * Kontrak snapshot versi (status beku): kunci yang dibaca presenter adalah
     * 'indikator' => ['kode','nama'], 'unit_kerja' => ['id','nama'],
     * 'pic' => ['id','nama']|null (null = tanpa PIC), 'uraian' (alias lama
     * 'narasi'), 'target_periode', 'bukti_dukungs'. Baris 'target_periode'
     * membawa 'periode_id','periode_nama','periode_urutan','nilai',
     * 'status_perhitungan','komponen' ([['komponen_id','kode','label','nilai']]).
     * Fallback kedua adalah jadwal_snapshot beku (nama indikator, unit_id).
     * Relasi live / header live TIDAK PERNAH dipakai untuk status beku
     * (termasuk master Periode live untuk nama/urutan); draft/dikembalikan
     * tetap live.
     */
    public function handle(RencanaAksi $ra, User $actor, bool $detail = false): array
    {
        $ra->loadMissing(['indikator', 'unit', 'penanggungJawab:id,nama', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion']);
        $version = $ra->latestVersion;
        $isBeku = in_array($ra->status_alur, ['diajukan', 'diverifikasi', 'disahkan'], true);
        $frozen = $isBeku && $version !== null && is_array($version->snapshot) ? $version->snapshot : null;
        // Fallback kedua (masih beku): kolom jadwal_snapshot, bukan relasi live.
        $jadwalBeku = $isBeku ? $ra->jadwalSnapshot : null;
        $konteksHilang = [];
        if ($frozen !== null || $isBeku) {
            $indikator = $this->frozenIndikator($frozen, $jadwalBeku, $konteksHilang);
            $unitKerja = $this->frozenUnit($frozen, $jadwalBeku, $konteksHilang);
            $pic = $this->frozenPic($frozen, $konteksHilang);
            $uraian = $this->frozenUraian($frozen, $konteksHilang);
        } else {
            $indikator = ['kode' => $ra->indikator->kode, 'nama' => $ra->indikator->nama];
            $unitKerja = $ra->unit ? $ra->unit->only(['id', 'nama']) : ['id' => $ra->unit_id, 'nama' => ''];
            $pic = $ra->penanggungJawab?->only(['id', 'nama']);
            $uraian = $ra->uraian;
        }
        $data = ['id' => $ra->id, 'versi' => $ra->versi, 'status' => $ra->status_alur, 'tahun' => $ra->tahun,
            'nomor_pengajuan' => $version?->nomor ?? 0, 'jalur_pengajuan' => $version?->jalur_pengajuan,
            'diajukan_pada' => $version?->diajukan_at?->toIso8601String(),
            'uraian' => $uraian,
            'indikator' => $indikator,
            'unit_kerja' => $unitKerja,
            'pic' => $pic,
            'konteks_tidak_lengkap' => $konteksHilang,
            'bukti_count' => is_array($frozen) ? count($frozen['bukti_dukungs'] ?? []) : (int) ($ra->bukti_dukungs_count ?? 0),
            'bukti_dukungs' => [], 'target_periode' => is_array($frozen) ? array_values($frozen['target_periode'] ?? []) : [],
            'can' => ['view' => true]];
        if (! $detail) {
            return $data;
        }
        $can = [];
        foreach (['view' => 'view', 'ratify' => 'sahkan', 'evidence' => 'viewEvidence'] as $key => $ability) {
            $can[$key] = Gate::forUser($actor)->allows($ability, $ra);
        }
        $data['can'] = $can;
        $diajukanOleh = null;
        if ($version?->diajukan_by) {
            $diajukanOleh = User::whereKey($version->diajukan_by)->first(['id', 'nama'])?->only(['id', 'nama']);
        }
        $data['diajukan_oleh'] = $diajukanOleh;
        $data['disahkan_pada'] = $ra->disahkan_at?->toIso8601String();
        $targets = is_array($data['target_periode']) ? $data['target_periode'] : [];
        if ($targets !== []) {
            $ra->loadMissing('jadwalSnapshot.komponen');
            $definisis = ($ra->jadwalSnapshot?->komponen ?? collect())->keyBy('komponen_id');
            if ($isBeku && is_array($frozen)) {
                // Status beku: nama/urutan periode HANYA dari snapshot versi.
                // Snapshot lama tanpa kunci → penanda, tanpa join master Periode live.
                // Kode/label komponen tetap boleh fallback definisi jadwal_snapshot
                // beku (bukan master live).
                $hilangTarget = [];
                $data['target_periode'] = collect($targets)->map(function ($row) use ($definisis, &$hilangTarget) {
                    $nama = $row['periode_nama'] ?? null;
                    if (! is_string($nama) || $nama === '') {
                        $nama = self::KONTEKS_TIDAK_LENGKAP;
                        $hilangTarget['target_periode_nama'] = true;
                    }
                    $urutan = $row['periode_urutan'] ?? null;
                    $urutan = is_numeric($urutan) ? (int) $urutan : null;
                    if ($urutan === null) {
                        $hilangTarget['target_periode_urutan'] = true;
                    }
                    $komponens = collect(array_values($row['komponen'] ?? []))->map(fn ($komponen) => [
                        'komponen_id' => $komponen['komponen_id'] ?? null,
                        'kode' => $definisis->get($komponen['komponen_id'] ?? '')?->kode ?? $komponen['kode'] ?? null,
                        'label' => $definisis->get($komponen['komponen_id'] ?? '')?->label ?? $komponen['label'] ?? null,
                        'nilai' => $komponen['nilai'] ?? null,
                    ])->values()->all();

                    return [
                        'periode_id' => $row['periode_id'] ?? null,
                        'periode_nama' => $nama,
                        'periode_urutan' => $urutan,
                        'nilai' => $row['nilai'] ?? null,
                        'status_perhitungan' => $row['status_perhitungan'] ?? null,
                        'komponen' => $komponens,
                    ];
                })->sortBy(fn ($row) => $row['periode_urutan'] ?? PHP_INT_MAX)->values()->all();
                foreach (array_keys($hilangTarget) as $key) {
                    if (! in_array($key, $konteksHilang, true)) {
                        $konteksHilang[] = $key;
                    }
                }
                $data['konteks_tidak_lengkap'] = $konteksHilang;
            } else {
                // Status tidak beku: pertahankan live join master Periode.
                $periodes = Periode::whereIn('id', collect($targets)->pluck('periode_id')->filter()->all())->get(['id', 'nama', 'urutan'])->keyBy('id');
                $data['target_periode'] = collect($targets)->map(function ($row) use ($periodes, $definisis) {
                    $komponens = collect(array_values($row['komponen'] ?? []))->map(fn ($komponen) => [
                        'komponen_id' => $komponen['komponen_id'] ?? null,
                        'kode' => $definisis->get($komponen['komponen_id'] ?? '')?->kode ?? $komponen['kode'] ?? null,
                        'label' => $definisis->get($komponen['komponen_id'] ?? '')?->label ?? $komponen['label'] ?? null,
                        'nilai' => $komponen['nilai'] ?? null,
                    ])->values()->all();

                    return [
                        'periode_id' => $row['periode_id'] ?? null,
                        'periode_nama' => isset($row['periode_id']) ? $periodes->get($row['periode_id'])?->nama : null,
                        'periode_urutan' => isset($row['periode_id']) ? $periodes->get($row['periode_id'])?->urutan : null,
                        'nilai' => $row['nilai'] ?? null,
                        'status_perhitungan' => $row['status_perhitungan'] ?? null,
                        'komponen' => $komponens,
                    ];
                })->sortBy('periode_urutan')->values()->all();
            }
        }
        if ($can['evidence']) {
            // Status beku tanpa key bukti = daftar kosong; jangan fallback relasi live.
            if (is_array($frozen)) {
                $items = $frozen['bukti_dukungs'] ?? [];
            } else {
                $items = $ra->buktiDukungs()->current()->get()->map(fn ($b) => $b->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks']))->all();
            }
            $data['bukti_dukungs'] = array_map(fn ($b) => [...array_intersect_key($b, array_flip(['id', 'jenis_berkas_id', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks'])),
                'menggantikan_id' => $b['menggantikan_id'] ?? null, 'alasan_koreksi' => $b['alasan_koreksi'] ?? null], is_array($items) ? $items : []);
        }

        return $data;
    }

    /** Konteks indikator beku: versi.snapshot dulu, lalu kolom jadwal_snapshot beku. */
    private function frozenIndikator(?array $frozen, mixed $jadwalBeku, array &$hilang): array
    {
        $beku = is_array($frozen['indikator'] ?? null) ? $frozen['indikator'] : [];
        $kode = $beku['kode'] ?? null;
        // jadwal_snapshot tidak menyimpan kode; kode yang hilang langsung ditandai.
        $nama = $beku['nama'] ?? $jadwalBeku?->nama;
        if (! is_string($kode) || $kode === '') {
            $kode = self::KONTEKS_TIDAK_LENGKAP;
            $hilang[] = 'indikator_kode';
        }
        if (! is_string($nama) || $nama === '') {
            $nama = self::KONTEKS_TIDAK_LENGKAP;
            $hilang[] = 'indikator_nama';
        }

        return ['kode' => $kode, 'nama' => $nama];
    }

    /** Konteks unit beku: id boleh fallback unit_id jadwal; nama tidak punya sumber beku lain. */
    private function frozenUnit(?array $frozen, mixed $jadwalBeku, array &$hilang): array
    {
        $beku = is_array($frozen['unit_kerja'] ?? null) ? $frozen['unit_kerja'] : [];
        $id = $beku['id'] ?? $jadwalBeku?->unit_id;
        $nama = $beku['nama'] ?? null;
        if (! is_string($id) || $id === '') {
            $id = '';
            $hilang[] = 'unit_id';
        }
        if (! is_string($nama) || $nama === '') {
            $nama = self::KONTEKS_TIDAK_LENGKAP;
            $hilang[] = 'unit_nama';
        }

        return ['id' => $id, 'nama' => $nama];
    }

    /**
     * PIC beku: key 'pic' yang ada bernilai null berarti memang tanpa PIC.
     * Key yang hilang berarti snapshot lama tanpa konteks → penanda.
     */
    private function frozenPic(?array $frozen, array &$hilang): ?array
    {
        if (! is_array($frozen) || ! array_key_exists('pic', $frozen)) {
            $hilang[] = 'pic';

            return ['id' => '', 'nama' => self::KONTEKS_TIDAK_LENGKAP];
        }
        $beku = $frozen['pic'];
        if ($beku === null) {
            return null;
        }
        if (! is_array($beku)) {
            $hilang[] = 'pic';

            return ['id' => '', 'nama' => self::KONTEKS_TIDAK_LENGKAP];
        }

        $nama = $beku['nama'] ?? null;
        if (! is_string($nama) || $nama === '') {
            $nama = self::KONTEKS_TIDAK_LENGKAP;
            $hilang[] = 'pic_nama';
        }

        return ['id' => is_string($beku['id'] ?? null) ? $beku['id'] : '', 'nama' => $nama];
    }

    /** Uraian beku ('uraian', alias lama 'narasi'): key hilang → penanda, null → tetap kosong. */
    private function frozenUraian(?array $frozen, array &$hilang): ?string
    {
        if (! is_array($frozen) || (! array_key_exists('uraian', $frozen) && ! array_key_exists('narasi', $frozen))) {
            $hilang[] = 'uraian';

            return self::KONTEKS_TIDAK_LENGKAP;
        }
        $uraian = $frozen['uraian'] ?? $frozen['narasi'] ?? null;

        return $uraian === null || is_string($uraian) ? $uraian : (string) $uraian;
    }
}
