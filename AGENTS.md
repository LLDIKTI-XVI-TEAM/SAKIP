# AGENTS.md — Panduan Agent SAKIP

## Kedudukan dan tujuan

Panduan ini berlaku untuk seluruh agent yang bekerja di workspace SAKIP LLDIKTI Wilayah XVI. Panduan ini dapat digunakan pada checkout anggota tim yang berbeda; requirement produk, stack, model akses, dan alur bisnis harus berasal dari dokumen SAKIP.

Instruksi sistem/developer tetap lebih tinggi. Instruksi pengguna yang secara eksplisit merevisi aturan lokal berlaku untuk cakupan revisinya. Konflik yang belum diputuskan harus dijelaskan dan diklarifikasi; jangan mengabaikan guardrail diam-diam atau meminta persetujuan ulang untuk tindakan yang sudah diizinkan.

Keringkasan bukan alasan menghilangkan validasi, authorization, audit, keamanan berkas, aksesibilitas, error handling, komentar penting, atau pengujian yang diperlukan. Gunakan Bahasa Indonesia untuk komunikasi, laporan, dan komentar domain. Panduan ini bukan izin implementasi seluruh roadmap.

## 1. Lokasi kerja dan kepemilikan dokumen

Lokasi `AGENTS.md` dan `SAKIP_ENGINEERING_STANDARDS.md` mengikuti pengaturan pengguna: boleh di dalam atau di luar repository, dan tidak harus berdampingan. Rujukan nama kedua panduan berarti file yang digunakan pada workspace ini, bukan path relatif dari satu panduan ke panduan lain. Gunakan lokasi yang diberikan pengguna atau konfigurasi agent; bila belum diketahui, cari secara terbatas di workspace terkait. Jika tidak ditemukan atau ada beberapa salinan yang ambigu, minta lokasi yang dimaksud, jangan membuat pengganti atau memilih diam-diam.

Path kode seperti `app/`, `resources/`, dan `tests/`, serta dokumen tim `document/`, mengacu pada root repository aplikasi SAKIP yang telah diverifikasi, bukan lokasi panduan atau working directory shell. Verifikasi root Git sebelum operasi; jangan memindahkan checkout atau menata ulang folder tanpa instruksi.

- `AGENTS.md`: aturan kerja stabil; tidak memuat snapshot branch, jumlah test, atau deadline sementara.
- `SAKIP_ENGINEERING_STANDARDS.md`: aturan teknis dan verifikasi, dibaca sesuai bagian relevan.
- `document/`: requirement, model, workflow, design system, dan planning tim; bukan artefak lokal agent.
- Context/handoff atau catatan lokal lain bersifat opsional bila disediakan pengguna; bukan prasyarat penggunaan dua panduan ini dan bukan pengganti keputusan kanonis.

**Artefak lokal:** gunakan direktori di luar repository aplikasi sesuai setup pengguna. Tentukan path absolutnya sebelum menjalankan tool; simpan screenshot, video, log, dan laporan QA dalam subfolder `qa-artifacts/<task-atau-pr>/`. Output Playwright dan cache Graphify juga berada di luar repository bila digunakan. Jangan menganggap tool atau folder sudah tersedia; buat folder hanya saat diperlukan dalam scope tugas.

Jangan membuat folder QA/Playwright/Graphify di repository aplikasi. Periksa konfigurasi output sebelum menjalankan tool. Jika tool tidak mendukung pemisahan working directory dan lokasi output, laporkan keterbatasannya sebelum operasi yang membuat artefak di lokasi terlarang; gunakan alternatif yang memenuhi aturan. Artefak existing yang salah lokasi tidak boleh dipindahkan, ditimpa, atau dihapus otomatis.

**Workbook referensi:** bila tugas memerlukan `Pengukuran Kinerja  Triwulan 2026.xlsx`, temukan file yang disediakan pengguna atau tim; jangan mengasumsikan path komputer tertentu. Gunakan untuk menelusuri contoh data dan formula, bukan menggantikan keputusan produk dalam dokumen tim. Angka, label, dan rumusnya tidak otomatis menjadi requirement yang disahkan; verifikasi file sebelum mengandalkan analisis sebelumnya. Jangan mengubah workbook tanpa instruksi pengguna.

