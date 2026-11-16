import { appendFileSync, readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { pathToFileURL } from "node:url";

export function isDocumentationOnly(paths) {
    return (
        paths.length > 0 &&
        paths.every(
            (path) =>
                path === "README.md" ||
                (path.startsWith("document/") &&
                    /\.(md|txt|pdf|docx|xlsx|png|jpe?g|svg|webp|drawio)$/i.test(
                        path,
                    )),
        )
    );
}

export function classifyChanges(eventName, event, git) {
    const full = { runApp: true, reason: "full" };
    if (!["push", "pull_request"].includes(eventName)) return full;
    if (!event || typeof event !== "object" || Array.isArray(event)) return full;

    const base =
        eventName === "pull_request"
            ? event.pull_request?.base?.sha
            : event.before;
    const head =
        eventName === "pull_request"
            ? event.pull_request?.head?.sha
            : event.after;
    const validSha = (sha) =>
        typeof sha === "string" &&
        /^[a-f0-9]{40,64}$/i.test(sha) &&
        !/^0+$/.test(sha);
    if (!validSha(base) || !validSha(head)) return full;

    try {
        // PR memakai seluruh delta sejak merge-base; push memakai seluruh rentang before..after.
        // --no-renames memasukkan path lama dan baru agar pemindahan kode ke document tidak lolos.
        const range = `${base}${eventName === "pull_request" ? "..." : ".."}${head}`;
        const paths = git([
            "diff",
            "--name-only",
            "--no-renames",
            "-z",
            range,
            "--",
        ])
            .split("\0")
            .filter(Boolean);
        return isDocumentationOnly(paths)
            ? { runApp: false, reason: "docs-only" }
            : full;
    } catch {
        // History/event yang tidak dapat diverifikasi selalu menjalankan seluruh pemeriksaan.
        return full;
    }
}

if (
    process.argv[1] &&
    import.meta.url === pathToFileURL(process.argv[1]).href
) {
    let result = { runApp: true, reason: "full" };
    try {
        const event = JSON.parse(
            readFileSync(process.env.GITHUB_EVENT_PATH, "utf8"),
        );
        result = classifyChanges(
            process.env.GITHUB_EVENT_NAME,
            event,
            (args) => {
                const command = spawnSync("git", args, {
                    encoding: "utf8",
                    maxBuffer: 16 * 1024 * 1024,
                });
                if (command.error || command.status !== 0)
                    throw new Error("Diff Git tidak tersedia.");
                return command.stdout;
            },
        );
    } catch {
        // Metadata hilang/tidak valid tidak boleh mematikan CI aplikasi.
        result = { runApp: true, reason: "full" };
    }
    console.log(`Jalur CI: ${result.reason}`);
    appendFileSync(process.env.GITHUB_OUTPUT, `run_app=${result.runApp}\n`);
    if (process.env.GITHUB_STEP_SUMMARY) {
        appendFileSync(
            process.env.GITHUB_STEP_SUMMARY,
            result.runApp
                ? "Seluruh pemeriksaan aplikasi dijalankan.\n"
                : "Hanya dokumentasi: pemeriksaan aplikasi dilewati. Tidak ada aturan branch yang diubah.\n",
        );
    }
}
