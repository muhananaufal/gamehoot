# AGENTS.md — Pentahoot

Aturan kerja untuk siapa pun yang menyentuh repo ini: developer, reviewer, atau AI agent di sesi mana pun.
Baca berkas ini utuh sebelum menulis kode.

## 1. Sumber kebenaran

| Apa | Di mana |
| :-- | :-- |
| Semua keputusan produk dan teknis, lengkap dengan ID (A, B, C, D, E, F, G, K, O, P, T, W) | `docs/pentahoot-spec.html` (versi terbit: https://claude.ai/artifact/YApdMqJDKY2XEpsdLAj34A) |
| Mockup UI tiga layar (HP, Public View, panel host) | https://claude.ai/artifact/QwAUn3xFXjzXWWFmxqhjL7 |
| Kebutuhan asli dari PM | "PRD — Pentahoot Game Platform" (PDF, dipegang PM) |

Aturan:

- **Spesifikasi menang atas ingatan.** Kalau ragu, buka `docs/pentahoot-spec.html` dan cari ID-nya. Jangan menebak aturan main dari PRD saja; spesifikasi sudah mengubah beberapa bagian PRD (bagian 10 "Yang berbeda dari PRD").
- **Ubah dokumen dulu, baru kode (W8).** Perubahan keputusan dicatat di spesifikasi dengan ID baru atau ID yang diperbarui, disetujui pemilik proyek, baru kodenya diubah. Kode yang menyimpang dari spesifikasi dianggap bug.
- **Rujuk ID** di pesan commit body dan komentar kode yang menerapkan aturan tidak jelas, misalnya `// E1: competition ranking (1-2-2-4)`.

## 2. Keputusan yang dikunci — jangan diusulkan ulang

- **Laravel 13 + PHP 8.4, dijalankan di Docker** di lokal, CI, staging, dan production: container `app` (php-fpm), `web` (nginx), `reverb` (A8). Pola compose mengikuti staging `vivace-board`.
- **Database: server MySQL yang sudah ada** (bukan container), dengan database dan user khusus Pentahoot (A9). Sampai versi server diketahui (O-B), hanya pakai fitur MySQL 8.0 ke atas; CI menguji MySQL 8.0 dan 8.4.
- **Laravel Reverb** satu server, **tanpa Redis**, **tanpa queue worker** (F5, F7).
- **Blade + Alpine.js + Laravel Echo + Tailwind** di semua layar, **tanpa Livewire**.
- **Pest v4** (+ plugin browser) untuk test, **Vitest** untuk logika JS murni, **Pint** untuk format, **Larastan** untuk analisis statis (W11).
- Server production: **VM lewat SSH** dengan disk permanen (A7), menjalankan Docker.
- **Bahasa antarmuka: Inggris** (A10). Isi yang ditulis host bebas bahasa.

## 3. Arsitektur yang wajib diikuti

- **Engine per jenis game (F13).** Semua logika game (aksi per state, snapshot, hasil, validasi soal, penyalinan dari paket) ada di class engine per `games.type`. Controller tidak boleh bercabang per jenis game.
- **Controller tipis.** Validasi di FormRequest, hak akses di Policy (pemilik, co-host, super-admin: C-2, C-4), logika bisnis di Action/Service.
- **Server satu-satunya sumber kebenaran (F1).** Setiap broadcast membawa snapshot utuh + `state_version` (F15). Klien membuang snapshot yang versinya lebih kecil.
- **Broadcast pakai `ShouldBroadcastNow`** dan dijaga jauh di bawah batas 10.000 byte Reverb (F14). Per vote hanya `{answered: n}`; rincian suara lewat `GET /host/{event}/state`.
- **Aksi peserta lewat HTTP POST**, bukan pesan WebSocket (F3). Rate limit per token klaim, bukan per IP (F11).
- **Aksi host** dijalankan dalam transaksi dengan `lockForUpdate` dan update bersyarat terhadap state (F6, G12).
- **Waktu dari server.** `ends_at` + `server_now`, tanpa job terjadwal (F4). Suara diterima sampai `ends_at` + 1 detik (E3).
- **Semua file lewat disk `media`** dan tabel `media_files` (F16, G11). Gambar jawaban privat, hanya lewat temporary signed URL saat Reveal (E9).
- **Channel realtime memakai UUID event**, bukan slug (F18).
- **Frontend (F20):** halaman host memakai satu layout (sidebar kiri + top bar breadcrumb dan aksi), tanpa menu di top bar; satu Alpine store bersama menerima snapshot, mengecek `state_version`, dan mengatur reconnect; view hanya membaca store. Elemen berulang (baris peringkat, podium, kotak huruf, countdown, pencarian nama, timeline progres) berupa komponen Blade. Timeline progres selalu vertikal dengan satu komponen. Semua teks UI (Bahasa Inggris) ada di berkas bahasa, tidak ditulis langsung di view.
- **Tema (F21):** warna hanya lewat token semantik Tailwind (`bg-surface`, `text-ink`, `bg-rank-1`, ...). Tema gelap mengganti nilai token di `[data-theme=dark]`. **Dilarang** hex arbitrer (`bg-[#...]`) dan palet bawaan (`bg-gray-900`) di view; dicek di CI.
- **Podium (E17)** hanya untuk hasil final; selama permainan tetap daftar peringkat.

## 4. Aturan database

- UUIDv7 (`HasUuids`) untuk tabel yang ID-nya terlihat dari luar; bigint untuk `votes`, `action_logs`, `question_results`, `game_results` (G6). Foreign key mengikuti tipe tabel yang dirujuk.
- Konten (`question_packs`, `pack_questions`, `pack_question_*`) terpisah dari sesi (`games`, `questions`, `question_*`). Soal paket **disalin** ke game (D-9, G9).
- Satu penanda untuk satu fakta: `events.active_game_id`, `games.current_question_id` (G7).
- Hasil **dibekukan** ke `question_results` saat Reveal dan `game_results` saat game selesai. Halaman Hasil dan export hanya membaca tabel beku (G10).
- Jaminan di level database: unique `(question_id, voter_person_id)`, unique `(event_id, name_normalized)`, FK `restrict` untuk data yang dipakai hasil (G2, G13).
- Hanya `events` dan `question_packs` yang memakai soft delete (G4, D-9).

## 5. Alur git (W1–W7)

```
main       production. Hanya dari release/* dan hotfix/*, setiap merge diberi tag.
develop    staging. Tempat integrasi.
feature/*  fix/*  refactor/*   dari develop, kembali ke develop, lalu dihapus.
release/x.y.z                  dari develop, ke main + tag, lalu back-merge ke develop.
hotfix/*                       dari main, ke main + develop.
```

Siklus satu tugas:

1. `git checkout develop && git checkout -b feature/<nama-kebab-case>`
2. Tulis test dulu → jalankan → **saksikan gagal** → implementasi → saksikan lulus.
3. Gerbang kualitas (bagian 6) harus lulus.
4. Commit atomik, satu commit per alasan perubahan, Conventional Commits Bahasa Inggris:

   ```
   feat(db): add events and people migrations

   Implements G6 id types and G2 unique constraints.

   Co-authored-by: aetheris <agents.aetheris@gmail.com>
   ```

   Trailer `aetheris` **wajib**. Trailer AI lain boleh ditambahkan di bawahnya, tidak menggantikan.
5. Isi `CHANGELOG.md` dan penuhi Definition of Done (bagian 6).
6. `git fetch origin && git rebase origin/develop` → `git push --force-with-lease origin feature/<nama>` (force hanya di branch fitur sendiri, setelah rebase).
7. **Tunggu CI hijau untuk commit terakhir branch itu (W18)**, misalnya dengan `gh run watch`. Merah → perbaiki di branch yang sama → push → tunggu hijau lagi. **Dilarang merge selama CI belum hijau.**
8. `git checkout develop && git merge --no-ff feature/<nama>` → `git push origin develop` → `git branch -d feature/<nama>` → `git push origin --delete feature/<nama>`.

Aturan tetap:

- **Tanpa pull request** untuk merge ke `develop` (W2), **tapi CI wajib hijau sebelum merge** (W18). Pengganti review: berhenti untuk review pemilik proyek setelah batch fondasi dan setelah setiap game selesai.
- `release/*` dan `hotfix/*` juga wajib CI hijau sebelum merge ke `main`. Job deploy hanya jalan kalau job CI di push yang sama hijau.
- Sebelum remote GitHub ada: jalankan langkah CI yang sama secara lokal di container yang sama, dan laporkan hasilnya sebagai pengganti sementara.
- **Merge ke `main` selalu ditanyakan dulu** ke pemilik proyek, setiap rilis.
- Dilarang commit langsung di `main`/`develop`, dilarang commit `wip`/`temp`, dilarang `git push --force` di `main`, `develop`, `release/*`.
- Remote GitHub (`devivace-groups`) menyusul. Saat dibuat, cek branch protection (W7).

Versi (W9), SemVer `MAJOR.MINOR.PATCH`:

- **PATCH**: perbaikan bug dan perubahan kecil. Boleh terus naik (`0.3.9` → `0.3.10`).
- **MINOR**: fitur baru. Sebelum rilis, minor hanya naik saat satu tahap selesai: `v0.1.0` fondasi, `v0.2.0` realtime, `v0.3.0` Pentahoot, `v0.4.0` Tebak Kata, `v0.5.0` Tebak Gambar. PATCH kembali ke 0.
- **MAJOR**: perubahan yang merusak kompatibilitas. `v1.0.0` = rilis production pertama.
- **Rilis event pertengahan Oktober 2026 paling tinggi `v1.0.0`.** Uji beban dan gladi memakai `v1.0.0-rc.N`.
- Versi hanya dibuat saat merge ke `main` dengan persetujuan pemilik proyek. Banyak perbaikan boleh dikumpulkan dalam satu PATCH. Merge ke `develop` tidak membuat versi. Tidak ada bump otomatis.

Changelog (W10):

- `CHANGELOG.md` mengikuti Keep a Changelog 1.1.0, **ditulis dalam Bahasa Inggris**, kategori Added / Changed / Deprecated / Removed / Fixed / Security.
- **Wajib sebelum merge ke `develop`:** commit terakhir di branch fitur adalah `docs(changelog): ...` yang menambah baris di `[Unreleased]`. Tulis dari sudut pandang pemakai ("Hosts can lock new name claims"), bukan detail kode ("add join_locked_at column").
- Saat rilis di `release/x.y.z`: pindahkan `[Unreleased]` ke `[x.y.z] - YYYY-MM-DD`.
- Refactor, test, dan perubahan CI tidak dicatat kecuali berdampak ke pemakai.

## 6. Gerbang kualitas

Wajib lulus sebelum commit dan sebelum merge. Laporkan hasilnya apa adanya, termasuk yang gagal.

```bash
php artisan test            # Pest, suite penuh
vendor/bin/pint --test      # format
vendor/bin/phpstan analyse  # Larastan
```

Klaim "selesai" hanya boleh dibuat setelah perintah di atas dijalankan ulang dan output-nya dibaca. Hasil run sebelumnya tidak dihitung.

Definition of Done (O11) — fitur baru boleh di-merge ke `develop` hanya kalau:

- [ ] gerbang kualitas di atas lulus,
- [ ] `CHANGELOG.md` `[Unreleased]` sudah diisi (W10),
- [ ] untuk fitur yang punya tampilan: dicek di HP, proyektor 16:9, dan laptop,
- [ ] aksesibilitas dasar: kontras cukup, target sentuh minimal 44 px, bisa dipakai dengan keyboard,
- [ ] spesifikasi masih sesuai dengan kode (W8).

## 6a. Aturan repo dan tooling (W12–W17)

- **`.gitattributes`** memaksa akhir baris LF untuk semua berkas teks (pengembangan di Windows, container di Linux).
- **Test memakai MySQL**, bukan SQLite. CI menguji MySQL 8.0 dan 8.4 sampai versi server diketahui.
- **Migration yang sudah masuk `develop` tidak boleh diedit**; perubahan lewat migration baru. Migration harus aman saat container lama masih jalan sebentar selama deploy (tambah kolom dulu, hapus kolom lama di rilis berikutnya).
- **Hook lokal sebelum commit:** Pint pada berkas yang diubah, ESLint/Prettier untuk JS, dan pengecekan format pesan commit.
- **ESLint + Prettier** untuk kode Alpine dan JS, di hook lokal dan di CI.
- **Versi tool dikunci:** `composer.lock` dan `package-lock.json` di-commit; Node lewat `.nvmrc` dan `engines`; PHP lewat image Docker.

## 6b. Operasional (O1–O10)

- **CI (O1):** GitHub Actions menjalankan gerbang kualitas + `composer audit` di setiap push.
- **Deploy (O2):** `develop` → staging, tag di `main` → production. Deploy wajib me-restart Reverb. Ada langkah rollback tertulis. **Dilarang deploy selama event berlangsung.**
- **Rahasia (O3):** hanya di `.env` tiap lingkungan dan GitHub Secrets. `.env.example` selalu lengkap.
- **Seeder (O10):**
  - Super-admin dibuat dengan `php artisan pentahoot:create-super-admin`; email dan password ditanyakan saat perintah dijalankan, **tidak pernah** di seeder atau di kode.
  - `DatabaseSeeder` hanya data dasar yang aman dijalankan berulang.
  - `DemoSeeder` dan `LoadTestSeeder` wajib menolak jalan kalau `APP_ENV=production`.
- **Keamanan (O7):** upload divalidasi dari isi file, header keamanan (CSP), CSRF di semua POST, aturan password host, audit keamanan sebelum `v1.0.0`.
- **Backup (O4):** tanpa backup otomatis. **Backup manual `mysqldump` wajib tepat sebelum dan sesudah setiap event**, disimpan di luar VM.

## 6c. Aturan kode (K1–K8)

- **Test arsitektur (K1):** Pest arch dengan preset Laravel, Strict (strict types, class `final`), Security, ditambah: controller tidak memakai facade `DB`, engine game tidak bergantung pada class HTTP, `dd`/`dump` dilarang. Aturan arsitektur baru di berkas ini harus punya arch test.
- **Mutation test (K2):** `pest --mutate --min=<skor>` di CI untuk E1, E10, E3, E5.
- **Larastan (K3):** tanpa baseline.
- **Error API (K4):** selalu `{code, message}` dengan kode tetap (`VOTE_CLOSED`, `STALE_ACTION`, `NAME_TAKEN`, `EVENT_NOT_FOUND`, `CLAIMS_LOCKED`, ...). Setiap exception domain dipetakan ke satu kode.
- **Log (K5):** terstruktur dengan `event_id` dan request id. **Dilarang mencatat nama peserta, token klaim, dan token link personal.**
- **Konfigurasi (K6):** variabel wajib yang hilang membuat aplikasi gagal start dengan pesan jelas.
- **Upload (K7):** batas yang sama di validasi Laravel, PHP (`upload_max_filesize`, `post_max_size`), dan nginx (`client_max_body_size`).
- **Dependency (K8):** paket baru wajib disertai alasan di pesan commit.

## 6d. Pipeline (P1–P7)

- **Image (P1, P2):** dibangun sekali di CI, disimpan di GitHub Container Registry dengan tag commit SHA dan versi. Build bertahap, non-root, base image dikunci, tanpa dependency dev, opcache aktif, `healthcheck` di setiap container. VM hanya menarik image.
- **Scan (P3):** gitleaks + Trivy + `composer audit`.
- **Deploy (P4):** lewat SSH, **tanpa `git pull` dan tanpa `composer install` di server**: salin `compose.yaml` → `IMAGE_TAG=<sha> docker compose pull` → `docker compose run --rm app php artisan migrate --force` → `docker compose up -d` → smoke test `/up` + satu koneksi WebSocket → kalau gagal, kembali ke tag image sebelumnya.
- **Production (P5):** deploy menunggu persetujuan manual di environment GitHub.
- **Job CI (P6):** lint, analisis statis, test, mutation, browser, dijalankan paralel dengan cache; run lama dibatalkan.
- **Data (P7):** staging hanya data demo; data production tidak pernah disalin ke staging.
- **Langkah rilis lengkap** (persiapan pertama dan langkah berulang untuk staging, production, hotfix, dan hari event) ada di bagian 15 spesifikasi. Ikuti urutannya, jangan dihafal.

## 7. Test yang wajib ada (W6)

| Lapisan | Minimal mencakup |
| :-- | :-- |
| Unit | Peringkat kompetisi E1, tie-break E10, toleransi waktu E3, pemecah kotak E5 |
| Database | Unique index, aturan hapus FK, hasil beku G10 |
| Feature | Setiap rute + hak aksesnya (bagian 08 spesifikasi) |
| Kontrak realtime | Ukuran setiap jenis snapshot < 10 KB (F14), `state_version` naik di setiap perubahan (F15) |
| Beban | Skrip F19 di staging sebelum gladi |
| Browser (W11) | Pest Browser Testing untuk alur end-to-end (host + Public View + HP dalam satu test, termasuk Safari/WebKit dan emulasi iPhone); Vitest untuk logika JS murni (F4, F15, F17, E5) |

Bugfix wajib diawali test yang membuktikan bug-nya (merah), lalu diperbaiki sampai hijau, dan test itu tetap tinggal sebagai regression test.

## 8. Clean code

- Kode, identifier, pesan log, dan pesan error dalam **Bahasa Inggris**. Dokumentasi dan diskusi dalam Bahasa Indonesia, kecuali `CHANGELOG.md` dan pesan commit yang ditulis dalam Bahasa Inggris.
- Dilarang `// TODO`, kode setengah jadi, kredensial di kode, dan bypass tipe.
- Aturan main yang penting (E1, E3, E5, E10) ditulis sebagai class kecil yang diuji terpisah, bukan tersebar di controller atau view.
- Batasi satu giliran kerja ke 3–4 berkas. Setelah batch pertama (migration, model, kontrak engine) lulus test, **berhenti dan minta review** sebelum lanjut.

## 9. Urutan pengerjaan

Ikuti bagian 12 spesifikasi: fondasi → kerangka realtime → Pentahoot → Tebak Kata → Tebak Gambar → uji beban dan gladi.
