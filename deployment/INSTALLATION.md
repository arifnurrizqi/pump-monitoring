# Runbook Instalasi Hydroflow di Server Client

Dokumen ini ditujukan untuk tim IT client. Instalasi menggunakan bundle Docker offline dan tidak memerlukan repository source ataupun akun registry.

## 1. Kebutuhan sistem

### Minimum

| Komponen | Minimum |
| --- | --- |
| CPU | 2 core 64-bit (`amd64` atau `arm64`) |
| RAM | 2 GB RAM + 2 GB swap |
| Penyimpanan | 15 GB kosong pada filesystem Docker |
| Sistem operasi | Linux 64-bit dengan Docker Engine dan Docker Compose v2 |
| Waktu | NTP aktif, timezone server disarankan `Asia/Jakarta` |

Spesifikasi minimum cocok untuk instalasi kecil/demo. Proses PHP-FPM, subscriber telemetry, scheduler, Reverb, PostgreSQL, Redis, dan Nginx berjalan bersamaan sehingga swap wajib disediakan bila RAM hanya 2 GB. Broker MQTT berjalan di luar server aplikasi.

### Rekomendasi production

| Komponen | Rekomendasi |
| --- | --- |
| CPU | 4 vCPU |
| RAM | 4 GB atau lebih |
| Penyimpanan | SSD 30 GB atau lebih, ditambah ruang backup |
| Sistem operasi | Debian 12 atau Ubuntu Server 22.04/24.04 LTS 64-bit |
| Backup | Penyimpanan kedua/off-server dengan retensi terjadwal |

Kapasitas penyimpanan harus disesuaikan lagi dengan jumlah device, interval pengiriman telemetry, dan masa retensi data. Jangan menyimpan seluruh backup hanya di disk server yang sama.

### Port

| Port | Penggunaan | Paparan yang disarankan |
| ---: | --- | --- |
| `8080/tcp` | Dashboard HTTP internal | Localhost/reverse proxy atau LAN |
| Port broker MQTT (umumnya `8883/tcp`) outbound | Subscriber ke broker eksternal | Izinkan ke hostname dan port broker yang dipilih |
| `80/443` | Reverse proxy HTTPS atau Cloudflare | Internet jika diperlukan |
| `7844/tcp+udp` outbound | Cloudflare Tunnel | Hanya jika memakai tunnel |

PostgreSQL dan Reverb tidak dipublikasikan ke host.

## 2. Informasi yang harus disiapkan

Sebelum instalasi, tentukan:

- ID client dan ID instalasi.
- Domain dashboard atau alamat IP/LAN.
- Arsitektur server: AMD64 atau ARM64.
- Host, port, kredensial, dan sertifikat broker MQTT eksternal yang dapat dijangkau server serta gateway.
- Metode akses dashboard: LAN langsung, reverse proxy HTTPS, atau Cloudflare Tunnel.
- Lokasi backup di luar server.
- Surat Pernyataan Amanah dan identitas penggunaan yang telah diisi/disepakati.

Jalankan pemeriksaan server:

```bash
uname -m
docker --version
docker compose version
free -h
df -h /var/lib/docker
timedatectl status
```

Pemetaan arsitektur:

```text
x86_64  → bundle amd64
aarch64 → bundle arm64
```

## 3. File yang diterima tim IT

Paket offline berisi struktur berikut:

```text
hydroflow-VERSION-ARCH-offline/
├── RELEASE.txt
└── deployment/
    ├── .env.example
    ├── LICENSE.md
    ├── SURAT_PERNYATAAN_AMANAH.md
    ├── docker-compose.yml
    ├── hydroflow-VERSION-ARCH-images.tar.gz
    ├── hydroflow-VERSION-ARCH-images.tar.gz.sha256
    └── scripts/
```

Jika paket dikirim sebagai `.tar`, verifikasi sebelum ekstraksi:

```bash
sha256sum -c hydroflow-1.0.0-amd64-offline.tar.sha256
tar -xf hydroflow-1.0.0-amd64-offline.tar
cd hydroflow-1.0.0-amd64-offline/deployment
```

Jika paket berasal dari artifact GitHub Actions berbentuk ZIP, ekstrak ZIP lalu masuk ke folder `deployment`.

## 4. Siapkan konfigurasi

```bash
cp .env.example .env.docker
chmod 600 .env.docker
nano .env.docker
```

