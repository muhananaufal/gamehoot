# HP menampilkan "Time is up" setelah reload walau sudah memilih

- Tanggal: 2026-10-01
- Status: selesai
- Lingkungan: lokal, v0.5.0 (audit sebelum tahap 6)
- Request id / event id: tidak dicatat (sebelum `X-Request-Id` ada)

## Gejala

Pada audit, HP yang sudah memilih menampilkan "Time is up · Voting for this question has closed" tanpa "Your pick" setelah waktu soal habis. Peserta bisa mengira suaranya tidak terhitung. Saat soal masih live dan setelah Reveal, "Your pick" tampil normal.

## Reproduksi

1. Host memulai soal Pentahoot.
2. HP memilih nama dan mengirim (201).
3. Tunggu sampai lewat `ends_at` + 1 detik (fase closed, E3).
4. Muat ulang halaman `/play` di HP.

## Hipotesis

| Hipotesis | Cara menguji | Hasil |
| :-- | :-- | :-- |
| Server tidak mengembalikan suara HP di fase closed | `GET /{event}/state` dengan cookie klaim HP | Gugur: `me.vote` ada |
| Store membuang snapshot berversi sama | Baca `realtime.js accept()` | Gugur: versi sama diterima |
| View salah memilih tampilan saat closed | Baca getter `view` dan `myVote` | Gugur: closed + `myVote` memberi `sent` |
| Render pertama tidak punya `me` | Tanpa reload, cek tampilan di fase closed: benar. Setelah reload, bandingkan `initial` di `#realtime-config` dengan respons `/state` | Terbukti: `initial` tanpa `me`, `/state` dengan `me` |

## Akar masalah

`ClientConfig::for()` menanam snapshot publik ke halaman tanpa `me` (nama dan suara HP itu), sedangkan `/state` menambahkannya. Setelah reload, render pertama tidak tahu HP sudah memilih dan menampilkan "Time is up" sampai respons `/state` tiba. Di jaringan lambat kilatan itu bisa beberapa detik.

## Perbaikan

Commit `fix(join): send the phone's own vote with the first snapshot`: `PlayController` mengirim `PhoneState::for()` ke komponen `x-realtime`, dan `ClientConfig::for()` menaruhnya di `initial.me`. Test: `ScreensTest` "puts the phone's own name and vote into the first snapshot" (merah sebelum perbaikan, hijau sesudahnya).

## Pelajaran

- Gejala awal ("tanpa Your pick setelah waktu habis") tidak sama dengan penyebabnya ("render pertama setelah reload"). Mereproduksi dengan dan tanpa reload langsung memisahkan keduanya.
- Data per pengguna yang ditambahkan oleh satu endpoint (`/state`) harus ikut di setiap jalur yang membawa snapshot yang sama, termasuk snapshot awal di halaman.
