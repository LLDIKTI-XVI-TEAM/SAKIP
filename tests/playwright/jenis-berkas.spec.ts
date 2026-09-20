import { test, expect } from '@playwright/test';

test.describe('Konfigurasi Persyaratan Jenis Berkas [ISS-11.01]', () => {
    test('Pengujian antarmuka lengkap: index, tambah, ubah dengan alasan audit', async ({ page }) => {
        // 1. Login sebagai Perencana
        await page.goto('http://127.0.0.1:8000/login');
        await page.fill('input[type="email"]', 'perencanaan@lldikti16.kemdikbud.go.id');
        await page.fill('input[type="password"]', 'password');
        await page.click('button:has-text("Masuk ke Sistem")');

        // 2. Buka Halaman Jenis Berkas
        await page.goto('http://127.0.0.1:8000/jenis-berkas');
        await expect(page.locator('h1')).toContainText('Konfigurasi Jenis Berkas');
        await page.screenshot({ path: 'docs/screenshots/jenis_berkas_index_view.png' });

        // 3. Tambah Jenis Berkas
        await page.click('button:has-text("Tambah Jenis Berkas")');
        await page.fill('input[placeholder*="Laporan"]', 'Laporan Capaian Output');
        await page.selectOption('select', 'pengukuran');
        await page.check('input[type="checkbox"][id*="file"]');
        await page.check('input[type="checkbox"][id*="tautan"]');
        await page.screenshot({ path: 'docs/screenshots/jenis_berkas_create_modal.png' });
        await page.click('button:has-text("Simpan Konfigurasi")');

        // 4. Verifikasi muncul di tabel
        await expect(page.locator('table')).toContainText('Laporan Capaian Output');

        // 5. Ubah Jenis Berkas (Memicu Alasan Audit)
        await page.click('button:has-text("Ubah")');
        await page.fill('input[placeholder*="Laporan"]', 'Laporan Capaian Output Triwulan');
        await page.click('button:has-text("Simpan Konfigurasi")');

        // 6. Modal Alasan Audit
        await expect(page.locator('text=Alasan Perubahan Data Sensitif')).toBeVisible();
        await page.screenshot({ path: 'docs/screenshots/jenis_berkas_audit_reason_modal.png' });
        await page.fill('textarea', 'Pembaruan nomenklatur jenis berkas sesuai arahan tim kerja');
        await page.click('button:has-text("Konfirmasi Simpan")');

        // 7. Verifikasi pembaruan tabel
        await expect(page.locator('table')).toContainText('Laporan Capaian Output Triwulan');
        await page.screenshot({ path: 'docs/screenshots/jenis_berkas_updated_table.png' });
    });
});
