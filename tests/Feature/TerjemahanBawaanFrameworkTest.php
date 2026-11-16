<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Teks bawaan framework yang sampai ke pengguna harus berbahasa Indonesia pada locale `id`. */
class TerjemahanBawaanFrameworkTest extends TestCase
{
    public function test_label_navigasi_paginator_tidak_tampil_sebagai_kunci_mentah(): void
    {
        app()->setLocale('id');
        // Fallback locale proyek juga `id`, sehingga tanpa terjemahan paginator mengirim "pagination.previous".
        $this->assertSame('&laquo; Sebelumnya', __('pagination.previous'));
        $this->assertSame('Berikutnya &raquo;', __('pagination.next'));
    }
}