Pertahankan mode offline:

```env
HYDROFLOW_DISTRIBUTION=offline
HYDROFLOW_PULL_POLICY=never
HYDROFLOW_REGISTRY=arnur
HYDROFLOW_VERSION=1.0.0
APP_VERSION=1.0.0
```

`HYDROFLOW_VERSION` dan `APP_VERSION` harus sama dengan versi pada `RELEASE.txt`.

Generate nilai rahasia satu per satu:

```bash
echo "base64:$(openssl rand -base64 32)"
openssl rand -hex 24
openssl rand -hex 24
openssl rand -hex 16
openssl rand -hex 32
```

Gunakan hasilnya untuk:

```env
APP_KEY=base64:HASIL_PERTAMA

DB_PASSWORD=HASIL_PASSWORD_DATABASE
POSTGRES_PASSWORD=HASIL_PASSWORD_DATABASE

MQTT_PASSWORD=HASIL_PASSWORD_MQTT

REVERB_APP_KEY=HASIL_KEY_REVERB
REVERB_APP_SECRET=HASIL_SECRET_REVERB
```

Jangan gunakan karakter spasi atau tanda `#` pada password `.env`. Jangan mengirim `.env.docker` melalui chat/email biasa.

Identitas instalasi wajib unik:

```env
CUSTOMER_ID=CLIENT-001
INSTALLATION_ID=CLIENT-001-SERVER-01
```

## 5. Pilih metode akses dashboard

Gunakan hanya salah satu skenario berikut.

### A. LAN atau IP langsung

Cocok jika dashboard hanya dibuka dari jaringan internal:

```env
APP_URL=http://192.168.1.20:8080
APP_BIND_IP=0.0.0.0
APP_PORT=8080
SESSION_SECURE_COOKIE=false

REVERB_ALLOWED_ORIGINS=192.168.1.20
REVERB_PUBLIC_HOST=192.168.1.20
REVERB_PUBLIC_PORT=8080
REVERB_PUBLIC_SCHEME=http

CLOUDFLARE_TUNNEL_TOKEN=
```

Batasi port `8080` menggunakan firewall agar hanya subnet internal yang dapat mengaksesnya.

### B. VPS dengan domain dan reverse proxy HTTPS

Ini rekomendasi untuk VPS tanpa Cloudflare Tunnel. Reverse proxy milik tim IT (Caddy, Nginx Proxy Manager, Traefik, atau Nginx host) menangani TLS pada port `443` dan meneruskan trafik ke `http://127.0.0.1:8080`.

```env
APP_URL=https://hydroflow.example.com
APP_BIND_IP=127.0.0.1
APP_PORT=8080
SESSION_SECURE_COOKIE=true

REVERB_ALLOWED_ORIGINS=hydroflow.example.com
REVERB_PUBLIC_HOST=hydroflow.example.com
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https

CLOUDFLARE_TUNNEL_TOKEN=
```

Reverse proxy harus mempertahankan header `Host`, `X-Forwarded-Proto`, serta WebSocket Upgrade. Semua path dashboard diteruskan ke port `8080`; Nginx Hydroflow mengarahkan `/app/*` ke Reverb. Gateway menghubungi broker MQTT eksternal secara langsung.

Jangan mempublikasikan port `8080` atau database ke internet ketika reverse proxy digunakan.

### C. Cloudflare Tunnel

Isi token tunnel:

```env
APP_URL=https://hydroflow.example.com
APP_BIND_IP=127.0.0.1
APP_PORT=8080
SESSION_SECURE_COOKIE=true

REVERB_ALLOWED_ORIGINS=hydroflow.example.com
REVERB_PUBLIC_HOST=hydroflow.example.com
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https

CLOUDFLARE_TUNNEL_TOKEN=TOKEN_TUNNEL_CLIENT
```

Pada Cloudflare Tunnel, Public Hostname diarahkan ke:

```text
Type: HTTP
URL : http://nginx:80
```

Karena `cloudflared` berada dalam network Docker yang sama, origin-nya adalah `nginx:80`, bukan `localhost:8080`.

## 6. Hubungkan broker MQTT eksternal

Siapkan broker di layanan atau server terpisah. Isi `.env.docker` dengan endpoint MQTT/TLS yang dapat dijangkau dari container subscriber:

