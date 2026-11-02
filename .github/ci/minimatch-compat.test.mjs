import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import test from 'node:test';

const require = createRequire(import.meta.url);

for (const mode of ['CommonJS', 'ESM']) {
    test(`matcher ESLint tetap memilih berkas dan menghormati pengecualian melalui ${mode}`, async () => {
        const load = mode === 'ESM' ? (name) => import(name) : (name) => require(name);
        const { ConfigArray } = await load('@eslint/config-array');
        const { FlatCompat } = await load('@eslint/eslintrc');
        const configs = new ConfigArray([
            { files: ['resources/js/**/*.{ts,tsx}'], ignores: ['**/*.test.tsx'] },
        ], { basePath: process.cwd() });
        configs.normalizeSync();

        const [legacy] = new FlatCompat({ baseDirectory: process.cwd() }).config({
            overrides: [{
                files: ['resources/js/**/*.{ts,tsx}'],
                excludedFiles: ['**/*.test.tsx'],
                rules: { 'no-debugger': 'error' },
            }],
        });

        for (const [file, matches] of [
            ['resources/js/Pages/Jadwal/Editor.tsx', true],
            ['resources/js/types/periode-jadwal.ts', true],
            ['resources/js/.hidden/Panel.tsx', true],
            ['resources/js/Pages/Jadwal/Editor.test.tsx', false],
            ['resources/js/Pages/Jadwal/Editor.css', false],
            ['tests/Frontend/JadwalEditor.test.tsx', false],
        ]) {
            const path = resolve(file);
            assert.equal(configs.getConfigStatus(path) === 'matched', matches, file);
            assert.equal(legacy.files.some((match) => match(path)), matches, file);
        }
    });
}
