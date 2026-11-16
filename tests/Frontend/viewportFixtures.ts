import { act } from '@testing-library/react';

/**
 * Mock window.matchMedia berbasis lebar viewport agar layout dapat diuji pada mode desktop dan mobile.
 * Query `(min-width: Npx)` dan `(max-width: Npx)` dievaluasi terhadap lebar saat ini;
 * listener `change` dipanggil ketika lebar diubah lewat setWidth().
 */
export function createViewportMock(initialWidth: number) {
    let width = initialWidth;
    const listeners = new Set<() => void>();
    const original = Object.getOwnPropertyDescriptor(window, 'matchMedia');

    const evaluate = (query: string): boolean => {
        const min = /\(min-width:\s*(\d+)px\)/.exec(query);
        const max = /\(max-width:\s*(\d+)px\)/.exec(query);
        return (!min || width >= Number(min[1])) && (!max || width <= Number(max[1]));
    };

    return {
        install() {
            Object.defineProperty(window, 'matchMedia', {
                configurable: true,
                writable: true,
                value: (query: string) => ({
                    media: query,
                    get matches() {
                        return evaluate(query);
                    },
                    onchange: null,
                    addEventListener: (_type: string, listener: () => void) => listeners.add(listener),
                    removeEventListener: (_type: string, listener: () => void) => listeners.delete(listener),
                    addListener: (listener: () => void) => listeners.add(listener),
                    removeListener: (listener: () => void) => listeners.delete(listener),
                    dispatchEvent: () => false,
                }),
            });
        },
        /** Mengatur lebar sebelum render, tanpa memicu listener. */
        reset(nextWidth: number) {
            width = nextWidth;
        },
        /** Mengubah lebar setelah render dan memberi tahu listener seperti perubahan ukuran jendela. */
        setWidth(nextWidth: number) {
            width = nextWidth;
            act(() => listeners.forEach((listener) => listener()));
        },
        listenerCount: () => listeners.size,
        restore() {
            listeners.clear();
            if (original) Object.defineProperty(window, 'matchMedia', original);
            else Reflect.deleteProperty(window, 'matchMedia');
        },
    };
}

/** jsdom belum menjalankan showModal/close pada <dialog>; cukup mensimulasikan atribut open. */
export function installDialogPolyfill(): () => void {
    const names = ['showModal', 'close'] as const;
    const originals = names.map((name) => Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, name));
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function (this: HTMLDialogElement) { this.open = true; } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function (this: HTMLDialogElement) { this.open = false; } });

    return () => names.forEach((name, index) => {
        const descriptor = originals[index];
        if (descriptor) Object.defineProperty(HTMLDialogElement.prototype, name, descriptor);
        else Reflect.deleteProperty(HTMLDialogElement.prototype, name);
    });
}
