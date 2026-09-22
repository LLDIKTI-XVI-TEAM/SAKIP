# SAKIP Engineering Standards

> **Pemeliharaan dokumen:** Isi dokumen ini dapat berkembang mengikuti informasi baru atau perbaikan yang relevan; usulkan pembaruan kepada pengguna dan terapkan setelah disetujui, sesuai bagian Pemeliharaan dokumen dalam `AGENTS.md`.

Standar penulisan kode, pengujian, dan review SAKIP. Lokasi file ini dan `AGENTS.md` mengikuti pengaturan pengguna; keduanya tidak harus berada di repository atau folder yang sama. Temukan panduan melalui lokasi yang diberikan pengguna atau konfigurasi agent, lalu pencarian terbatas di workspace bila diperlukan; klarifikasi jika tidak ditemukan atau salinannya ambigu. Berlaku bersama `AGENTS.md`; instruksi yang lebih tinggi dan revisi eksplisit pengguna tetap diutamakan. Standar ini menjadi panduan kerja bersama, bukan klaim bahwa seluruh arsitektur atau quality tooling sudah diimplementasikan.

Aturan domain merujuk dokumen tim di `document/` relatif terhadap root repository aplikasi. Bila kontrak bertentangan, ikuti mekanisme konflik AGENTS; jangan menciptakan keputusan produk di standar ini. Verifikasi status environment dan kesiapan tooling melalui README, manifest/lockfile, konfigurasi runtime, dan CI aktual. Context/handoff bila disediakan membantu orientasi; tidak menjadi prasyarat dokumen ini.

Ketentuan wajib di sini adalah pilihan engineering SAKIP yang disetujui pengguna; bukan klaim bahwa framework mewajibkan pola yang sama di semua proyek. Penjelasan perilaku framework merujuk dokumentasi resmi, sedangkan opsi bertanda bersyarat diterapkan hanya jika kebutuhan, dukungan versi, dan manfaatnya terbukti. Verifikasi API terhadap manifest/lockfile dan runtime yang relevan; jangan menjadikan versi terbaru di internet sebagai target upgrade otomatis.

## Indeks penggunaan

| Bagian | Baca ketika |
| --- | --- |
| 1. Prinsip dan penulisan kode | Menulis atau mereview kode |
| 2. Arsitektur Laravel/Inertia | Menambah route/controller/Action atau mengubah kontrak halaman |
| 3. Auth dan authorization | Menyentuh login, permission, unit, PIC, akses data/berkas |
| 4. Validasi dan error | Menambah input, mutasi, filter, atau respons kegagalan |
| 5. Database dan concurrency | Mengubah skema, query, transaksi, workflow, snapshot |
| 6. Kontrak domain kinerja | Menyentuh kalkulasi, periode, target, status, buka kembali |
| 7. Bukti dukung dan audit | Menyentuh file/tautan/teks, riwayat, aksi sensitif |
| 8. Integrasi dan background work | Menyentuh OIDC, storage, queue, scheduler, notifikasi |
| 9. React, TypeScript, dan aksesibilitas | §9.1 tipe/state; §9.2 form; §9.3 navigasi/history; §9.4 aksesibilitas |
| 10. Performa | Query/payload, partial/deferred props, prefetch, polling, dan optimasi bersyarat |
| 11. Testing | Menentukan reproduksi, fixture, regression, atau cakupan akhir |
| 12. Quality gate dan review | Menyelesaikan perubahan atau menyiapkan PR |
| Referensi resmi | Memastikan perilaku dan kompatibilitas API yang dipakai |

## 1. Prinsip dan penulisan kode

- Kode harus jelas, aman, mudah dirawat, dan dapat ditelusuri ke requirement. Minimalisme tidak menghapus validasi, error handling, authorization, audit, aksesibilitas, atau test.
- Ikuti idiom Laravel, PHP bertipe jelas, dan React/TypeScript existing. Gunakan parameter/return type dan kontrak props eksplisit; bentuk data yang tidak sederhana diberi PHPDoc/type yang akurat.
- Nama class/method/variabel mengungkap maksud domain. Pertahankan istilah SAKIP dan bahasa identifier yang konsisten dengan kontrak/area existing; jangan menerjemahkan semua class secara mekanis.
- Path publik memakai istilah Bahasa Indonesia bila jelas; istilah teknis seperti login, logout, callback, health tetap wajar. Permission mengikuti katalog SAKIP `entitas:aksi`; jangan menggantinya dengan nama dotted SIMPEG.
- Tambahkan komentar/docblock Bahasa Indonesia pada public/service methods, FormRequest, Policy/resolver, jobs, dan helper yang memiliki alasan domain/non-obvious. Jelaskan tujuan, invariant, edge case, atau alasan pendekatan; tidak perlu menjelaskan getter/setter dan CRUD trivial.
- Area kritis yang perlu penjelasan: izin/data scope, kalkulasi/presisi, snapshot, transaksi/locking, lifecycle workflow, payload audit, file privat, integrasi dan retry.
- Jangan menaruh emoji, nomor issue/PR, label sprint, atau planning sementara dalam komentar source. Traceability rinci berada di PR/dokumen.
- Dilarang catch kosong, `as any`, suppression untuk melewati error, mass assignment tak terbatas, serta fallback diam-diam yang mengubah makna domain. Perbaiki tipe dan kontrak sebenarnya.
- Abstraksi mengikuti kebutuhan: DTO untuk payload kompleks lintas batas, Query Object untuk query/filter yang kompleks, adapter untuk integrasi yang perlu diisolasi. Jangan menambah lapisan generik untuk delegasi satu baris.

## 2. Arsitektur Laravel/Inertia