```env
MQTT_HOST=broker.example.com
MQTT_PORT=8883
MQTT_USERNAME=nama_pengguna_broker
MQTT_PASSWORD=HASIL_PASSWORD_MQTT
MQTT_TLS_ENABLED=true
```

Gateway memakai broker, kredensial, dan topic `devices/{device_sn}/telemetry` yang sama. Jika gateway memakai MQTT over WebSocket, gunakan endpoint WSS milik broker eksternal; path `/mqtt` pada domain dashboard tidak lagi tersedia. Pastikan firewall server mengizinkan koneksi keluar ke host dan port broker. Bila broker memakai CA private, atur `MQTT_TLS_CA_FILE` seperti dijelaskan pada bagian pengaturan MQTT.

Jika memperbarui instalasi lama yang memakai broker Docker bawaan, pindahkan gateway ke broker eksternal terlebih dahulu. Ubah `MQTT_HOST`, port, kredensial, dan TLS dalam `.env.docker`, lalu pastikan pengaturan web yang aktif juga menunjuk broker eksternal atau matikan **Use web configuration**. Setelah itu jalankan `./scripts/update.sh`; Compose menghentikan container broker lama melalui `--remove-orphans`. Volume data broker lama tidak dihapus otomatis.

## 7. Load image offline

```bash
chmod +x scripts/*.sh
./scripts/load-images.sh hydroflow-1.0.0-amd64-images.tar.gz
```

Loader akan:

1. Memastikan arsitektur bundle cocok dengan server.
2. Memverifikasi SHA-256.
3. Menjalankan `docker load`.

Periksa image:

```bash
docker image ls | grep -E 'hydroflow|postgres|cloudflared'
```

## 8. Validasi konfigurasi

```bash
docker compose --env-file .env.docker config --quiet
```

Pastikan file rahasia tidak dapat dibaca user lain:

```bash
ls -l .env.docker
```

Permission yang diharapkan:

```text
-rw-------
```

## 9. Jalankan instalasi

```bash
./scripts/install.sh
```

Installer akan memeriksa konfigurasi dan image, menyalakan PostgreSQL/Redis, menjalankan migration, kemudian menyalakan seluruh service. Mode offline tidak melakukan `docker pull`.

Periksa status:

```bash
docker compose --env-file .env.docker ps
```

Kondisi yang diharapkan:

```text
app        Up
telemetry-subscriber Up
scheduler  Up
postgres   Up (healthy)
reverb     Up (healthy)
nginx      Up (healthy)
cloudflared Up        # hanya jika token tunnel diisi
```

## 10. Uji penerimaan

### Buat administrator awal

User pertama yang didaftarkan pada instalasi baru otomatis menjadi administrator sistem. Lakukan registrasi akun pemilik sebelum dashboard dapat dijangkau publik. Pada database hasil upgrade, migration mempromosikan user dengan ID paling awal.

Administrator akan melihat menu **MQTT Broker** pada sidebar. Buka menu tersebut untuk memilih sumber konfigurasi broker eksternal:

- Biarkan **Use web configuration** mati bila Laravel memakai endpoint broker eksternal dari `.env.docker`.
- Aktifkan untuk mengelola hostname, port, username, password, dan TLS broker eksternal lewat dashboard.
- Klik **Test Connection** sebelum **Save Configuration**. Test tidak menyimpan perubahan.
- Setelah disimpan, jalankan `docker compose --env-file .env.docker restart telemetry-subscriber` agar koneksi penerima memakai broker baru.

Password MQTT disimpan terenkripsi menggunakan `APP_KEY`. Jangan mengganti `APP_KEY` setelah konfigurasi disimpan. Jika broker external memakai CA private, salin certificate ke:

```bash
cp mqtt-ca.crt certificates/mqtt-ca.crt
chmod 644 certificates/mqtt-ca.crt
```

Kemudian isi **Custom CA File** dengan `/run/secrets/hydroflow/mqtt-ca.crt`. Untuk certificate dari CA public, biarkan field tersebut kosong agar container memakai trust store sistem.

Uji origin lokal:

```bash
curl -i http://127.0.0.1:8080/up
curl -I http://127.0.0.1:8080
```

Hasil `/up` harus `200 OK`. Root aplikasi biasanya menghasilkan `302` menuju `/dashboard`.

Jika memakai domain:

```bash
curl -I https://hydroflow.example.com
```

