<?php

namespace App\Actions\Laporan;

use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Laporan\ArsipDokumenResmi;
use App\Services\Storage\UploadLimits;
use Illuminate\Support\Facades\DB;

class TampilkanDokumenResmi
{
    public function __construct(private readonly ArsipDokumenResmi $arsip, private readonly PermissionResolver $izin) {}

    /** Daftar selalu dibatasi satu induk dan dipaginasi; path privat tidak menjadi props. */
    public function handle(User $actor, array $filter): array
    {
        $this->arsip->izinkan($actor, 'laporan:ekspor');
        $query = JadwalTahunan::with('renstra:id,nama')->whereIn('status', ['aktif', 'ditutup']);
        $pilihan = (clone $query)->orderByDesc('tahun')->orderBy('id')->limit(100)->get();
        $jadwal = ! empty($filter['jadwal_id']) ? (clone $query)->findOrFail($filter['jadwal_id']) : $pilihan->first();
        if ($jadwal && ! $pilihan->contains('id', $jadwal->id)) {
            $pilihan->push($jadwal);
        }
        $periode = $jadwal ? PeriodeJadwal::with('periode')->where('jadwal_id', $jadwal->id)->get()->sortBy('periode.urutan')->values() : collect();
        $selected = ! empty($filter['periode_id']) ? $periode->firstWhere('periode_id', $filter['periode_id']) : $periode->first();
        abort_if(! empty($filter['periode_id']) && ! $selected, 422, 'Periode tidak termasuk jadwal terpilih.');
        $jenis = $filter['jenis'] ?? 'ra';
        $induk = $jenis === 'triwulan' ? $selected : $jadwal;
        $list = Berkas::with('pengunggah:id,nama')->where('berkasable_type', $jenis === 'ra' ? 'jadwal_tahunan' : 'jadwal_periode')->where('berkasable_id', $induk?->id)
            ->select('berkas.*')->selectSub(DB::table('berkas as pengganti')->selectRaw('count(*)')->whereNull('pengganti.dihapus_pada')->whereColumn('pengganti.menggantikan_id', 'berkas.id'), 'jumlah_pengganti')
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->withQueryString();
        $list->through(fn (Berkas $b) => ['id' => $b->id, 'nama_asli' => $b->nama_asli, 'keterangan' => $b->keterangan,
            'pengunggah' => $b->pengunggah?->nama ?? 'Pengguna', 'waktu' => $b->created_at->toIso8601String(),
            'menggantikan_id' => $b->getAttribute('menggantikan_id'), 'alasan_koreksi' => $b->getAttribute('alasan_koreksi'),
            'digantikan' => (int) $b->getAttribute('jumlah_pengganti') > 0, 'url' => route('laporan.dokumen-resmi.unduh', $b->id)]);
        $unduh = $this->arsip->dapatUnduh($actor);

        return ['filters' => ['jadwal_id' => $jadwal?->id ?? '', 'periode_id' => $selected?->periode_id ?? '', 'jenis' => $jenis],
            'pilihan_jadwal' => $pilihan->map(fn ($j) => ['id' => $j->id, 'label' => $j->tahun.' · '.$j->renstra->nama])->all(),
            'pilihan_periode' => $periode->map(fn ($p) => ['id' => $p->periode_id, 'nama' => $p->periode->nama])->all(),
            'jadwal' => $jadwal?->only(['id', 'tahun', 'status']), 'periode_jadwal_id' => $selected?->id, 'arsip' => $list,
            'can' => ['unggah' => $induk !== null && $this->izin->allows($actor, 'laporan:unggah'), 'unduh' => $unduh],
            'alasan_unduh' => $unduh ? null : ArsipDokumenResmi::AKSES_DIBATASI, 'batas_kb' => UploadLimits::resolve(null, 'pdf')['ukuran_maks_kb']];
    }
}
