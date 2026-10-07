<?php

namespace App\Actions\RencanaAksi;

use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PresentRencanaAksi
{
    public function handle(RencanaAksi $ra, User $actor, bool $detail = false): array
    {
        $ra->loadMissing(['indikator', 'unit', 'penanggungJawab:id,nama', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion']);
        $version = $ra->latestVersion;
        $frozen = in_array($ra->status_alur, ['diajukan', 'diverifikasi', 'disahkan'], true) ? $version?->snapshot : null;
        $data = ['id' => $ra->id, 'versi' => $ra->versi, 'status' => $ra->status_alur, 'tahun' => $ra->tahun,
            'nomor_pengajuan' => $version?->nomor ?? 0, 'jalur_pengajuan' => $version?->jalur_pengajuan,
            'diajukan_pada' => $version?->diajukan_at?->toIso8601String(),
            'uraian' => is_array($frozen) && array_key_exists('uraian', $frozen) ? $frozen['uraian'] : $ra->uraian,
            'indikator' => ['kode' => $ra->indikator->kode, 'nama' => $ra->indikator->nama],
            'unit_kerja' => $ra->unit ? $ra->unit->only(['id', 'nama']) : ['id' => $ra->unit_id, 'nama' => ''],
            'pic' => $ra->penanggungJawab?->only(['id', 'nama']),
            'bukti_count' => is_array($frozen) ? count($frozen['bukti_dukungs'] ?? []) : (int) ($ra->bukti_dukungs_count ?? 0),
            'bukti_dukungs' => [], 'target_periode' => is_array($frozen) ? array_values($frozen['target_periode'] ?? []) : [],
            'can' => ['view' => true, 'ratify' => false]];
        if (! $detail) {
            return $data;
        }
        $can = [];
        foreach (['view' => 'view', 'ratify' => 'sahkan'] as $key => $ability) {
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
            $periodes = Periode::whereIn('id', collect($targets)->pluck('periode_id')->filter()->all())->get(['id', 'nama', 'urutan'])->keyBy('id');
            $data['target_periode'] = collect($targets)->map(fn ($row) => [
                'periode_id' => $row['periode_id'] ?? null,
                'periode_nama' => isset($row['periode_id']) ? $periodes->get($row['periode_id'])?->nama : null,
                'periode_urutan' => isset($row['periode_id']) ? $periodes->get($row['periode_id'])?->urutan : null,
                'nilai' => $row['nilai'] ?? null,
                'status_perhitungan' => $row['status_perhitungan'] ?? null,
                'komponen' => array_values($row['komponen'] ?? []),
            ])->sortBy('periode_urutan')->values()->all();
        }
        if ($can['view']) {
            $items = is_array($frozen) && array_key_exists('bukti_dukungs', $frozen)
                ? $frozen['bukti_dukungs']
                : $ra->buktiDukungs()->current()->get()->map(fn ($b) => $b->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks']))->all();
            $data['bukti_dukungs'] = array_map(fn ($b) => [...array_intersect_key($b, array_flip(['id', 'jenis_berkas_id', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks'])),
                'menggantikan_id' => $b['menggantikan_id'] ?? null, 'alasan_koreksi' => $b['alasan_koreksi'] ?? null], $items);
        }

        return $data;
    }
}