Kontrak produk: monolith Laravel dengan Inertia + React/TypeScript, routing/controller di server, tanpa API terpisah (PRD §6). Root Blade hanya sebagai bootstrapping Inertia bila sesuai implementasi, bukan alasan memindahkan seluruh UI ke Blade.

Alur standar perubahan backend:

```text
Route → Middleware/authentication → FormRequest/Policy → Controller
      → Action → Service/Model → props Inertia atau redirect/response sesuai kontrak
```

Authorization dan validasi yang memerlukan data terkunci harus diperiksa kembali di batas mutasi yang sesuai. Diagram bukan alasan membatasi pemeriksaan keamanan hanya sekali di awal request.

- Controller adalah adapter: menerima request/model binding, memanggil satu Action/use case, mengembalikan response. Jangan menambah query berat, transaksi, formula, audit, atau mapping OIDC langsung di controller.
- Action mengorkestrasi satu use case; Service menyimpan perilaku domain yang digunakan ulang. Action + FormRequest tetap baseline proyek, termasuk ketika contoh framework memakai controller langsung. Service/DTO/repository bukan lapisan wajib untuk setiap Action. Jangan memecah operasi atomik menjadi langkah yang dapat meninggalkan data setengah jadi.
- Route hanya mendeklarasikan URL/middleware/binding. Closure dibatasi untuk redirect/static/helper development yang sederhana, tanpa logika domain.
- Letakkan file baru berdasarkan domain: `app/Actions/{Domain}`, `app/Services/{Domain}`, `app/Http/Requests/{Domain}`, dan controller halaman per domain di `app/Http/Controllers/{Domain}`. Auth di `app/Http/Controllers/Auth`; Policy di `app/Policies`.
- Jangan membuat `Api/V1` atau lapisan REST generik tanpa kontrak API yang memang diminta. `JsonResource` digunakan jika bermanfaat bagi kontrak JSON yang nyata, bukan dipaksakan untuk semua halaman Inertia.
- Props halaman disusun eksplisit: data yang dibutuhkan, pagination/filter state, dan capability `can.*`. Jangan mengirim model/relasi sensitif mentah atau seluruh user/permission catalog ke klien. Semua props yang dikirim dapat dibaca klien meskipun tidak dirender.
- Page props memuat kebutuhan halaman; shared props hanya konteks kecil yang diperlukan lintas halaman, dengan namespace jelas agar tidak bertabrakan. Bentuk payload memakai allowlist field dan kontrak serialisasi; array eksplisit cukup bila tidak membutuhkan Resource. Props adalah snapshot respons, bukan jaminan data tetap mutakhir atau izin masih berlaku.
- Gunakan request Inertia untuk alur halaman/form. Endpoint JSON terbatas, misalnya pencarian pilihan/autocomplete, boleh digunakan dalam monolith jika diperlukan fitur yang ditugaskan; autentikasi session, CSRF untuk mutasi, authorization, scope, dan validasi tetap berlaku. Jangan otomatis memindahkannya ke `api.php`, menambah token auth, atau menduplikasi seluruh halaman dengan API. Mutasi JSON harus memiliki cara menyelaraskan kembali props/state yang terdampak.
- Ikuti nama/path existing secara konsisten, termasuk huruf besar/kecil; perubahan struktur besar harus terpisah dan diizinkan. Refactor dalam slice hanya yang dibutuhkan agar perubahan aman.
- Perubahan response/props harus memeriksa seluruh consumer dan memiliki regression test relevan. Jangan mengganti kontrak diam-diam karena pola baru lebih disukai.

## 3. Auth dan authorization

Rujukan: PRD §6–7, Data Model bagian evaluasi permission dan pemisahan tugas, Workflow §19–21, Plan Modul 1 dan P.3.

- Target autentikasi ialah Keycloak/OIDC Authorization Code Flow berbasis session melalui integrasi yang ditentukan dokumen, client khusus SAKIP. Kode callback tidak membuktikan konfigurasi provider sudah benar.
- Verifikasi state/callback, mapping identity unik, validasi token melalui library yang tepat, session regeneration, logout, serta data claim yang benar-benar tersedia. Jangan mengarang claim, realm, URL, atau client secret.
- Login/demo bypass wajib terisolasi pada development dan teruji tidak tersedia di environment lain. Jangan membawa login password existing sebagai keputusan final bila PRD menyatakan SAKIP tidak menyimpan password.
- Gunakan satu resolver izin yang konsisten untuk role, grant, deny, dan scope. Deny yang cocok menang; akses fail-closed. Superadmin tidak menjadi bypass tak terbatas atas deny, invariant bisnis, atau scope yang diwajibkan kontrak.
- Role default bukan alasan menambah allowlist permanen. Sebaliknya, jangan menghapus pengecualian/invariant bisnis yang eksplisit hanya karena aktor mempunyai permission.
- PIC bukan role baru. Satu role per pengguna dan scope grant mengikuti kontrak MVP; jangan menambahkan multi-role/switch-role dari SIMPEG.
- Setiap akses menjawab: siapa aktor, permission apa, objek/unit mana, field apa, serta apakah state/window/invariant bisnis memperbolehkan tindakan. UI hanya menerima hasil `can.*`.
- Verifikasi object ownership/unit di server dari relasi tepercaya, bukan `unit_id`, actor, role, atau status yang dikirim klien. Lindungi list, detail, mutasi, download, dan export secara konsisten.
- Pemisahan tugas jalur PIC dan pengecualian Perencanaan mengikuti sumber domain, termasuk audit `self_approval`. Jangan melarang semua self-approval atau membolehkannya semua berdasarkan nama role.
- Grant/deny, perubahan role, serta penjelasan izin harus mempertahankan provenance yang diperlukan audit. Perubahan role/PIC tidak boleh diam-diam mengubah arti jejak historis.
- Jika sumber keputusan masih bertentangan mengenai hak baca PIC, selesaikan konflik sebelum jalur terkait diimplementasikan; jangan menciptakan grant tambahan atau bypass sendiri.