## 2. Baca sebelum bekerja

1. Baca seluruh `AGENTS.md` pada awal sesi. Jika pengguna menyediakan context/handoff, baca bagian relevan dan verifikasi ulang faktanya sebelum tindakan.
2. Pada orientasi awal, baca PRD bagian ringkasan, scope MVP/deferred, arsitektur, serta indeks bagian domain. Untuk tugas konkret, baca penuh bagian PRD dan acceptance criteria yang berkaitan.
3. Baca bagian Data Model, Workflow, dan task/Dependency/DoD Plan yang terkait sebelum plan, kode, review, atau delegasi domain tersebut. Pembacaan berat boleh didelegasikan sesuai bagian 10.
4. Gunakan indeks `SAKIP_ENGINEERING_STANDARDS.md`; baca bagian relevan secara penuh saat aturan itu berlaku. Tidak perlu mencerna seluruh standar atau semua dokumen besar untuk setiap tugas kecil.
5. Untuk pekerjaan UI, baca `document/design-system.md` dan komponen existing yang digunakan. Pada perubahan halaman/form, baca engineering standards §2, §4, dan §9–11 sesuai dampak: kontrak props, state, lifecycle form, navigasi, serta verifikasinya.

Jika dokumen atau context yang tersedia terbukti stale, sebutkan informasi yang berbeda beserta bukti terbaru, lalu tawarkan pembaruan/sinkronisasi kepada pengguna. Usia dokumen saja bukan bukti bahwa isinya salah. Jangan memperbarui context/planning lokal tanpa permintaan atau persetujuan eksplisit; izin yang sudah diberikan tidak perlu ditanyakan ulang. Untuk pekerjaan aktif, gunakan fakta terbaru yang terverifikasi sesuai prioritas sumber dan lanjutkan bagian yang tidak bergantung pada keputusan terbuka. Permintaan membuat atau memperbarui panduan merupakan izin untuk dokumen yang diminta, bukan izin mengubah aplikasi.

### Pemeliharaan dokumen

AGENTS.md dan SAKIP_ENGINEERING_STANDARDS.md dapat diperbarui seiring perkembangan proyek: informasi baru, isi yang terbukti stale, struktur yang lebih jelas, atau pola dan praktik coding yang lebih tepat. Agent harus mengusulkan pembaruan kepada pengguna terlebih dahulu dengan menyebut bagian terdampak, alasan, serta bukti atau manfaatnya; terapkan hanya setelah disetujui. Izin yang sudah diberikan untuk perubahan tersebut tidak perlu diminta ulang. Jangan mengubah dokumen otomatis atau memperlakukan usulan sebagai aturan yang sudah berlaku; instruksi pengguna dan prioritas sumber tetap diutamakan. Panduan ini tidak memberikan izin pemeliharaan otomatis terhadap catatan lokal atau dokumen lain.

## 3. Sumber keputusan dan anti-hallucination

Urutan kebijakan kerja: instruksi pengguna terbaru yang eksplisit; keputusan/addendum SAKIP yang terbukti disetujui dan menggantikan keputusan lama; dokumen tim sesuai tanggung jawabnya; spec/context lokal; tracker dan histori.

| Sumber di `document/`                  | Tanggung jawab                                                                         |
| -------------------------------------- | -------------------------------------------------------------------------------------- |
| `SAKIP - PRD.md`                       | Tujuan, scope, requirement, kontrak produk/teknis, acceptance criteria                 |
| `SAKIP - Data Model.md`                | Entitas, relasi, constraints, integritas data; periksa status draf/pengesahannya       |
| `SAKIP - Workflow.md`                  | Aktor, transisi, prasyarat, jendela waktu, buka kembali                                |
| `SAKIP - Plan Pengembangan.md`         | Task bernomor, dependency, scope, DoD; bukan bukti implementasi selesai                |
| `design-system.md`                     | Kontrak UI dan komponen; tidak mengubah aturan bisnis                                  |
| Transkrip rapat dan workbook referensi | Bukti konteks/keputusan/data yang harus diperiksa tanggal, cakupan, dan konsistensinya |

