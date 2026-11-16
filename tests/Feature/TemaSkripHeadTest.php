<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class TemaSkripHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_skrip_tema_gelap_dijalankan_di_head_sebelum_aset_vite(): void
    {
        // Mode hot memunculkan tag Vite tanpa manifest build.
        $hot = tempnam(sys_get_temp_dir(), 'vite-hot');
        file_put_contents($hot, 'http://vite.test');
        $this->withVite();
        Vite::useHotFile($hot);

        try {
            $html = (string) $this->get('/auth/logged-out')->assertOk()->getContent();
        } finally {
            unlink($hot);
        }

        $skrip = strpos($html, "if (localStorage.getItem('sakip-tema') === 'gelap') document.documentElement.dataset.tema = 'gelap';");
        $asetVite = strpos($html, 'http://vite.test/');

        $this->assertNotFalse($skrip, 'Skrip tema tidak ditemukan di HTML halaman.');
        $this->assertNotFalse($asetVite, 'Tag aset Vite tidak ditemukan di HTML halaman.');
        $this->assertLessThan($asetVite, $skrip);
        $this->assertLessThan((int) strpos($html, '</head>'), $skrip);
    }
}
