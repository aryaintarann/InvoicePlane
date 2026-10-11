# Notifikasi WhatsApp melalui Fonnte

InvoicePlane dapat mengantrekan pesan invoice terbit, pengingat jatuh tempo, dan pembayaran positif baru. Semua jenis pesan dan izin pelanggan default nonaktif. Email tetap menggunakan pengaturan dan alur yang sudah ada.

## Konfigurasi

1. Jalankan upgrade melalui wizard setup InvoicePlane atau `php index.php setup/cli/migrate`. Upgrade `047_1.7.6.sql` menambahkan antrean, izin pelanggan, dan penanda publikasi pertama. Backup database sebelum upgrade seperti biasa.
2. Tambahkan `FONNTE_TOKEN='token-perangkat'` pada `ipconfig.php`. Token berasal dari perangkat Fonnte milik Anda. Token dibaca menjadi konstanta `FONNTE_TOKEN`, tidak dapat diedit atau dilihat pada Settings.
3. Pada Settings > WhatsApp, aktifkan jenis pesan yang dibutuhkan dan sesuaikan template. Untuk recurring, aktifkan **Automatically publish recurring invoices for WhatsApp** serta **Invoice issued**. Opsi publikasi recurring tetap berlaku saat email belum dikonfigurasi atau gagal.
4. Pada formulir pelanggan, isi Mobile number dan centang persetujuan menerima WhatsApp. Nomor Indonesia `08…`, `628…`, dan `+628…` didukung; spasi, tanda kurung, dan tanda hubung dihapus. Nomor kosong, nomor tidak valid, dan persetujuan nonaktif dicatat sebagai alasan dilewati.
5. Pada `compose.yml`, service `scheduler` memanggil endpoint cron setiap menit secara otomatis. Pastikan Cron key sudah dibuat pada Settings > General; scheduler membaca key terbaru dari database. Untuk instalasi lain, panggil endpoint scheduler existing dengan cron key setiap menit. Contoh untuk Linux:

   ```cron
   * * * * * /usr/bin/curl --fail --silent --show-error 'https://invoice.example/index.php/invoices/cron/recur/CRON_KEY' >/dev/null
   ```

   Gunakan URL aplikasi dan cron key dari Settings. Lindungi konfigurasi cron serta akses log server karena URL cron mengandung kredensial. Interval setiap menit memproses antrean dan pengingat; tanggal pembuatan recurring tetap mengikuti jadwal recurring existing. Autentikasi dan pembatasan percobaan key tetap berlaku.

## Docker

Untuk deployment `compose.yml` (service `app`), simpan token pada file `.env` di sebelah file Compose:

```dotenv
FONNTE_TOKEN=token-perangkat-anda
```

`compose.yml` meneruskan nilai tersebut ke environment container. Entrypoint menuliskannya ke `ipconfig.php` pada setiap startup. Buat ulang container setelah mengubah token:

```sh
docker compose -f compose.yml up -d --build
```

Build diperlukan saat pertama memakai perubahan WhatsApp ini; image lama belum memiliki modul WhatsApp dan penerusan token. Setelah image diperbarui, perubahan token cukup memakai `docker compose -f compose.yml up -d app`. Jangan mengedit `ipconfig.php` langsung di container produksi karena entrypoint menimpanya saat startup.

Service `scheduler` memproses antrean secara otomatis setiap menit. Mengubah draft menjadi sent hanya membuat antrean; status `queued` dengan jumlah percobaan nol berarti worker belum memprosesnya. Periksa worker dengan `docker compose -f compose.yml logs --tail 10 scheduler`. Jika log menyatakan menunggu cron key, buat key melalui Settings > General. Scheduler mencoba lagi pada menit berikutnya ketika aplikasi/database belum tersedia.

Untuk development `docker-compose.yml` (service `php`), tambahkan `FONNTE_TOKEN='token-perangkat-anda'` pada `ipconfig.php` di folder proyek host. Folder proyek di-mount ke `/var/www/projects/invoiceplane`, sehingga file tersebut langsung tersedia bagi aplikasi. Entrypoint development mempertahankan file yang sudah ada.

## Pesan dan jadwal

Placeholder yang diterima hanya `{customer}`, `{number}`, `{amount}`, `{balance}`, `{due_date}`, dan `{link}`. Untuk invoice terbit dan pengingat, amount adalah total invoice; untuk pembayaran, amount adalah nominal pembayaran baru dan balance adalah saldo setelah pembayaran tersebut berhasil dicatat. Tautan menuju invoice publik. Pesan dibuat saat pengiriman, tanpa menyimpan salinan pesan atau tautan publik pada antrean/log aplikasi.

