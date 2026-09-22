import { Link } from '@inertiajs/react';

export function RegulasiFailureNotice({ message }: { message: string }) {
    if (!message) return null;
    return <div role="alert" className="my-4 space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm">
        <p>{message}</p>
        <p>Salin input terlebih dahulu. Membuka data terbaru akan membuang draft; berkas perlu dipilih kembali.</p>
        <Link href="/regulasi" preserveState={false} className="text-primary underline">Periksa data terbaru</Link>
    </div>;
}
