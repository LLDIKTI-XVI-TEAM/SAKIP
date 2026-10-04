<?php

namespace Tests\Support;

use App\Models\IndikatorKinerja;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;

/** Membentuk intent satu operasi untuk menguji boundary atomik pengganti CRUD. */
trait SubmitsIndicatorDefinition
{
    protected function createKomponen(string $id, array $data): TestResponse
    {
        return $this->submitKomponen('post', $id, null, $data);
    }

    protected function createKomponenJson(string $id, array $data): TestResponse
    {
        return $this->submitKomponen('post', $id, null, $data, true);
    }

    protected function updateKomponen(string $id, string $childId, array $data): TestResponse
    {
        return $this->submitKomponen('put', $id, $childId, $data);
    }

    protected function updateKomponenJson(string $id, string $childId, array $data): TestResponse
    {
        return $this->submitKomponen('put', $id, $childId, $data, true);
    }

    protected function deleteKomponen(string $id, string $childId, array $data): TestResponse
    {
        return $this->submitKomponen('delete', $id, $childId, $data);
    }

    protected function deleteKomponenJson(string $id, string $childId, array $data): TestResponse
    {
        return $this->submitKomponen('delete', $id, $childId, $data, true);
    }

    protected function submitKomponen(string $method, string $id, ?string $childId, array $data, bool $json = false): TestResponse
    {
        $row = Arr::only($data, ['kode', 'label', 'satuan', 'peran', 'bobot', 'urutan', 'aktif']);
        if ($childId !== null) {
            $row['id'] = $childId;
        }
        $payload = array_merge(Arr::only($data, ['expected_updated_at', 'alasan']), [
            'tipe_perhitungan' => IndikatorKinerja::findOrFail($id)->tipe_perhitungan,
            'komponen' => $method === 'delete' ? [] : [$row],
            'hapus_komponen_ids' => $method === 'delete' ? [$childId] : [],
        ]);
        $url = "/perencanaan/indikator/{$id}/formula";

        return $json ? $this->patchJson($url, $payload) : $this->patch($url, $payload);
    }
}
