import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import { http, HttpResponseError, type HttpResponse } from '@inertiajs/core';
import { afterEach, expect, it, vi } from 'vitest';
import CalculationPreview from '@/Pages/Pengukuran/CalculationPreview';
import { formatNilai } from '@/Pages/Pengukuran/formatNilai';

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

it.each([[401, 'Sesi kedaluwarsa'], [419, 'Sesi kedaluwarsa'], [403, 'Akses pratinjau ditolak'], [422, 'Input pratinjau tidak valid'], [503, 'Pratinjau belum tersedia']])('memberikan tindak lanjut sesuai kegagalan HTTP %s', async (status, message) => {
    vi.spyOn(http.getClient(), 'request').mockRejectedValueOnce(new HttpResponseError('Ditolak', { status: Number(status), data: '', headers: {} }));
    render(<CalculationPreview id="pengukuran-uji" satuan="%" desimalTampilan={2} komponen={[{ komponen_id: 'a', nilai: '80' }]} />);
    await screen.findByText(new RegExp(String(message)));
    expect(screen.queryByText('80,00 %')).toBeNull();
});

it('menampilkan desimal besar tanpa konversi float dan membulatkan hanya untuk tampilan', () => {
    expect(formatNilai('9007199254740993.125', 2)).toBe('9.007.199.254.740.993,13');
    expect(formatNilai('-999999999999999999.995', 2)).toBe('-1.000.000.000.000.000.000,00');
    expect(formatNilai('0.000000000001', 12)).toBe('0,000000000001');
});

it('mengambil pratinjau server, membatalkan request lama dan mengabaikan hasil basi', async () => {
    const request = vi.spyOn(http.getClient(), 'request');
    let finishOld: ((response: HttpResponse) => void) | undefined;
    request.mockImplementationOnce(() => new Promise((resolve) => { finishOld = resolve; }));
    request.mockResolvedValueOnce({ status: 200, data: JSON.stringify({ nilai: '90.00', status_perhitungan: 'terhitung' }), headers: {} });
    const props = { id: 'pengukuran-uji', satuan: '%', desimalTampilan: 2, komponen: [{ komponen_id: 'a', nilai: '80' }] };
    const { rerender } = render(<CalculationPreview {...props} />);
    await waitFor(() => expect(request).toHaveBeenCalledTimes(1));
    rerender(<CalculationPreview {...props} komponen={[{ komponen_id: 'a', nilai: '90' }]} />);
    expect(screen.queryByText('80,00 %')).toBeNull();
    await screen.findByText('90,00 %');
    expect(request.mock.calls[0][0].signal?.aborted).toBe(true);
    expect(JSON.parse(String(request.mock.calls[1][0].data))).toEqual({ komponen: [{ komponen_id: 'a', nilai: '90' }] });
    await act(async () => { finishOld?.({ status: 200, data: JSON.stringify({ nilai: '80.00', status_perhitungan: 'terhitung' }), headers: {} }); });
    expect(screen.queryByText('80,00 %')).toBeNull();
    expect(screen.getByText('90,00 %')).toBeTruthy();
});

it('menampilkan penyebut nol dan kegagalan tanpa menyajikan angka lama sebagai hasil baru', async () => {
    const request = vi.spyOn(http.getClient(), 'request').mockResolvedValueOnce({ status: 200, data: JSON.stringify({ nilai: null, status_perhitungan: 'tidak_dapat_dihitung' }), headers: {} });
    const props = { id: 'pengukuran-uji', satuan: '%', desimalTampilan: 2, komponen: [{ komponen_id: 'a', nilai: '0' }] };
    const { rerender } = render(<CalculationPreview {...props} />);
    await screen.findByText('Tidak dapat dihitung');
    request.mockRejectedValueOnce(new Error('Koneksi putus'));
    rerender(<CalculationPreview {...props} komponen={[{ komponen_id: 'a', nilai: '' }]} />);
    await screen.findByText(/Pratinjau belum tersedia/);
    expect(screen.queryByText('Tidak dapat dihitung')).toBeNull();
});
