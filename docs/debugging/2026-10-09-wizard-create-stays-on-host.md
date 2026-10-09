# Test browser wizard: "Event created" tidak muncul, halaman tetap di /host

- Tanggal: 2026-10-09
- Status: penyebab belum diketahui; bukti visual hilang karena bug CI (sudah diperbaiki), menunggu kejadian berikutnya
- Lingkungan: CI repo gerbang, runner `ubuntu-24.04` privat 2 core, Chrome, image `pentahoot-ci:browser-*`
- Request id / event id: tidak relevan (test server Pest)

## Gejala

`HostSetupTest` "sets up a new event with names and games in four steps, saved at the last one (U26)" gagal sekali di Chrome pada run 37880572292 (commit `fdd73cd`):

```
Expected to see text [Event created. Open it when you are ready, and players can join.] on the page
initially with the url [http://127.0.0.1:34769/host], but it was not found or not visible.
```

Commit yang sama lulus di run 37879355750 dan lulus lagi saat job browser run 37880572292 dijalankan ulang (attempt 2). Branch itu tidak mengubah kode aplikasi atau test (`git diff 62eec28 fdd73cd -- app resources routes tests database config` kosong). Safari di run yang sama hijau.

## Bukti

- Setelah klik "Create event", `EventController::store` me-redirect ke `host.events.live` (`app/Http/Controllers/Host/EventController.php:43`). Saat gagal, URL halaman **masih `/host`** setelah batas 30 detik: event tidak dibuat atau navigasinya tidak terjadi. Ini bukan toast yang terlalu cepat hilang.
- Semua klik di test ini sudah lewat `->script()`, jadi penyebab di `2026-10-02-browser-click-timeout-after-navigation.md` (klik yang diulang plugin) tidak berlaku di sini.
- Screenshot kegagalan **tidak ada**: run browser Safari di job yang sama mengosongkan `tests/Browser/Screenshots` saat mulai (`Screenshot::cleanup()` di `vendor/pestphp/pest-plugin-browser/src/Filters/UsesBrowserTestCaseMethodFilter.php:57-61`), lalu upload melapor "No files were found". Bug ini lahir saat Chrome dan Safari digabung dalam satu job (P6, 9 Oktober 2026) dan sudah diperbaiki: screenshot tiap browser disisihkan ke `browser-screenshots/<browser>` sebelum run berikutnya.

## Hipotesis

| Hipotesis | Cara menguji | Hasil |
| :-- | :-- | :-- |
| Tombol Create event masih disabled atau sedang transisi animasi (motion U30) saat `.click()` dipanggil, sehingga klik tidak berefek | Screenshot kejadian berikutnya; atau di test, catat `disabled` dan posisi langkah tepat sebelum klik | Belum diuji |
| Pindah ke langkah review (`[data-step=review]`) belum selesai saat tombol Create diklik | Sama | Belum diuji |
| Request create gagal atau sangat lambat di runner (validasi, database) | Log aplikasi di artifact; assert respons lewat `up:request:load` seperti test lain di berkas ini | Belum diuji |

## Perbaikan

Belum ada perubahan test: tanpa bukti, mengganti cara klik hanya menebak. Yang sudah dilakukan: screenshot kegagalan kini bertahan (lihat Bukti). Kegagalan berikutnya ditelusuri dari screenshot di artifact `browser-screenshots` run itu, lalu catatan ini diperbarui.

## Pelajaran

- Menggabung job yang menjalankan alat yang sama dua kali bisa membuat run kedua menghapus bukti run pertama. Saat menggabung job, periksa apa yang dibersihkan alat itu saat mulai.
- Pola gagal yang mirip (test browser, flaky, Chrome) belum tentu punya penyebab yang sama; cocokkan pesan error dan langkah yang gagal sebelum memakai perbaikan lama.