## 4. Validasi dan error

- Gunakan FormRequest untuk input mutasi. Validasi query/filter, pagination, enum, route params, UUID, panjang teks, relasi, file, dan data bersarang sesuai kontrak.
- Pisahkan sintaks input, authorization, dan validasi bisnis; seluruhnya tetap di server. Periksa ulang invariant yang bisa berubah di dalam transaksi/locking yang sesuai.
- Gunakan `validated()` dan mapping field eksplisit. Field aktor, calculated value, status final, unit tepercaya, audit, dan timestamp server tidak boleh diambil dari input bebas.
- UUID-bound routes memakai constraint `whereUuid(...)` atau setara sehingga malformed UUID menghasilkan 404, bukan error PostgreSQL. Jangan memaksa UUID pada route integer existing tanpa perubahan kontrak yang diizinkan.
- Aturan `exists`/unique harus memperhatikan scope, soft-delete, tahun/periode, dan parent sesuai domain; DB constraint tetap menjadi penjaga terakhir untuk race.
- Error pengguna harus jelas dan aman. Jangan mengirim raw SQL, stack trace, token, jalur storage privat, atau pesan provider sensitif ke halaman/props.
- Domain exception boleh dipakai untuk kegagalan yang nyata dan ditangani terpusat. Validasi form melalui kunjungan Inertia mengikuti redirect dan error props/error bag, bukan handler JSON `422`. Endpoint JSON, termasuk bila memakai `useHttp` yang didukung versi proyek, mengikuti kontrak JSON/statusnya sendiri. Jangan memaksakan satu envelope ke semua respons.
- Bedakan kesalahan field, akses ditolak, session/CSRF kedaluwarsa, konflik state, kegagalan server, dan gangguan jaringan. Pertahankan input yang aman dan berikan tindak lanjut yang sesuai; jangan mengubah kegagalan menjadi pesan sukses. `onFinish` bukan bukti berhasil.
- Timeout, putus koneksi, atau pembatalan request oleh browser tidak membuktikan transaksi server dibatalkan. Sebelum retry mutasi yang hasilnya ambigu, periksa status otoritatif/identitas operasi; gunakan perlindungan idempotensi dan concurrency sesuai §5.
- Pertahankan proteksi CSRF Laravel dan transport Inertia sesuai versi yang dipakai. Jangan menonaktifkan middleware untuk mengatasi error integrasi atau menambahkan token meta statis pada integrasi Laravel/Inertia yang menggunakan cookie/header XSRF. Penanganan session expiry harus aman, tidak melakukan replay mutasi otomatis, dan diuji melalui alur browser yang relevan.

## 5. Database dan concurrency

- PostgreSQL adalah target domain. Versi runtime harus diverifikasi; jangan meniru versi SIMPEG atau menyimpulkan versi dari README yang bertentangan dengan container.
- Migration harus eksplisit tentang FK, unique/check/index, nullability, tipe numeric, dan dampak data. `down()` mengikuti kontrak integritas, bukan otomatis menghapus data penting.
- UUID, JSONB, precision/scale, partial/functional index, dan exclusion constraints mengikuti kebutuhan skema yang terverifikasi. Periksa dukungan extension seperti `btree_gist` di environment, jangan mengasumsikan aktif.
- Uji constraint nullable pada PostgreSQL; jangan menganggap unique biasa memperlakukan NULL sebagai nilai yang sama.
- Multi-step mutation dengan audit dan perubahan state berjalan atomik. Pilih row lock, optimistic concurrency, atau constraint sesuai invariant dan kontrak; jangan menggunakan global lock tanpa kebutuhan.
- Konflik stale update harus terdeteksi dan diberi respons yang dapat ditindaklanjuti; jangan last-write-wins diam-diam pada pengesahan/snapshot/akses.
- Idempotensi perlu dibuktikan di DB untuk operasi yang bisa diulang. Pola cek-lalu-insert tanpa constraint/locking bukan bukti aman dari race.
- Snapshot tidak boleh berubah karena master berubah. Verifikasi batas koreksi sebelum dirujuk dan catatan konflik; membuka kembali jadwal bukan izin umum overwrite sejarah.
- Audit dan riwayat resmi tetap append-only sesuai kontrak. Jangan menambahkan endpoint update/delete generik; soft delete hanya bila semantik domain membenarkan.
- Index dibuat untuk query/scoping yang nyata; bounded query dan eager loading diprioritaskan. Jangan membuat index spekulatif untuk semua kolom.
- Tanggal, timezone, boundary inklusif/eksklusif, dan periode harus eksplisit dari dokumen/keputusan. Jangan menyalin timezone atau formula tanggal SIMPEG tanpa dasar SAKIP.
- Sebelum operasi destruktif, pastikan DB disposable yang benar. Database testing dan server browser/E2E sama-sama harus terisolasi; jangan memakai nama database saja sebagai bukti.

## 6. Kontrak domain kinerja

Bagian ini adalah guardrail pembacaan, bukan pengganti rumus dan transisi lengkap dalam PRD/Data Model/Workflow.

