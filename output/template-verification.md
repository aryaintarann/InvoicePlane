# Pemeriksaan template Professional

PASS implementasi: Professional terdaftar di dua allowlist PDF eksplisit dan dipasang read-only di luar document root Docker.
PASS aktivasi: keempat pengaturan PDF invoice/default/paid/overdue dan quote terbaca kembali sebagai Professional setelah disimpan melalui model Settings.
PASS PHPUnit: 41 tes, 232 assertion, tanpa skip; PHP 8.2.34 dan MariaDB 10.11 terpisah dari database aplikasi.
PASS format dan sintaks: Pint, php -l, dan docker compose config -q.
PASS PDF: enam dokumen mPDF dirender menjadi PNG; semua 12 halaman diperiksa, teks berada dalam batas A4.
PASS pagination: invoice dan quote tiga item masing-masing satu halaman; 45 item masing-masing empat halaman, semua item dan nomor halaman tersedia, judul kolom berulang.
PASS edge cases: diskon item, logo besar, nama item panjang, footer tiga baris; font nama item tetap minimal 8,5 pt dan logo maksimal 42 × 18 mm.
PASS aplikasi: halaman login HTTP 200, controller pratinjau CLI dan jalur source template HTTP 404; controller sementara dihapus setelah verifikasi.
PASS review: kedua temuan kecil diperbaiki dan diuji, review akhir tidak menemukan masalah yang perlu ditindaklanjuti.

## Delivery gate desain

R-02 PASS: judul dan teks contoh tanpa em dash; nominal memakai formatter aplikasi.
R-03 PASS: seluruh teks dari 12 halaman ada di dalam batas halaman; PDF memiliki ukuran A4 tetap.
R-17 PASS: tidak ada statistik pemasaran; semua nominal pratinjau diberi penanda contoh.
R-18 PASS: tidak ada testimonial atau identitas pelanggan nyata yang dibuat-buat.
R-23 PASS: logo produksi dari Settings; logo fixture diberi label LOGO UJI (CONTOH), hanya untuk uji dan sudah dihapus dari uploads.
R-24 PASS: tidak ada navigasi baru dalam PDF.
R-25 PASS: teks #161616 dan #555555 di atas putih, rasio kontras masing-masing sekitar 18,1:1 dan 7,5:1.
R-26 PASS: pilihan template menggunakan selector Settings existing; backend allowlist dan pilihan tersimpan sudah diverifikasi.
R-27 PASS: field opsional kosong tidak menimbulkan error, diuji dalam PHPUnit; PDF tidak memiliki loading state interaktif.
R-28 PASS: tidak ada FAQ.
R-32 PASS: tidak ada kontrol interaktif baru; hasil PDF berisi teks yang dapat dipilih.
R-33 PASS: implementasi berada di view PHP dan konfigurasi Compose, tanpa modifikasi runtime DOM/CSS.
R-34 PASS: tidak ada theme toggle; putih dipilih pengguna untuk cetak.
R-35 PASS: aplikasi Docker berjalan, mPDF aktual merender semua sampel, pilihan default tersimpan dan terbaca kembali.
R-36 PASS: tidak ada klaim keamanan, performa, sertifikasi, atau pelanggan pada dokumen.
R-37 PASS: arahan pengguna hitam-putih minimalis diterapkan; ENERGY 1 / RHYTHM 2 / MOTION 1 dicatat sebelum implementasi.
R-38 PASS: seluruh isi pratinjau secara eksplisit ditandai contoh; dokumen aplikasi mengambil data perusahaan dan pelanggan aktual.

R-01 PASS: tidak memakai gradient atau glow.
R-04 PASS: tidak memakai ikon dekoratif.
R-06 PASS: DejaVu Sans dipilih untuk dukungan mPDF dan keterbacaan cetak; judul uppercase membedakan jenis dokumen.
R-07 PASS: tidak memakai grid dekoratif atau pola latar.
R-08 PASS: tidak memakai panah dekoratif.
R-09 PASS: status berupa teks sederhana dari data invoice/quote, tanpa capsule badge.
R-10 PASS: tidak memakai glassmorphism.
R-12 PASS: tidak memakai shadow.
R-13 PASS: tidak memakai glow.
R-14 PASS: tidak memakai feature cards.
R-19 PASS: tidak memakai animasi, sesuai dokumen cetak.
R-22 PASS: tidak memakai ilustrasi generik.

Dials PASS: ENERGY 1 / RHYTHM 2 / MOTION 1 sesuai brief cetak profesional.
Focal point PASS: judul dokumen 25 pt dan saldo/total 12 pt membentuk hierarki yang terlihat pada PNG.
Whitespace PASS: identitas, penerima, item, ringkasan, dan catatan dipisahkan oleh jarak terukur.
Accent PASS: garis hitam 2 px menegaskan saldo/total, sesuai arahan pengguna tanpa warna tambahan.
Identity motif PASS: garis tipis tabel dan garis tebal pada identitas/totals dipakai konsisten di invoice dan quote.
Design read PASS: brief dan alasan tipografi/spasi dicatat sebelum pembuatan pada catatan kerja.

C-1 PASS: warna dari pilihan pengguna; tipografi, ukuran A4, serta batas logo dipilih untuk cetak dan mPDF.
C-2 PASS: tidak ada kontrol PDF yang tidak bekerja; pilihan Settings menyimpan template aktif.
C-3 PASS: setiap bagian menyajikan data transaksi, identitas, catatan, atau pembayaran.
C-4 PASS: zero quantity, field kosong, paid/overdue, diskon, nama panjang, logo besar dan multipage sudah diuji.
C-5 PASS: tidak ada testimonial, statistik atau klaim fabricated.
R-05 PASS: komposisi mengikuti urutan dokumen bisnis, bukan layout landing page.
R-11 PASS: tidak memakai pill-shaped components.
R-15 PASS: tidak ada CTA generik.
R-16 PASS: tidak ada buzzword pemasaran.
R-20 PASS: desain bisnis sengaja dapat digunakan ulang; identitas dan logo mengikuti perusahaan pengguna sesuai brief.
R-21 PASS: mode putih sesuai permintaan pengguna.
R-29 PASS: palet monokrom #161616/#555555/putih dengan garis abu-abu untuk tabel.
R-30 PASS: komposisi PDF transaksi, tanpa meniru UI produk web lain.
R-31 PASS: judul menandai jenis dokumen, garis mengelompokkan rincian, saldo ditonjolkan untuk tindakan pembayaran, margin menjaga keterbacaan cetak.