Ini pembagian tanggung jawab, bukan pernyataan bahwa seluruh dokumen sudah final atau hierarki otomatis PRD mengalahkan semua dokumen lain. Bila sumber bertentangan tanpa pengganti eksplisit, catat kedua ketentuan beserta lokasinya dan minta keputusan sebelum mengimplementasikan bagian yang terdampak. Lanjutkan pekerjaan independen yang tidak bergantung pada konflik.

Kode, test, lockfile, konfigurasi, dan hasil eksekusi membuktikan status implementasi; tidak otomatis mengesahkan requirement baru. Jangan menyalin source contoh dalam dokumen sebagai kode siap pakai tanpa verifikasi kontrak dan keamanan.

Jangan mengarang:

- formula, presisi, pembulatan, agregasi lintas periode, atau perlakuan nilai kosong;
- permission, scope unit, pengecualian deny, hak self-approval, atau peran PIC;
- batas waktu, timezone, koreksi snapshot, pembukaan kembali, atau aturan backfill;
- field database, payload, nama claim Keycloak, pengaturan realm/client, atau kontrak integrasi;
- jenis bukti dukung, pengecualian kelengkapan, audit masking/retention, atau penerima/channel notifikasi;
- ID user story, nomor issue aktif, ownership, final merge authority, deadline, atau status deployment/UAT.

Jika belum jelas: periksa sumber domain dan keputusan terkait serta context bila tersedia → source/test untuk status implementasi → tandai `Open Question` dan ajukan pertanyaan terarah. Bedakan `Dikonfirmasi dokumen`, `Terverifikasi implementasi`, `Inferensi engineering`, dan `Memerlukan keputusan stakeholder`. Inferensi bukan aturan produk baru.

## 4. Batas proyek SAKIP

SAKIP merupakan aplikasi terpisah dari SIMPEG, termasuk kode dan database. Jangan menghubungkan tabel, memakai database SIMPEG, atau memindahkan aturan kepegawaian/cuti/EWS ke SAKIP. Jabatan anggota tim dan kewenangan merge mengikuti penugasan SAKIP yang terverifikasi; jangan diwariskan dari SIMPEG.

Scope MVP mengacu PRD §5: autentikasi/akses, regulasi dan Renstra, sasaran/indikator/komponen, target tahunan/PK, jadwal/periode, penanggung jawab, rencana aksi, kegiatan/klaim, pengukuran/verifikasi/pengesahan, bukti dukung, status capaian, rekomendasi pimpinan, dashboard/laporan Excel, setelan, dan audit. Daftar ini adalah batas produk, bukan daftar pekerjaan otomatis atau klaim selesai.

Fitur yang ditunda atau di luar scope menurut dokumen harus tetap demikian sampai ada keputusan: approval Pimpinan dalam workflow, formula bertingkat, ekspor PDF/gambar grafik, status otomatis dari sistem lain, matrix permission penuh, impor massal, modul anggaran penuh, dan integrasi eksternal. Target tahunan PK berbeda dari target periode rencana aksi; jangan menyamakan mekanisme revisinya. Jika scope notifikasi bertentangan antar sumber, laporkan konflik dan minta keputusan; jangan menyelesaikannya dengan menyalin SIMPEG.

Kontrak stack berasal dari PRD §6 dan Plan: Laravel + Inertia + React/TypeScript + Tailwind, PostgreSQL, Keycloak/OIDC berbasis session, dan Bun untuk toolchain frontend. Versi dependency aktual dibaca dari manifest/lockfile. Jangan membuat API terpisah, mengganti frontend menjadi Blade/Alpine, atau menambah framework tanpa kebutuhan dan izin. Ketidaksesuaian implementasi terhadap dokumen harus dilaporkan, bukan diperbaiki massal di luar tugas.

## 5. Scope, keselamatan Git, dan data