- Telusuri rantai regulasi → Renstra/sasaran/indikator → target tahunan/PK → jadwal/periode → rencana aksi/kegiatan → pengukuran → pelaporan. Jangan menukar target tahunan PK dengan target komponen per periode.
- Mesin kalkulasi mengikuti tipe dan definisi komponen dalam dokumen; jangan hardcode angka/formula per IKU dari contoh workbook. Workbook referensi bukan kontrak impor massal.
- Nilai indikator dihitung di server. React menampilkan hasil, bukan menjadi sumber perhitungan resmi; calculated value dari payload tidak dipercaya (Plan P.3).
- `0`, `null`, belum diisi, tidak berlaku, dan nilai pembagi nol harus dibedakan sesuai kontrak. Jangan memakai fallback `0` untuk menyembunyikan hasil yang tidak tersedia.
- Precision, pembulatan, arah penilaian, baseline, dan agregasi harus mengikuti spesifikasi; pisahkan nilai sumber, hasil perhitungan, dan formatting tampilan. Tambahkan boundary test sebelum implementasi kalkulasi.
- Klaim kegiatan bukan otomatis kontribusi numerik ke komponen. Status alur, status capaian manual, dan persentase numerik adalah konsep berbeda.
- Transisi Draft/Diajukan/Diverifikasi/Disahkan/Dikembalikan dan jalur buka kembali diperiksa dari state machine untuk entitas terkait; jangan membangun transisi generik hanya dari kesamaan label.
- Jendela PIC, hak Perencanaan, penutupan tahunan, kelengkapan rencana aksi/komponen/bukti, serta pengecualian retroaktif memiliki cakupan tersendiri. Jangan menerapkan satu gate global yang menghilangkan pengecualian resmi.
- Dashboard/laporan resmi mengikuti kriteria data yang boleh dihitung, termasuk status pengesahan. Nilai draft tidak boleh masuk angka resmi hanya karena tersedia.
- Schema yang disiapkan dokumen untuk fase lanjutan tidak berarti UI/alurnya boleh diaktifkan. Sebaliknya, jangan menghapus kolom yang eksplisit diwajibkan dokumen hanya demi prinsip anti-overengineering.
- Konflik snapshot, target manual, atau window yang belum diputuskan harus menjadi pertanyaan sebelum implementasi jalur tersebut.

## 7. Bukti dukung dan audit

- Ikuti tiga mode bukti file/tautan/teks dan hubungan induk yang ditetapkan dokumen. Validasi stage, jenis persyaratan, ukuran, MIME/extension, dan status induk di server.
- Jangan memperketat pengecualian `tidak_dapat_dipenuhi` atau backfill dengan meniadakannya. Verifikasi alasan, scope, dan audit yang diwajibkan sumber.
- File privat memakai storage privat, nama tersimpan yang aman, dan route download/stream dengan authorization objek. Path dari klien tidak boleh menjadi filesystem path bebas.
- Periksa risiko traversal, tipe file berbahaya, dan akses lintas unit/induk. Jangan membuat symlink publik untuk bukti privat. Scan malware digunakan bila infra tersedia dan scope mengharuskan, bukan klaim fasilitas sudah aktif.
- Tautan eksternal divalidasi sesuai kontrak. Jangan fetch URL pengguna di server tanpa kebutuhan dan proteksi SSRF yang relevan.
- Konsistensi file dan DB perlu penanganan kegagalan; transaksi SQL tidak otomatis mengembalikan upload/delete storage. Jangan menghapus file existing sebagai kompensasi spekulatif.
- Audit mencatat aktor/target/aksi/waktu/perubahan/alasan serta `dasar_izin` untuk aksi sensitif sesuai kontrak. Aktor dan dasar izin ditentukan server, bukan payload audit dari klien.
- Audit penolakan yang diwajibkan harus tetap tercatat tanpa menyimpan mutasi domain yang ditolak. Uji bahwa rollback tidak tanpa sengaja menghilangkan bukti penolakan atau meninggalkan mutation parsial.
- Pilih field audit secara eksplisit. Jangan mencatat credential, cookie, token, isi file, atau seluruh request mentah. Masking/retention baru memerlukan kontrak; jangan mengarang masa retensi.
- Akses audit, ekspor, dan download harus diuji scope/privacynya, bukan hanya tombol terlihat.

## 8. Integrasi dan background work

- Isolasi detail provider di service/adapter yang diperlukan. Jangan taruh token dan kontrak provider di controller, frontend bundle, fixture, atau log publik.
- Queue/driver/scheduler/channel/penerima bukan asumsi dari SIMPEG. Periksa dokumen, config, dan environment; konflik notifikasi eksternal yang belum diputuskan harus diklarifikasi sebelum jalur terdampak diimplementasikan.
- Jobs harus memiliki retry/idempotensi yang sesuai efek samping. Side effect eksternal setelah commit bila diperlukan; jangan mengirim berdasarkan transaksi yang kemudian rollback.
- Respons accepted/queued dari provider bukan bukti delivery. Jika hasil pengiriman ambigu, cek status berdasarkan identitas operasi sebelum mencoba ulang.
- Jangan mengirim email/WhatsApp nyata atau menyalakan scheduler saat pengujian tanpa izin dan batas penerima yang jelas. Gunakan fake/sink lokal untuk test yang tidak membutuhkan provider nyata.
- Bedakan error pengguna dari detail operasional. Simpan detail sensitif di log terbatas dengan identitas korelasi yang aman, bukan notifikasi mentah.

## 9. React, TypeScript, dan aksesibilitas

Rujukan UI: `document/design-system.md`. Pakai token, komponen, font, dan pola layout yang ditetapkan; jangan mengganti identitas visual berdasarkan selera agent. Sediakan loading, empty, error, success, pending, dan validation state sesuai interaksi.

### 9.1 Kontrak tipe, komponen, dan state

