<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengiriman;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PembacaStatusPengiriman
{
    /** Kontrak halaman status; alamat dan isi pesan tidak keluar lewat presenter.
     * @param  array<string, mixed>  $filter
     */
    public function handle(User $actor, array $filter): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('pengaturan:update');
        $f = Validator::make($filter, ['status' => 'nullable|in:antre,gagal,dibatalkan,diterima_provider,perlu_rekonsiliasi', 'kanal' => 'nullable|in:email,whatsapp', 'penerima' => 'nullable|uuid', 'mulai' => 'nullable|date_format:Y-m-d', 'selesai' => 'nullable|date_format:Y-m-d'.(! empty($filter['mulai']) ? '|after_or_equal:mulai' : ''), 'page' => 'nullable|integer|min:1'], [
            'status.in' => 'Pilih status pengiriman yang tersedia.',
            'kanal.in' => 'Pilih kanal Email atau WhatsApp.',
            'penerima.uuid' => 'Gunakan ID akun SAKIP berformat UUID yang valid.',
            'mulai.date_format' => 'Gunakan tanggal awal WITA yang valid.',
            'selesai.date_format' => 'Gunakan tanggal akhir WITA yang valid.',
            'selesai.after_or_equal' => 'Tanggal akhir WITA tidak boleh sebelum tanggal awal.',
            'page.integer' => 'Nomor halaman harus berupa bilangan bulat.',
            'page.min' => 'Nomor halaman minimal 1.',
        ])->validate();
        $q = NotifikasiPengiriman::query()->select(['id', 'status', 'kanal', 'penerima_user_id', 'driver', 'simulasi', 'kode_hasil', 'percobaan', 'created_at', 'dicoba_terakhir_pada']);
        foreach (['status' => 'status', 'kanal' => 'kanal', 'penerima' => 'penerima_user_id'] as $input => $column) {
            if (! empty($f[$input])) {
                $q->where($column, $f[$input]);
            }
        }
        if (! empty($f['mulai'])) {
            $q->where('created_at', '>=', CarbonImmutable::parse($f['mulai'], config('app.business_timezone'))->utc());
        }
        if (! empty($f['selesai'])) {
            $q->where('created_at', '<', CarbonImmutable::parse($f['selesai'], config('app.business_timezone'))->addDay()->utc());
        }

        // Muat nama hanya untuk halaman aktif; alamat dan profil lengkap tidak diproyeksikan.
        $halaman = $q->orderByDesc('created_at')->orderBy('id')->paginate(25, page: $f['page'] ?? 1);
        $nama = User::query()->whereIn('id', $halaman->getCollection()->pluck('penerima_user_id'))->pluck('nama', 'id');

        return $halaman->through(fn ($p) => [...$p->toArray(), 'penerima_nama' => $nama[$p->penerima_user_id] ?? null, 'label_status' => match ($p->status) {
            'diterima_provider' => $p->simulasi ? 'Simulasi diterima' : 'Diterima provider',
            'antre' => 'Dalam antrean', 'gagal' => 'Gagal', 'dibatalkan' => 'Dibatalkan', default => 'Perlu rekonsiliasi',
        }]);
    }
}