- Tugas jawab/analisis/review/audit/status bersifat read-only kecuali perubahan diminta. Implementasi hanya mengizinkan perubahan dalam scope.
- Jangan commit, push, membuat PR, membalas GitHub, atau merge tanpa instruksi eksplisit. Izin implementasi bukan izin publikasi; izin push bukan izin merge. Hormati urutan publikasi yang diminta.
- **Jangan menggunakan worktree.** Kerjakan pada checkout yang disepakati dan lindungi perubahan yang tidak terkait.
- Jangan reset/overwrite perubahan, mengganti branch secara berisiko, menghapus/memindahkan file, atau melakukan operasi database destruktif tanpa otorisasi yang jelas. Periksa target absolut, dampak, dan pemulihannya.
- Jangan menganggap data development/QA boleh dihapus. Jangan menambahkan dukungan legacy atau migrasi production spekulatif; status deployment/data harus diverifikasi sesuai tugas.
- Sebelum test yang memutasi database, periksa environment efektif runner dan server, host/port/database, config cache, serta isolasi disposable. `APP_ENV=testing` saja tidak membuktikan database aman. Test yang menjalankan migration/reset wajib memakai database testing disposable yang terverifikasi, bukan database development. Migration development dalam tugas yang diizinkan tetap harus diperiksa target dan dampaknya; reset atau migration destruktif memerlukan otorisasi eksplisit, verifikasi target, dan mekanisme pemulihan sesuai batas tindakan di atas. Database SIMPEG tidak boleh digunakan untuk operasi SAKIP.
- Jangan menjalankan worker/scheduler, seed/migrate, mengirim notifikasi nyata, atau memasang dependency hanya untuk orientasi. Jalankan hanya bila diperlukan dan diizinkan tugas; periksa efek sampingnya.
- Jangan mengungkap credential, token, cookie, session, payload sensitif, atau data riil di log, fixture, screenshot, komentar publik, dan commit.

Kedua panduan ini (`AGENTS.md`, `SAKIP_ENGINEERING_STANDARDS.md`), panduan/config agent pribadi lain, spec/handoff lokal, serta artefak QA/Playwright/Graphify tidak boleh di-commit/push kecuali pengguna meminta path tersebut secara eksplisit. Larangan ini tidak mencakup seluruh `document/` tim; perubahan dokumen tim tetap membutuhkan scope tugas yang sesuai. Jika file lokal untracked, biarkan untracked. Jangan menambah `.gitignore` hanya untuk menyembunyikan file lokal; pertahankan aturan existing tanpa cleanup otomatis.

Sebelum commit yang diizinkan: tampilkan status repository yang benar, periksa seluruh diff dan staged paths, stage file scope secara eksplisit, serta pastikan tidak ada rahasia/data sensitif/artefak lokal. Hindari blanket staging.

## 6. Commit dan Pull Request

### Commit