- Halaman mengomposisi data/interaksi fitur, layout mengelola kerangka halaman, dan komponen reusable memiliki tanggung jawab jelas. Custom hook dibuat untuk perilaku yang memang perlu dipisahkan/digunakan ulang; jangan membuat pembungkus generik hanya untuk menyembunyikan satu pemanggilan Inertia.
- Halaman, komponen, layout, shared props, dan form memiliki tipe eksplisit. Tipe mengikuti JSON aktual: bedakan field absen dari `null`, tanggal terserialisasi dari objek `Date`, serta string desimal dari number sesuai kontrak backend. Type assertion bukan validasi runtime; jangan memakai assertion/suppression untuk menyembunyikan mismatch.
- Pisahkan props server, data form, state UI lokal, dan nilai turunan tampilan. Hitung nilai turunan UI sederhana saat render; jangan menyimpan salinannya dalam state dan menyinkronkannya lewat effect. Permission dan rumus indikator tetap di server sesuai Plan P.3, termasuk bila hasil ingin ditampilkan sebagai preview.
- Gunakan `useEffect` untuk sinkronisasi dengan sistem eksternal, dengan dependency dan cleanup yang benar. Jangan refetch data halaman yang sudah menjadi props hanya karena mount, atau menimpa draft form pada setiap perubahan props. Ketika objek/periode berubah, tentukan reset/rehidrasi form secara sengaja agar draft tidak bocor ke record lain.
- Jangan memutasi props/state langsung. State lokal cukup untuk modal, tab, dan pilihan sementara; state manager global atau data-fetching library tambahan bersyarat pada kebutuhan nyata yang belum dipenuhi React/Inertia.
- Error boundary menangani kegagalan rendering sesuai batas komponen; error event handler dan request tetap ditangani pada jalurnya. Jangan menghilangkan Strict Mode atau menyembunyikan error untuk melewati masalah lifecycle.

### 9.2 Form, mutasi, dan unggahan

- `useForm` adalah default SAKIP sesuai design system. Gunakan `setData`/mekanisme resmi dan transformasi payload yang sesuai, bukan assignment langsung seperti `data.action = ...`. Untuk form dengan beberapa intent, intent yang dikirim harus benar pada klik maupun submit keyboard; jangan mengandalkan state baru langsung tersedia sesudah setter.
- Ikat error server ke field yang benar, termasuk input bersarang dan beberapa form pada halaman yang sama. Tampilkan pending/validation/success/error state yang sesuai. Tombol non-submit harus mempunyai tipe yang benar; cegah double-submit di UI, dengan perlindungan transaksi/idempotensi server tetap mengikuti §5.
- Gunakan `onSuccess` untuk tindakan yang mensyaratkan keberhasilan, seperti menutup modal atau membersihkan form. `onFinish` hanya untuk penyelesaian lifecycle/cleanup yang juga aman saat gagal. Jangan menghapus input/error atau menutup modal koreksi hanya karena request selesai. Jika kegagalan domain/session ditangani melalui redirect + flash, periksa outcome server sebelum reset atau menutup form; callback `onSuccess` tanpa validation errors sendiri bukan bukti mutasi tersimpan.
- Bedakan reset data, reset error, default nilai, dan dirty state. Form create boleh dikosongkan setelah sukses bila sesuai alur; form edit menetapkan baseline baru dari nilai tersimpan yang otoritatif. Reset tidak boleh mengembalikan nilai lama atau menghilangkan koreksi pengguna secara tak sengaja.
- Inertia mendukung `FormData` untuk upload. Gunakan POST dengan method spoofing bila dibutuhkan untuk upload PUT/PATCH pada stack Laravel; kontrak route tetap harus cocok. Jangan memasukkan berkas sebagai base64 ke props atau history. Progress upload selesai bukan bukti berkas tervalidasi dan transaksi tersimpan.
- Untuk transisi resmi seperti pengajuan, verifikasi, pengesahan, koreksi snapshot, atau perubahan akses, tampilkan hasil setelah konfirmasi server. Optimistic update hanya bersyarat untuk interaksi berisiko rendah yang dapat dipulihkan, dengan rekonsiliasi kegagalan; tidak menggantikan otorisasi atau integritas server.

### 9.3 Navigasi, history, dan kesegaran data

- Navigasi halaman internal memakai Inertia `Link`; mutasi memakai form/button atau Link yang dirender sebagai button. GET tidak mengubah state bisnis. Download file dan redirect eksternal mengikuti jenis responsnya. Hindari elemen interaktif bersarang seperti button di dalam link.
- Simpan filter/sort/page yang harus dapat dibuka ulang atau dibagikan pada query URL. Server memvalidasi parameter dan allowlist kolom sort; reset pagination saat filter berubah. Debounce pencarian bila perlu dan gunakan `replace` secara selektif agar history tidak berisi setiap ketikan. Respons request lama tidak boleh menimpa hasil pencarian baru; batalkan/abaikan hasil yang tidak relevan.
- `preserveState` mempertahankan state komponen pada kunjungan terkait; `preserveScroll` menangani posisi scroll; `useRemember` menyimpan state lokal dalam history; persistent layout mempertahankan instance layout. Pilih sesuai kebutuhan, bukan mengaktifkan semuanya secara global. Key state yang diingat harus membedakan halaman/form/record yang relevan.
- Jangan menyimpan credential, token, atau file melalui remembered state. Draft sensitif hanya boleh diingat bila kebutuhan dan perlindungannya jelas. Review data yang masuk props/history maupun storage browser; layout yang persisten bukan penyimpanan otoritatif data pengguna/izin.
- Bedakan history Inertia, cache prefetch, HTTP cache, dan bfcache browser. Ketika menyentuh login/logout, identitas, scope akses, atau data sensitif, tentukan dan uji invalidation/pembersihan yang diperlukan, termasuk Back/Forward. History encryption/clear history adalah mekanisme terpisah, bukan pengganti authorization, CSRF, proteksi XSS, atau kebijakan HTTP cache.
- Jangan menganggap `Cache-Control: no-store` selalu menonaktifkan bfcache di semua browser. Periksa perilaku browser target; Chrome mengizinkannya dalam kondisi tertentu. Jangan mengandalkan header itu saja untuk membuktikan halaman sensitif tidak muncul kembali.
- Pesan sukses sekali tampil tidak boleh diperlakukan sebagai status domain. Shared `props.flash` dan fasilitas dedicated flash Inertia mempunyai perilaku history berbeda; gunakan kontrak yang didukung adapter dan uji agar navigasi kembali tidak mengulang pesan seolah mutasi baru berhasil.

