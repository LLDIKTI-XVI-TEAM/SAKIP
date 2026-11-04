<?php

namespace App\Services\Notifikasi;

/** Hanya klasifikasi aman; adapter tidak meneruskan body atau exception provider. */
final readonly class HasilKirim
{
    private function __construct(public string $jenis, public ?string $rujukan = null, public int $jeda = 60) {}

    public static function diterima(?string $rujukan = null): self
    {
        return new self('diterima', $rujukan);
    }

    public static function sementara(int $jeda = 60): self
    {
        return new self('sementara', jeda: max(1, $jeda));
    }

    public static function permanen(): self
    {
        return new self('permanen');
    }

    public static function ambigu(): self
    {
        return new self('ambigu');
    }
}