Invoice terbit dikirim sekali saat draft dipublikasikan. Menyimpan ulang, mengedit pembayaran, atau mengirim email tidak menghasilkan kejadian duplikat. Publikasi pertama ketika fitur nonaktif tetap ditandai agar aktivasi kemudian tidak mengirim invoice terbit lama. Pembayaran lama tidak dipindai. Invoice lama yang masih terbuka dapat menerima pengingat mendatang.

Default pengingat: `-3,0,3`, pukul `09:00`, zona waktu `Asia/Jakarta`. Selisih negatif berarti sebelum jatuh tempo, positif berarti setelahnya. Hanya invoice berstatus sent/viewed dengan saldo positif yang memenuhi syarat. Cron yang terlambat masih mengirim setelah jam tersebut pada tanggal lokal yang sama. Pengingat tanggal sebelumnya dilewati. Izin, nomor, status, saldo, tanggal jatuh tempo, serta jadwal diperiksa ulang sebelum pengiriman. Waktu antrean dan riwayat disimpan dalam UTC.

## Riwayat dan retry

Halaman invoice, termasuk Sumex, menampilkan status queued, processing, accepted by provider, failed, needs review, atau skipped, beserta jumlah percobaan dan ID pesan Fonnte. **Accepted by provider** berarti Fonnte menerima pesan ke antreannya; status delivered/read belum didukung.

Penolakan eksplisit Fonnte dicoba ulang tiga kali setelah percobaan awal, dengan jeda 1, 5, dan 15 menit. Token kosong juga mengikuti batas tersebut. Timeout, kesalahan koneksi, respons tidak dapat dikenali, serta worker yang terhenti masuk **Needs review**, tanpa pengiriman ulang otomatis. Respons provider mentah dan exception HTTP tidak dicatat agar token/tautan tidak bocor. Gunakan dashboard Fonnte untuk memeriksa detail perangkat/token.

Admin dapat melakukan retry failed, needs review, atau skipped melalui tombol pada riwayat invoice. Retry memakai POST dan CSRF, mencatat ID admin, mempertahankan total percobaan, dan memulai ulang batas retry otomatis. **Periksa riwayat Fonnte dahulu untuk Needs review**: pesan mungkin sudah diterima provider sebelum timeout. Job accepted atau processing tidak dapat di-retry. Pengingat kedaluwarsa tetap dilewati walaupun diminta ulang.

Worker memakai update bersyarat untuk klaim atomik dan kunci unik untuk setiap kejadian. Satu eksekusi memproses maksimal 20 job, dengan batas waktu kerja sekitar 40 detik ditambah request terakhir. Request memiliki connect timeout 3 detik dan total timeout 8 detik, memakai verifikasi TLS dan tidak mengikuti redirect. Preview tautan dimatikan. Tidak ada webhook atau percakapan masuk pada versi ini. Pengiriman memakai [API send message Fonnte](https://docs.fonnte.com/api-send-message/).

## Pengujian

```sh
composer install
vendor/bin/phpunit
```

Uji integrasi memakai **database khusus `invoiceplane_whatsapp_test` yang akan dihapus dan dibuat ulang**. Jangan gunakan database produksi. Dengan MySQL/MariaDB lokal dan akun yang dapat membuat database:

```sh
IP_TEST_DB=1 IP_TEST_DB_HOST=127.0.0.1 IP_TEST_DB_PORT=3306 \
IP_TEST_DB_USER=root IP_TEST_DB_PASSWORD=root vendor/bin/phpunit
```

Uji integrasi memerlukan ekstensi PHP `mysqli` dan `pdo_mysql`. Uji menggunakan mekanisme setup SQL/PHP asli, model pembayaran asli, controller recurring asli, dan mock HTTP Fonnte. Uji tanpa database melewati suite integrasi. Windows PowerShell: set variabel melalui `$env:IP_TEST_DB='1'` dan panggil `php vendor/bin/phpunit`.

Sebelum mengaktifkan pelanggan umum, gunakan satu pelanggan internal dengan persetujuan: publikasikan draft, simpan ulang, buat recurring tanpa SMTP, jalankan pengingat, catat pembayaran sebagian lalu pelunasan, dan periksa WA serta ID pesan di dashboard Fonnte. Kegagalan pengiriman tidak membatalkan invoice/pembayaran yang telah disimpan. Jika database antrean gagal setelah commit bisnis, periksa log aplikasi dan riwayat; versi ini tidak memulihkan kejadian terbit/pembayaran yang gagal diantrekan secara otomatis.
