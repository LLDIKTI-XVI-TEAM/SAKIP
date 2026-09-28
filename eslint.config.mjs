import babelParser from "@babel/eslint-parser";
import reactHooks from "eslint-plugin-react-hooks";

export default ["ts", "tsx"].map((extension) => ({
    files: [
        `resources/js/**/*.${extension}`,
        `tests/Frontend/**/*.${extension}`,
    ],
    languageOptions: {
        // Parser syntax-only: typescript-eslint belum mendukung TypeScript 7.
        // Pemeriksaan tipe tetap dijalankan terpisah oleh bun run typecheck.
        parser: babelParser,
        parserOptions: {
            requireConfigFile: false,
            babelOptions: {
                babelrc: false,
                configFile: false,
                plugins: [
                    [
                        "@babel/plugin-syntax-typescript",
                        { isTSX: extension === "tsx" },
                    ],
                ],
            },
        },
    },
    plugins: { "react-hooks": reactHooks },
    rules: {
        "constructor-super": "error",
        "for-direction": "error",
        "no-async-promise-executor": "error",
        "no-constant-condition": "error",
        "no-dupe-else-if": "error",
        "no-unsafe-finally": "error",
        "no-unsafe-optional-chaining": "error",
        "valid-typeof": "error",
        "react-hooks/rules-of-hooks": "error",
        "react-hooks/exhaustive-deps": "error",
    },
}));
