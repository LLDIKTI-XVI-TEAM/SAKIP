import { useSyncExternalStore } from 'react';

export type Tema = 'terang' | 'gelap';

// Kunci dan atribut sama dengan skrip inline di resources/views/app.blade.php (Q89).
const KUNCI = 'sakip-tema';
const pendengar = new Set<() => void>();

/** Tema yang terpasang di <html data-tema>; nilai selain "gelap" berarti terang. */
export function temaAktif(): Tema {
    return document.documentElement.dataset.tema === 'gelap' ? 'gelap' : 'terang';
}

export function setTema(tema: Tema): void {
    if (tema === 'gelap') {
        document.documentElement.dataset.tema = 'gelap';
    } else {
        delete document.documentElement.dataset.tema;
    }

    try {
        localStorage.setItem(KUNCI, tema);
    } catch {
        // Penyimpanan diblokir (mis. mode privat): tema tetap berlaku sampai halaman dimuat ulang.
    }

    pendengar.forEach((dengar) => dengar());
}

function langganan(dengar: () => void): () => void {
    pendengar.add(dengar);

    return () => {
        pendengar.delete(dengar);
    };
}

export function useTema(): Tema {
    return useSyncExternalStore(langganan, temaAktif, () => 'terang');
}
