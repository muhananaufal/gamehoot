# Test browser timeout di klik yang berpindah halaman, padahal halamannya sudah termuat

- Tanggal: 2026-10-02
- Status: penyebab diketahui; tidak diubah di kode (lingkungan lokal)
- Lingkungan: Docker lokal di Windows + WSL, `pentahoot-php:browser`, Chrome dan Safari; di CI Safari dua kali (tahap 6 "Show leaderboard", lalu "confirm-winner")
- Request id / event id: tidak relevan (test server Pest)

## Gejala

`HostSetupTest` "lets a host create an event and add names" gagal 3 dari 7 kali di lokal dengan `Timeout 30000ms exceeded` pada `->press('Create event')`. Sekali juga pada `->press('Save question')` (Tebak Kata, Safari). Screenshot kegagalan selalu menunjukkan halaman **tujuan** (Names, paket soal) yang sudah termuat dan datanya sudah tersimpan. Run berikutnya dengan kode yang sama lolos.

Kejadian di CI (2026-10-02, Safari, `TebakKataGameTest`): `->click('@confirm-winner')` timeout. Tombol itu menutup dialognya lalu mengirim aksi; percobaan ulang mencari tombol yang kini berada di dialog tertutup. Job dijalankan ulang tanpa perubahan kode; hasilnya hijau (run 36966911781, attempt 2).

## Reproduksi

1. Jalankan `HostSetupTest` di container browser lokal beberapa kali berturut-turut.
2. Kegagalan muncul tidak tentu di klik yang mengirim form dan berpindah halaman.

## Hipotesis

| Hipotesis | Cara menguji | Hasil |
| :-- | :-- | :-- |
| Halaman tujuan error atau JS-nya rusak | Test diagnostik: setelah klik, baca `Alpine.store('toasts')` dan `assertNoJavaScriptErrors()` | Gugur: toast ada, tanpa error |
| Teks tombol ambigu (lebih dari satu "Create event") | Cari teks di view dan berkas bahasa | Gugur: hanya satu |
| Aset dev Vite (`public/hot`) menggantung | Cek `public/hot`, baca `ViteFonts` | Gugur: `public/hot` tidak ada |
| Klik sukses tetapi dianggap gagal, lalu diulang di halaman baru | Baca `Execution::waitForExpectation()` dan `AwaitableWebpage::__call()` di plugin | Terbukti (lihat akar masalah) |

## Akar masalah

`pest-plugin-browser` menjalankan setiap aksi lewat `Execution::waitForExpectation()`: satu percobaan diberi timeout Playwright **1 detik**, lalu diulang sampai batas 30 detik. Klik yang mengirim form menunggu navigasinya. Di Docker lokal satu permintaan butuh 2–3 detik, jadi percobaan pertama sering melewati 1 detik dan dianggap gagal, **padahal form sudah terkirim**. Percobaan berikutnya mencari tombol yang sama di halaman tujuan, tidak menemukannya, dan berakhir dengan timeout 30 detik. Di CI permintaan lebih cepat, sehingga jarang terjadi.

## Perbaikan

Tidak ada perubahan kode aplikasi: aplikasinya benar. Test tidak diubah, karena mengganti setiap klik yang berpindah halaman di semua test browser lebih berisiko daripada masalahnya. CI tetap menjadi acuan: kegagalan seperti ini di lokal dijalankan ulang saat mesin tidak sibuk. Kasus `confirm-winner` muncul lagi di run CI berikutnya (run 36968819563, Safari); screenshot artifact `browser-screenshots-safari-2` menunjukkan "Winner: Rita Wulandari" sudah tampil, jadi klik pertama berhasil. Langkah itu di `TebakKataGameTest` kini memakai `->script()` yang langsung memanggil `click()` lalu menunggu "Winner:", sehingga tidak ada percobaan ulang aksi yang tidak idempoten. Klik lain yang berpindah halaman diubah dengan cara yang sama hanya kalau muncul berulang di CI.

Kejadian 2026-10-09 (Safari, gate run 37895969265): `HostSetupTest` "lets a host create an event and add names", `->click('Import CSV')` timeout 30 detik. Link itu memuat modal dari server (Unpoly, U18); screenshot menunjukkan modal "Import names" sudah terbuka, jadi klik pertama berhasil dan percobaan ulangnya terhalang modal. Langkah itu kini menunggu dialog sebelumnya tertutup lalu memakai `->script()`.

## Pelajaran

- Screenshot di halaman tujuan berarti aksinya sudah terjadi; yang perlu dicurigai adalah mekanisme retry, bukan aplikasinya.
- Baca cara test runner mengulang aksi sebelum mencurigai kode: aksi yang tidak idempoten (kirim form) dan retry otomatis adalah kombinasi yang rapuh.
