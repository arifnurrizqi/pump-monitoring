# Hydroflow Client Deployment

Paket ini menjalankan Hydroflow dari private registry atau bundle image offline. Source code dan proses build tidak diperlukan pada server client.

Penggunaan paket tunduk pada Surat Pernyataan Amanah Penggunaan Hydroflow yang disepakati Arnur Tech dan client. Kepemilikan bundle/image tidak memberikan izin untuk menduplikasi, memodifikasi, mendistribusikan, atau menggunakannya bersama perangkat di luar ruang lingkup yang diberikan.

Panduan lengkap yang ditujukan untuk pelaksana instalasi tersedia di [INSTALLATION.md](INSTALLATION.md). Tim IT client sebaiknya mengikuti runbook tersebut dari pemeriksaan spesifikasi server sampai checklist serah-terima.

> Image Docker tetap dapat diperiksa oleh administrator server. Perlindungan distribusi ini membatasi akses repository dan alur pengembangan, bukan menjadikan source di dalam image mustahil diekstrak. Gunakan perjanjian lisensi dan kontrol akses server sebagai lapisan tambahan.

## Prasyarat

- Linux server dengan Docker Engine dan Docker Compose plugin.
- Akun registry read-only, atau file bundle offline sesuai arsitektur server.
- DNS/Cloudflare Tunnel jika dashboard diakses melalui internet.

Image resmi repository ini diterbitkan oleh GitHub Actions ke namespace `ghcr.io/arifnurrizqi` ketika tag release seperti `v1.0.0` dibuat.

## Instalasi melalui registry

1. Login ke registry menggunakan token read-only:

   ```bash
   echo "REGISTRY_READ_TOKEN" | docker login ghcr.io -u REGISTRY_USER --password-stdin
   ```

2. Salin `.env.example` menjadi `.env.docker`, lalu ganti seluruh nilai kosong dan `CHANGE_THIS_*`:

   ```bash
   cp .env.example .env.docker
   chmod 600 .env.docker
   nano .env.docker
   ```

3. Pastikan `HYDROFLOW_VERSION`, `APP_VERSION`, dan image release yang akan dipasang sama. Generate nilai rahasia menggunakan `openssl rand`.

4. Instal:

   ```bash
   chmod +x scripts/*.sh
   ./scripts/install.sh
   ```

5. Untuk Cloudflare Tunnel container, isi `CLOUDFLARE_TUNNEL_TOKEN`. Public Hostname harus menunjuk ke `http://nginx:80`. Jika token kosong, profile tunnel tidak dijalankan.

## Instalasi offline

Artifact release offline berisi image Hydroflow, PostgreSQL, Redis, Cloudflared, Compose, dan script deployment. Server client tidak perlu login GHCR maupun menarik image dari internet.

1. Pastikan bundle sesuai arsitektur server:

   ```bash
   uname -m
   ```

   `x86_64` memakai bundle `amd64`, sedangkan `aarch64` memakai bundle `arm64`.

2. Masuk ke folder `deployment` dari artifact, lalu siapkan konfigurasi offline:

   ```bash
   cp .env.example .env.docker
   chmod 600 .env.docker
   nano .env.docker
   ```

   Pertahankan nilai berikut:

   ```env
   HYDROFLOW_DISTRIBUTION=offline
   HYDROFLOW_PULL_POLICY=never
   HYDROFLOW_REGISTRY=arnur
   ```

   Sesuaikan `HYDROFLOW_VERSION` dan `APP_VERSION` dengan versi bundle, serta ganti seluruh nilai `CHANGE_THIS_*`.

3. Verifikasi checksum dan load seluruh image:

   ```bash
   chmod +x scripts/*.sh
   ./scripts/load-images.sh hydroflow-1.0.0-amd64-images.tar.gz
   ```

4. Instal tanpa registry pull:

   ```bash
   ./scripts/install.sh
   ```

`HYDROFLOW_PULL_POLICY=never` memastikan Docker tidak diam-diam menarik image ketika instalasi offline. Installer akan berhenti dan menyebut image yang belum tersedia jika proses load belum lengkap.

## Update

Ubah `HYDROFLOW_VERSION` dan `APP_VERSION` pada `.env.docker`, kemudian:

```bash
./scripts/update.sh
```

Skrip membuat backup sebelum menarik image dan menjalankan migrasi.

Untuk update offline, unduh/kirim artifact versi baru, load bundle barunya, ubah `HYDROFLOW_VERSION` dan `APP_VERSION`, lalu jalankan `./scripts/update.sh`. Mode offline tidak melakukan registry pull.

## Backup dan restore

```bash
./scripts/backup.sh
./scripts/restore.sh backups/hydroflow-YYYYMMDDTHHMMSSZ.dump
```

Restore menghentikan proses aplikasi yang dapat menulis database dan meminta konfirmasi eksplisit.

## Operasional

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs --tail=100 app telemetry-subscriber scheduler redis reverb nginx mqtt
curl -i http://127.0.0.1:8080/up
```

Jangan mengirim `.env.docker`, token registry, backup database, atau token Cloudflare kepada pihak yang tidak berwenang.