### 9.4 Aksesibilitas dan komponen UI

- Komponen/form memiliki label dengan ID unik, nama aksesibel, keyboard navigation, focus terlihat, serta error/helper yang terhubung ke input melalui atribut yang sesuai (`aria-invalid`, `aria-describedby`). Arahkan focus ke error atau ringkasannya secara masuk akal; pesan pending/sukses penting dapat diumumkan tanpa mengganggu.
- Modal mengelola focus masuk/keluar dan dismissal sesuai kebutuhan; interaksi keyboard harus tetap dapat menyelesaikan alur. Status tidak dibedakan dengan warna saja. Pastikan kontras sesuai design system, tabel/headings semantik, dan angka/unit/periode terbaca.
- Periksa desktop/mobile yang relevan dengan DoD, overflow, sidebar, tabel panjang, modal, tombol sentuh, dan navigasi setelah mutasi.
- Gunakan React Testing Library bila sudah disiapkan untuk test perilaku komponen. Test harus membuktikan interaksi pengguna/kontrak, bukan snapshot DOM yang luas tanpa invariant.
- Jangan menambah dependensi UI/chart yang belum diperlukan hanya karena tercantum sebagai target stack. Wiring tooling/dependency dilakukan dalam task yang sesuai.

## 10. Performa

- Data yang bisa tumbuh memakai pagination dan filter/sort database. Larang `Model::all()`/unbounded `get()` untuk tabel, audit, bukti, dan pengukuran yang terus bertambah tanpa alasan terukur. Pilih pagination total jika UI memerlukan total, simple pagination bila cukup previous/next, dan cursor hanya jika pola akses serta urutan deterministik dengan pembeda unik mendukungnya.
- Eager load secara eksplisit; hindari N+1, query saat rendering/helper berulang, serta agregasi seluruh dataset di browser.
- Batasi shared props Inertia sesuai §2. Sebelum mengoptimasi, ukur query, ukuran payload, dan waktu interaksi; `only` pada request tidak otomatis menghemat query yang sudah dieksekusi sebelum seleksi props.
- Bedakan evaluasi closure (ditunda sampai prop diperlukan), `optional` (hanya dikirim saat diminta secara eksplisit melalui partial reload), dan deferred props (diambil dalam request terpisah sesudah render awal). Pilih sesuai kebutuhan/dukungan versi; jangan mengubah semua props menjadi deferred.
- Partial reload berlaku untuk komponen halaman yang sama dan menggabungkan props baru dengan props lama. Reload bersama data yang saling bergantung, misalnya daftar, filter aktif, ringkasan, dan capability yang terdampak; jika sulit menjaga konsistensi, gunakan reload penuh yang sesuai. Setiap request tetap menjalankan authorization/data scope.
- Deferred props hanya untuk data sekunder yang boleh terlambat, dengan loading/error/retry yang jelas. Pengelompokan request harus mempertimbangkan biaya query dan konsistensi. Data yang menentukan akses atau keamanan mutasi tidak boleh bergantung pada keberhasilan render placeholder.
- Prefetch/once props bersyarat: data harus aman dipakai ulang, dengan masa berlaku dan invalidation yang sesuai saat mutasi, identitas, scope, atau periode berubah. Jangan memakai cache itu untuk mengasumsikan permission atau status resmi selalu mutakhir.
- Dashboard/chart memakai agregasi yang memenuhi scope/state resmi. Jangan cache lintas aktor/unit tanpa cache key dan invalidation yang benar.
- Polling harus memiliki alasan produk, interval, payload minimum, penghentian ketika tidak terautentikasi/komponen dilepas, dan pengendalian request bersamaan. Throttling tab background tidak sama dengan berhenti; periksa lifecycle helper yang dipakai. Jangan memperpanjang sesi idle lewat polling secara tidak sengaja.
- Hindari font ganda, aset dekoratif besar, dan bundle/dependency berat tanpa kebutuhan. Ukur bottleneck sebelum menambahkan memoization/cache/infrastruktur. Code splitting dipilih menurut ukuran/frekuensi halaman dan biaya request tambahan; SSR, React Compiler, memoization luas, dan persistent layout global bukan kewajiban default. Versi React saja tidak membuktikan Compiler dikonfigurasi.
- Catatan browser smoke pada perubahan halaman mencakup route, viewport, interaksi utama, error console/network, dan polling/lag yang diamati. Bila lambat, ukur timing/query/payload yang relevan dan pisahkan overhead lokal dari regresi aplikasi.
- Angka performance budget SIMPEG bukan SLA SAKIP. Target formal mengikuti NFR SAKIP atau keputusan terverifikasi; hasil pengukuran menyebut environment dan baseline.

## 11. Testing

