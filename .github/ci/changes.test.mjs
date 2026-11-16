import assert from "node:assert/strict";
import test from "node:test";
import { classifyChanges, isDocumentationOnly } from "./changes.mjs";

const base = "a".repeat(40);
const head = "b".repeat(40);

test("hanya daftar dokumentasi eksplisit yang boleh melewati pemeriksaan aplikasi", () => {
    assert.equal(
        isDocumentationOnly([
            "README.md",
            "document/SAKIP - PRD.md",
            "document/tabel.xlsx",
        ]),
        true,
    );
    for (const paths of [
        [],
        ["document/README.md", "app/Models/User.php"],
        ["resources/js/README.md"],
        ["DOCUMENT/README.md"],
        ["document/script.js"],
        [".github/workflows/ci.yml"],
        [".github/ci/changes.mjs"],
        ["tests/Frontend/StoragePolicy.test.tsx"],
        ["config/app.php"],
        ["database/migrations/2026_09_29_example.php"],
        ["package.json"],
        ["composer.json"],
        ["Containerfile"],
        ["composer.lock"],
        ["bun.lock"],
        ["unknown.txt"],
        ["new-directory/README.md"],
        ["document/README.md", "resources/js/app.tsx"],
        ["document/README.md", "unknown.txt"],
    ])
        assert.equal(isDocumentationOnly(paths), false, JSON.stringify(paths));
});

test("PR memakai seluruh diff merge-base dan memasukkan kedua path rename", () => {
    let capturedArgs;
    const result = classifyChanges(
        "pull_request",
        {
            pull_request: { base: { sha: base }, head: { sha: head } },
        },
        (args) => {
            capturedArgs = args;
            // Commit terakhir bisa docs; kode sebelumnya atau path asal rename tetap memerlukan CI penuh.
            return "app/old.php\0document/old.md\0";
        },
    );
    assert.deepEqual(capturedArgs, [
        "diff",
        "--name-only",
        "--no-renames",
        "-z",
        `${base}...${head}`,
        "--",
    ]);
    assert.equal(result.runApp, true);
});

test("push dokumen langsung memakai seluruh rentang push", () => {
    let capturedArgs;
    const result = classifyChanges(
        "push",
        { before: base, after: head },
        (args) => {
            capturedArgs = args;
            return "document/SAKIP - Plan Pengembangan.md\0README.md\0";
        },
    );
    assert.equal(capturedArgs[4], `${base}..${head}`);
    assert.deepEqual(result, { runApp: false, reason: "docs-only" });
});

test("dispatch, SHA kosong, metadata invalid, diff kosong, dan history hilang menjalankan CI penuh", () => {
    for (const [eventName, event] of [
        ["workflow_dispatch", {}],
        ["unknown_event", {}],
        ["push", null],
        ["push", []],
        ["pull_request", "invalid"],
        ["push", { before: "0".repeat(40), after: head }],
        ["pull_request", {}],
        ["push", { before: "--bad-revision", after: head }],
    ]) {
        assert.equal(
            classifyChanges(eventName, event, () => {
                throw new Error("Tidak boleh dipanggil");
            }).runApp,
            true,
        );
    }
    assert.equal(
        classifyChanges("push", { before: base, after: head }, () => "").runApp,
        true,
    );
    assert.equal(
        classifyChanges("push", { before: base, after: head }, () => {
            throw new Error("Missing history");
        }).runApp,
        true,
    );
});
