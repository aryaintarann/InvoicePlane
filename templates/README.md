# Template PDF Professional

Template invoice dan quote A4, hitam-putih untuk cetak. Logo dan identitas
perusahaan, bahasa, format tanggal, mata uang, catatan, serta informasi rekening
mengikuti pengaturan InvoicePlane. Total, pajak, diskon, pembayaran, dan saldo
menggunakan nilai yang sudah dihitung oleh aplikasi.

## Docker proyek ini

`compose.yml` memasang folder ini sebagai volume read-only di
`/var/www/invoiceplane-templates/`, di luar document root Apache. Template
`Professional` terdaftar melalui `CUSTOM_INVOICE_TEMPLATES_PDF` dan
`CUSTOM_QUOTE_TEMPLATES_PDF`; daftar template tidak dibuat dengan scanning file.

Jalankan `docker compose -f compose.yml up -d`, lalu pilih **Professional** pada:

- **Settings → Invoices**: PDF default, paid, dan overdue.
- **Settings → Quotes**: template PDF quote.

Invoice lunas dan terlambat memakai desain yang sama, dengan status sesuai
invoice. Perubahan file di folder ini langsung tersedia melalui bind mount;
tidak perlu membangun ulang image Docker.

## Mengubah desain

- `shared/professional.php`: tata letak bersama, CSS cetak, dan data dokumen.
- `invoice_templates/pdf/Professional.php`: pembungkus invoice.
- `quote_templates/pdf/Professional.php`: pembungkus quote.

Pertahankan escaping data dan validasi logo saat mengedit. `sheet-size: A4`
dan `size: auto` mengikuti parser mPDF. Footer memakai nama yang ditetapkan
pada `@page` agar muncul di semua halaman. Nama item panjang diberi titik
pemisah baris tidak terlihat agar tabel tetap terbaca.

Untuk instalasi tanpa Docker, arahkan `CUSTOM_TEMPLATES_FOLDER` di `ipconfig.php`
ke folder ini, dengan pemisah direktori di akhir, dan tetapkan kedua allowlist
PDF di atas menjadi `Professional`. Pilih template di Settings.

Pratinjau menggunakan data contoh: [invoice](../output/pdf/professional-invoice-preview.pdf)
dan [quote](../output/pdf/professional-quote-preview.pdf).

Pengujian template: `php vendor/bin/phpunit --filter ProfessionalTemplateTest`.