- Ikuti TDD berbasis risiko dalam AGENTS. Test reproduksi harus gagal karena masalah yang dimaksud, bukan karena database/dependency salah.
- Gunakan fixture/factory sintetis yang deterministik. Jangan bergantung diam-diam pada seed development, akun riil, tanggal hari ini, urutan test, atau data yang sudah ada.
- Untuk fitur kritis, cakup happy path, input gagal, permission denied, scope leakage, malformed identifier, state/window invalid, transaksi/audit, dan retry/concurrency sesuai perubahan.
- Authorization perlu skenario role/grant/deny yang relevan, objek di unit lain, serta endpoint detail/download/export jika terdampak. Jangan hanya membuktikan menu tersembunyi.
- Kalkulasi perlu boundary nilai, `0`/`null`, pembagi nol, bobot/presisi dan formula yang benar-benar digunakan. Uji kode kalkulasi aplikasi pada backend di environment testing disposable dan bandingkan dengan expected value dari kontrak/contoh terverifikasi; fungsi rumus yang hanya didefinisikan di file test tidak membuktikan kalkulasi aplikasi. Test frontend memeriksa input dan hasil server yang ditampilkan, bukan membuat mesin indikator kedua.
- Workflow perlu state awal/target, aktor, prasyarat, stale request, side effect dan rollback. Test sequential tidak boleh diklaim sebagai bukti race dua koneksi.
- DB-sensitive tests memakai PostgreSQL disposable dan schema aktual. Verifikasi runner/server/cache/worker database sebelum migration/reset; jangan menjalankan lane destruktif bersamaan dengan suite lain.
- Untuk frontend, typecheck, test komponen/perhitungan tampilan yang relevan, build produksi, dan browser smoke/E2E sesuai flow tetap terpisah. Build Vite saja bukan bukti typecheck atau aksesibilitas; gunakan command typecheck yang cocok dengan toolchain aktual tanpa memasang tool baru hanya untuk melewati gate.
- Backend test Inertia memeriksa komponen halaman, props/tipe/nilai penting, pagination/filter, capability, dan ketiadaan field sensitif pada aktor berbeda; gunakan assertion adapter yang didukung versi. Uji redirect/error props untuk form, serta kontrak JSON secara terpisah. Hindari snapshot seluruh payload yang tidak melindungi invariant tertentu.
- Test komponen membuktikan perilaku pengguna: pengisian/submit keyboard, intent tombol, pending, binding error, serta data/modal tetap tersedia saat gagal. E2E dipilih untuk batas integrasi yang berisiko, misalnya upload, session expiry, navigasi Back/Forward setelah logout, dan stale state saat mutasi.
- Middleware CSRF Laravel dinonaktifkan otomatis pada test biasa; feature test lulus bukan bukti alur CSRF browser. Jika area session/CSRF berubah, verifikasi melalui pengujian yang benar-benar mengaktifkan proteksi tersebut. Tidak perlu menduplikasi semua skenario backend dalam E2E.
- Sebelum menambah test, identifikasi kontrak atau risiko yang dilindungi dan gap pada coverage existing. Untuk refactor yang sudah tercakup, jalankan test existing; tambah regresi hanya bila ada gap. Tidak perlu menambah test hanya karena ada file baru atau perubahan kode.
- Pilih lapisan paling ringan yang cukup membuktikan perilaku: unit test untuk logika murni, integration/feature test untuk database, authorization, dan workflow, test komponen untuk interaksi UI, serta browser/E2E untuk batas integrasi yang memang memerlukan browser. Jangan mengganti bukti database/authorization dengan mock yang melewati kontrak yang diuji.
- Hindari mengulang skenario yang sama di banyak lapisan tanpa risiko atau kontrak berbeda yang dilindungi. Jangan membuat test getter/setter trivial, detail implementasi, atau perilaku bawaan framework tanpa kebutuhan khusus proyek. Parameterized test boleh merangkum kasus berbeda; hindari kombinasi spekulatif yang tidak menambah perlindungan.
- Fixture hanya memuat data dan relasi yang diperlukan skenario. Saat test lambat, ukur bagian yang mahal dan periksa setup, query, bootstrap aplikasi, serta reset database sebelum menambah suite atau infrastruktur baru. Optimasi harus mempertahankan isolasi dan determinisme; jangan memakai database development atau state bersama yang membuat test bergantung urutan.
- Selama pengerjaan, jalankan focused tests yang relevan. Jalankan full quality gate yang diwajibkan pada tahap final sesuai scope, bukan setelah setiap edit; ulangi hanya pemeriksaan terdampak bila ada perubahan atau failure yang membenarkannya. Jangan menghapus/melemahkan assertion atau melewatkan coverage kritis demi memangkas durasi; jumlah test maupun target coverage numerik bukan tujuan tersendiri.
- Test source tidak membuktikan test pernah dijalankan. Catat command, SHA/diff, environment/database, jumlah hasil yang benar, skips, dan keterbatasan.

## 12. Quality gate dan review

### Standar yang diadopsi dan kesiapan tooling

Baseline wajib untuk perubahan backend yang dinyatakan PR-ready adalah Pint seluruh proyek, static analysis PHP minimum level 3, dan suite backend sesuai risiko/CI. Perubahan frontend memerlukan typecheck TypeScript, test frontend relevan, build produksi, serta browser smoke untuk halaman/alur yang berubah. Perubahan lintas lapisan memenuhi keduanya. Ini penetapan standar kerja, bukan klaim bahwa seluruh command/dependency sudah tersedia; pengecualian eksplisit pengguna dan gate yang belum siap harus dilaporkan.