- Gunakan Conventional Commits dengan prefix Inggris dan deskripsi Bahasa Indonesia: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`.
- Contoh: `fix: cegah pengesahan pengukuran dari status yang tidak sesuai`.
- Gabungkan satu concern yang aman direview/revert bersama. Pisahkan concern independen; hindari micro-commit per file atau refactor mekanis.
- Untuk perubahan non-trivial, sertakan body ringkas yang menjelaskan perubahan, alasan domain/desain penting, dan bukti verifikasi relevan. Jangan hanya subject yang tidak informatif.
- Jangan menambah footer AI/co-author/generated kecuali diminta. Jangan menulis ulang histori hanya untuk menyeragamkan bahasa.

### Pull Request

- Judul dan body Bahasa Indonesia. Jelaskan masalah, perilaku hasil perubahan, batas scope, catatan implementasi penting, dan bukti pengujian.
- Tinjau seluruh branch diff dan commit yang masuk PR, bukan hanya commit terakhir.
- Wajib ada bagian **Traceability Requirement/Task**: petakan perubahan ke bagian PRD, nomor/judul task Plan dan DoD yang benar, serta issue aktif bila sudah diverifikasi. Pisahkan requirement yang selesai dari yang hanya disentuh/terdampak. Jika tidak ada requirement/task yang cocok secara langsung, nyatakan hal tersebut dan jelaskan sifat serta alasan perubahan; jangan memaksakan pemetaan. Jangan mengarang `US-*` atau mengklaim seluruh modul selesai karena satu slice.
- Sertakan Mermaid ringkas untuk perubahan workflow/transisi/data flow bila memperjelas review.
- Perubahan tampilan memerlukan bukti browser pada route dan viewport relevan. Simpan di direktori artefak lokal di luar repository sesuai bagian 1, dalam `qa-artifacts/<task-atau-pr>/`.
- Jika tool tidak dapat melampirkan gambar, sediakan placeholder `<!-- LETAKKAN GAMBAR DI SINI: nama-berkas.png -->`, jelaskan apa yang dibuktikan, dan berikan pemetaan file kepada pengguna. Jangan mengklaim screenshot terlampir jika hanya placeholder.
- Catat command, baseline commit/working diff, environment/database, hasil, skip/failure, dan batas cakupan. PASS lokal bukan CI hijau; test otomatis bukan UAT/persetujuan stakeholder.

## 7. Disiplin implementasi dan kualitas

Ikuti bagian relevan `SAKIP_ENGINEERING_STANDARDS.md`; aturan penulisan kode terperinci dimiliki dokumen tersebut.

- Sebelum memakai API/pola Laravel, Inertia, atau React, cocokkan dokumentasi resmi dengan manifest/lockfile proyek dan periksa runtime terpasang bila diperlukan tugas. Jangan membawa asumsi versi lama, Next.js, atau SPA dengan API terpisah ke SAKIP.
- Bedakan aturan wajib SAKIP, perilaku framework yang didokumentasikan, dan optimasi bersyarat. Riset/contoh eksternal tidak mengganti keputusan proyek atau memberi izin menambah dependency, memperluas scope, maupun merombak aplikasi.

- Pahami acceptance criteria dan invariant sebelum mengubah kode; review kritis spec/plan aktif terhadap dokumen dan HEAD.
- Nyatakan tujuan, batas scope/non-goals, acceptance criteria, dan bagian yang tetap tidak disentuh secara proporsional. Untuk tugas kecil cukup penjelasan singkat; jangan mengulang checklist panjang sebelum setiap command.
- Sebelum fitur substansial, pastikan keputusan domain yang relevan sudah jelas, misalnya izin/data scope unit, model/database, transisi/jendela waktu, kalkulasi/target, snapshot, bukti dukung, dan audit. Pilih sesuai dampak fitur; bukan kewajiban membahas semua domain atau membuat dokumen baru untuk setiap tugas. Ketidakjelasan yang material harus diselesaikan sebelum jalur terdampak diimplementasikan.
- Klarifikasi ketidakpastian bisnis, izin, atau dampak material. Untuk pilihan engineering rutin dalam scope, gunakan pendekatan paling sederhana yang memenuhi aturan dan jelaskan asumsi yang relevan; tidak perlu meminta keputusan pengguna untuk setiap pilihan teknis.
- Pertahankan sinkronisasi backend, UI, permission/data scope, audit, dan test pada fitur yang ditugaskan. Jika surface yang disentuh memakai dummy data, hubungkan ke data nyata dalam slice tersebut; jangan membersihkan seluruh aplikasi.
- Saat menyentuh UI/frontend, evaluasi kualitas tampilan dan pengalaman pengguna pada alur yang berubah: kejelasan informasi dan aksi, konsistensi design system, responsivitas, aksesibilitas, serta feedback interaksi. Verifikasi di browser sesuai scope; kelulusan typecheck/build saja tidak membuktikan kualitas UI/UX. Ikuti rincian engineering standards §9–11.
- Perbaiki bug/pelanggaran langsung terkait perubahan atau yang diperlukan agar perubahan aman. Laporkan temuan tak terkait dahulu, meski berada di file yang sama.
- Authorization, validasi bisnis, kalkulasi indikator, dan audit ditegakkan di server. Frontend hanya menampilkan hasil/capability; menu tersembunyi bukan pengaman.
- Pertahankan performa: pagination/filter/sort server-side, eager loading eksplisit, payload Inertia bounded, dan agregasi terukur. Jangan memuat seluruh dataset ke React atau menambah polling global tanpa kebutuhan dan bukti.
- Jangan melemahkan gate dengan `as any`, suppression, catch kosong, menghapus test, menghilangkan validasi, atau mengubah expected value tanpa dasar kontrak.
- Gunakan solusi terkecil yang memenuhi requirement dan keamanan/data. Tidak ada kewajiban menambah DTO, repository layer, adapter generik, atau struktur domain bertingkat untuk kasus sederhana.

## 8. Anti-overengineering dan penyelesaian

- Jangan membuat abstraksi, toggle, compatibility layer, implementasi paralel, atau dependency untuk kebutuhan hipotetis.
- Jangan mengubah kontrak response, nama field, permission, skema, atau struktur modul secara oportunistis. Refactor menjaga perilaku dan memiliki bukti regression.
- Gunakan spec/plan yang telah disetujui. Jangan membuat salinan rencana atau mengedit context/planning otomatis di luar permintaan.
- Besarnya verifikasi mengikuti risiko. Jangan memperluas scope untuk mengejar arsitektur sempurna, test semua modul, atau sekadar karena satu file disentuh.
- Jangan membuat gate baru yang menolak pengecualian bisnis eksplisit seperti jalur bukti dukung/backfill; baca kontrak sebelum memperketat perilaku.
- Jika pendekatan mulai menyentuh banyak concern tak terkait, persempit dan jelaskan kebutuhan yang benar-benar terbukti.

## 9. Testing, review, dan bukti

- TDD wajib untuk kalkulasi kinerja, aturan hak akses/data scope yang kompleks, transaksi/transisi kritis, snapshot, serta regresi kritis: buktikan RED yang sesuai masalah → implementasi → GREEN.
- CRUD sederhana boleh test-after. Copy/styling/dokumentasi tidak wajib TDD; tetap periksa diff dan hasil yang relevan. Perubahan UI memerlukan browser smoke sesuai risiko.
- Routing, middleware, konfigurasi, dan komponen React yang memengaruhi authorization, data scope, validasi, atau alur penting tetap memerlukan verifikasi sesuai risiko. Tidak wajib strict TDD bukan berarti tidak perlu test; jumlah baris atau label cosmetic bukan ukuran risiko.
- Periksa coverage existing sebelum menulis test. Tambahkan atau perluas test hanya untuk requirement, risiko, atau regresi penting yang belum terlindungi; bila coverage sudah cukup, jalankan test relevan tanpa otomatis menambah test baru. Pilih lapisan paling ringan yang tetap membuktikan kontrak, hindari duplikasi dan matriks spekulatif, serta perhatikan biaya eksekusi sesuai engineering standards §11. Efisiensi tidak boleh mengurangi coverage kritis atau menjadi batas jumlah test yang arbitrer.
- DB-sensitive behavior harus diuji pada PostgreSQL disposable; SQLite-only tidak cukup untuk UUID/JSON/FK/index/locking/transaksi.
- Focused tests selama implementasi; final gate mengikuti risiko, standar engineering, dan konfigurasi aktual. Jangan mengulang full suite tanpa perubahan/failure yang relevan.
- Jangan menganggap `composer qa`, PHPStan, Pest, lane paralel, Dusk, atau CI sudah tersedia. Bedakan standar yang ditetapkan dengan tooling yang belum disiapkan; laporkan gate yang belum dapat dijalankan.
- Feedback reviewer manusia/AI adalah hipotesis. Verifikasi pada HEAD, caller, requirement, test, dan reproduksi. Bedakan bug valid, false positive, trade-off, dan keputusan bisnis; perbaiki hanya temuan valid dalam scope.
- Sebelum selesai, baca sendiri file yang berubah, periksa diff/side effect, jalankan verifikasi terdampak setelah fix review, dan cocokkan hasil dengan acceptance criteria.
- Jangan menyatakan PR-ready jika gate wajib gagal/belum dijalankan. Bedakan kegagalan akibat perubahan, pre-existing, serta keterbatasan environment. Laporan final menyebut perubahan, bukti, dan risiko/blocker yang masih relevan.

### Penanganan review Pull Request

1. Baca komentar beserta konteks diff, file/caller terkait, dan HEAD PR terbaru. Bedakan komentar pada versi lama dari masalah yang masih berlaku; jangan memperbaiki berdasarkan kutipan terpotong atau status thread saja.
2. Verifikasi setiap temuan terhadap requirement, kode, test, dan reproduksi yang relevan sebelum menerima atau menolaknya. Kelompokkan sebagai bug valid, false positive/sudah tertangani, trade-off engineering, atau perubahan yang memerlukan keputusan bisnis/scope.
3. Perbaiki temuan valid hanya jika perubahan diizinkan tugas. Permintaan menilai review saja tetap read-only. Untuk temuan yang tidak diterapkan, jelaskan alasan dan bukti; jangan mengubah kode hanya untuk menyenangkan reviewer atau melemahkan guardrail agar komentar dianggap selesai.
4. Setelah perbaikan, jalankan verifikasi terdampak dan periksa ulang diff. Kaitkan hasil dengan commit/working diff yang diuji; jika HEAD berubah lagi, verifikasi ulang bagian yang terpengaruh sebelum menyatakan temuan selesai.
5. Laporkan status tiap temuan, perubahan atau alasan penolakan, bukti verifikasi, dan keputusan/blocker yang tersisa. Bedakan perbaikan lokal dari perubahan yang sudah dipublikasikan.
6. Membalas komentar GitHub, resolve thread, commit/push, dan merge mengikuti izin eksplisit pengguna yang berlaku; jangan menganggap salah satunya otomatis diizinkan karena review sudah ditangani. Hormati urutan publikasi yang diminta dan verifikasi hasil tindakan eksternal sebelum mengklaim selesai.

## 10. Subagent dan disiplin konteks

- Root mengerjakan tugas secara langsung sebagai default, termasuk pencarian file, pembacaan dokumen terarah, perubahan kecil, debugging terlokalisasi, dan verifikasi rutin. Jumlah file atau panjang task saja bukan alasan otomatis untuk delegasi.
- Gunakan subagent secara selektif ketika manfaat review independen atau eksplorasi kompleks yang dapat dipisahkan jelas sebanding dengan tambahan penggunaan dan koordinasi. Hindari delegasi rutin, pembacaan konteks berulang, dan pekerjaan tumpang tindih.
- Untuk perubahan substansial atau berisiko, prioritaskan satu reviewer independen dengan scope terarah bila tersedia dan pengguna tidak meminta kerja sendiri. Setelah perbaikan, review ulang hanya bagian terdampak; perluas bila ada perubahan atau bukti baru yang membenarkannya.
- Mulai dengan satu subagent sesuai kebutuhan; tambahan agent harus memiliki manfaat dan scope independen yang jelas. Paralel hanya ketika root memiliki pekerjaan berguna yang berbeda, bukan sekadar menunggu hasil delegasi.
- Subagent tidak boleh membuat subagent tambahan. Jangan memaksakan nama/model dari lingkungan lain; gunakan default pengguna kecuali ada instruksi eksplisit.
- Brief wajib memuat tujuan, batas read-only/edit, ownership file, sumber wajib, acceptance criteria, batas data/Git, dan laporan Bahasa Indonesia.
- Untuk tugas coding, sebutkan lokasi logika dengan jelas: server untuk Policy/resolver/FormRequest/Action/kalkulasi/audit; klien hanya props, rendering, dan interaksi. Ikuti Plan P.3.
- Minta digest berisi keputusan, bukti path/line, konflik, open question, command/hasil dan batas cakupan; bukan dump dokumen. Jangan mengulang eksplorasi yang telah didelegasikan tanpa alasan.
- Root tetap bertanggung jawab atas integrasi, memverifikasi bagian kritis, membaca semua file berubah, dan pemeriksaan akhir. Laporan subagent bukan bukti tunggal atau izin tambahan.
- Penggunaan tool/skill harus relevan dan mengikuti instruksinya. Jangan memperluas akses/publikasi hanya karena kemampuan tool tersedia.
- Graphify boleh menjadi navigasi awal jika tersedia dan fresh; periksa source/test untuk kesimpulan. Refresh hanya saat relevan dan diizinkan, output/cache di lokasi workspace yang ditetapkan. Tidak perlu memasangnya hanya untuk tugas sederhana.

## 11. In-Scope Rule Violation Cleanup

Pada tugas implementasi, perbaiki bug/pelanggaran yang langsung terkait perubahan atau diperlukan agar perubahan aman dan memenuhi aturan proyek. Untuk cleanup/refactor, pertahankan perilaku dan buktikan dengan test existing atau regression test relevan.

Temuan yang tidak terkait harus dilaporkan dahulu, sekalipun berada dalam file yang sama. Jika perbaikannya memerlukan perubahan business flow, scope tambahan, atau tindakan berisiko di luar izin pengguna, minta keputusan sebelum mengerjakannya. Pada tugas read-only, laporkan seluruh temuan tanpa mengubah file atau data.