Kemudian lakukan pengujian berikut dari browser:

- Halaman login tampil melalui HTTPS.
- Login menggunakan akun administrator awal.
- Menu MQTT Broker hanya terlihat oleh administrator dan Test Connection berhasil.
- Dashboard dapat dibuka.
- Developer Tools > Network > WS menunjukkan koneksi `/app/...` dengan status `101`.
- Telemetry test masuk dan nilai dashboard berubah tanpa refresh.
- Parameter power meter dan sensor motor tampil sesuai sumber gateway yang didaftarkan.
- Histori interval dan alarm ambang parameter dapat dibuka.
- Waktu telemetry sesuai timezone Asia/Jakarta.

## 11. Log dan diagnosis

```bash
docker compose --env-file .env.docker logs --tail=100 app
docker compose --env-file .env.docker logs --tail=100 nginx
docker compose --env-file .env.docker logs --tail=100 telemetry-subscriber
docker compose --env-file .env.docker logs --tail=100 reverb
docker compose --env-file .env.docker logs --tail=100 cloudflared
```

Masalah umum:

| Gejala | Pemeriksaan |
| --- | --- |
| Nginx `502` | Pastikan `app` dan `reverb` berstatus Up/healthy. |
| Image tidak ditemukan | Jalankan `load-images.sh` dan pastikan versi `.env.docker` cocok. |
| `no matching manifest` | Bundle tidak sesuai arsitektur server. |
| Telemetry tidak masuk | Periksa koneksi keluar ke broker eksternal, kredensial, TLS, topic, dan log `telemetry-subscriber`. |
| Domain `502` | Periksa target reverse proxy/tunnel dan origin lokal `/up`. |
| Realtime offline | Periksa `REVERB_PUBLIC_*`, allowed origins, Nginx, subscriber, dan Reverb. |
| Cookie/login berulang | Pastikan `SESSION_SECURE_COOKIE` sesuai HTTP atau HTTPS. |

## 12. Backup awal

Setelah penerimaan berhasil:

```bash
./scripts/backup.sh
ls -lh backups/
```

Salin backup ke penyimpanan di luar server. Uji prosedur restore pada server staging sebelum dijadikan prosedur operasional production.

## 13. Update offline

1. Buat backup:

   ```bash
   ./scripts/backup.sh
   ```

2. Salin bundle image versi baru ke folder deployment dan load:

   ```bash
   ./scripts/load-images.sh hydroflow-1.1.0-amd64-images.tar.gz
   ```

3. Ubah versi pada `.env.docker`:

   ```env
   HYDROFLOW_VERSION=1.1.0
   APP_VERSION=1.1.0
   ```

4. Jalankan update:

   ```bash
   ./scripts/update.sh
   ```

5. Ulangi checklist penerimaan dan jangan hapus backup sampai verifikasi selesai.

## 14. Rollback

Rollback kode tanpa rollback database belum tentu aman setelah migration. Gunakan bundle versi lama bersama backup yang dibuat sebelum update:

1. Pastikan image versi lama masih tersedia atau load ulang bundle lama.
2. Kembalikan `HYDROFLOW_VERSION` dan `APP_VERSION` ke versi lama.
3. Restore backup sebelum update:

   ```bash
   ./scripts/restore.sh backups/hydroflow-TIMESTAMP.dump
   ```

4. Periksa container, `/up`, login, telemetry dari broker eksternal, dan realtime.

## 15. Checklist serah-terima

- [ ] Arsitektur bundle cocok dengan server.
- [ ] Docker dan Compose tersedia.
- [ ] Disk, RAM, swap, NTP, dan timezone diperiksa.
- [ ] `.env.docker` permission `600` dan seluruh placeholder sudah diganti.
- [ ] Domain/IP dan metode akses dipilih dengan benar.
- [ ] Firewall membatasi dashboard dan mengizinkan koneksi keluar ke broker MQTT eksternal.
- [ ] Seluruh container berada pada kondisi yang diharapkan.
- [ ] Health endpoint menghasilkan `200`.
- [ ] Login, telemetry MQTT, realtime, histori, dan alarm parameter diuji.
- [ ] Backup awal dibuat dan disalin ke luar server.
- [ ] Password, token, dan dokumen akses diserahkan melalui kanal aman.
- [ ] Versi aplikasi dan tanggal instalasi dicatat pada dokumen aset IT.