Periksa `README.md`, `composer.json`, `composer.lock`, `package.json`, lockfile frontend, `phpunit.xml`, konfigurasi container, dan workflow CI yang benar-benar ada. Manifest yang berbeda dari keputusan toolchain proyek yang telah disahkan bukan izin migrasi otomatis; laporkan gap dan pisahkan penyiapan tooling sebagai pekerjaan yang diizinkan.

- `composer qa` hanya dijalankan jika script ada dan target/efek sampingnya telah diperiksa. Ketiadaan script tidak membebaskan kewajiban verifikasi; identifikasi command penyusun yang tersedia dan gate yang belum siap.
- PHP/container: gunakan runtime proyek yang telah diverifikasi sesuai versi dependency. Jika memakai Podman, periksa container/mount/cwd/environment target; nama/container SIMPEG tidak berlaku. Jangan menetapkan bahwa PHP host atau Podman sudah siap tanpa bukti.
- Frontend mengikuti Bun menurut dokumen; pengecualian tooling harus eksplisit. Jangan menghapus lockfile npm atau memasang ulang dependency dalam tugas yang tidak meminta peralihan toolchain.
- Focused tests digunakan selama implementasi. Untuk perubahan backend substansial/final PR-ready jalankan suite/gate proyek yang berlaku; jika pengguna meminta focused-only, catat pengecualian dan gate yang tidak dijalankan.
- Dokumentasi saja: periksa isi, referensi/path, konsistensi, diff, dan scope; tidak perlu menjalankan runtime/test aplikasi atau menginstal tool QA.
- Kegagalan pre-existing dipisahkan dari kegagalan perubahan. Gate yang belum tersedia/dijalankan dilaporkan sebagai keterbatasan; jangan dilabeli PASS atau PR-ready penuh.
- Periksa status CI live bila tugas membutuhkan publikasi/PR-ready; workflow yang sengaja nonaktif tidak boleh diaktifkan sendiri. Tidak ada workflow lokal bukan bukti keadaan remote.

### Pemeriksaan akhir

Review mencakup correctness, security, authorization/data scope, audit/privacy, validasi, struktur, performa, traceability, aksesibilitas dan kualitas test sesuai risiko. Verifikasi temuan reviewer pada exact head dan caller; jangan memperbaiki false positive secara mekanis.

Tinjau perubahan untuk duplikasi yang tidak diperlukan, alur kontrol berbelit, tanggung jawab yang bercampur, serta pekerjaan/query berulang. Gunakan skill `simplify` bila relevan dan tersedia. Perbaiki hanya masalah konkret dalam scope, pertahankan perilaku, dan verifikasi perubahan; jangan memaksakan refactor atau abstraksi hanya untuk memendekkan kode.

Sebelum menyatakan selesai: baca semua file berubah; periksa diff dan perubahan pengguna; selesaikan temuan valid dalam scope; jalankan ulang gate terdampak setelah fix; laporkan hasil serta batas verifikasinya. Publikasi, merge, dan perubahan context/planning tetap mengikuti izin AGENTS.

## Referensi resmi

Rujukan perilaku framework, bukan sumber requirement SAKIP atau izin upgrade. Gunakan versi dokumentasi yang cocok dengan dependency proyek; contoh resmi tetap disesuaikan dengan Action/FormRequest, Plan P.3, dan design system. Daftar ini tidak menetapkan versi patch terbaru atau tanggal rilis sebagai aturan.

- Arsitektur/props: [shared data](https://inertiajs.com/docs/v3/data-props/shared-data), [TypeScript](https://inertiajs.com/docs/v3/advanced/typescript).
- Form/error/upload: [forms](https://inertiajs.com/docs/v3/the-basics/forms), [validation](https://inertiajs.com/docs/v3/the-basics/validation), [HTTP requests](https://inertiajs.com/docs/v3/the-basics/http-requests), [file uploads](https://inertiajs.com/docs/v3/the-basics/file-uploads), [optimistic updates](https://inertiajs.com/docs/v3/the-basics/optimistic-updates).
- State/history: [remembering state](https://inertiajs.com/docs/v3/data-props/remembering-state), [layouts](https://inertiajs.com/docs/v3/the-basics/layouts), [flash data](https://inertiajs.com/docs/v3/data-props/flash-data), [history encryption](https://inertiajs.com/docs/v3/security/history-encryption), [Chrome bfcache dan no-store](https://developer.chrome.com/docs/web-platform/bfcache-ccns).
- CSRF: [integrasi Laravel/Inertia](https://inertiajs.com/docs/v3/security/csrf-protection), [Laravel request forgery protection](https://laravel.com/framework/docs/13.x/csrf).
- Performa: [Laravel pagination](https://laravel.com/framework/docs/13.x/pagination), [partial reloads](https://inertiajs.com/docs/v3/data-props/partial-reloads), [deferred props](https://inertiajs.com/docs/v3/data-props/deferred-props), [once props](https://inertiajs.com/docs/v3/data-props/once-props), [prefetching](https://inertiajs.com/docs/v3/data-props/prefetching), [polling](https://inertiajs.com/docs/v3/data-props/polling), [code splitting](https://inertiajs.com/docs/v3/advanced/code-splitting).
- React: [state structure](https://react.dev/learn/choosing-the-state-structure), [effects](https://react.dev/learn/you-might-not-need-an-effect), [immutable state](https://react.dev/learn/updating-objects-in-state), [input dan label](https://react.dev/reference/react-dom/components/input).
- Verifikasi: [Inertia testing](https://inertiajs.com/docs/v3/advanced/testing), [Vite dan TypeScript](https://vite.dev/guide/features.html#typescript), [Testing Library principles](https://testing-library.com/docs/guiding-principles/).
