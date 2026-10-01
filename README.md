# Hydroflow System Monitoring

> **Penggunaan terbatas.** Hak atas bagian proprietary Hydroflow dimiliki Arnur Tech. Penerima wajib menjaga amanah penggunaan dan tidak menggandakan, memodifikasi, menyebarkan, atau menggunakannya untuk perangkat lain tanpa izin Arnur Tech. Lihat [Surat Pernyataan Amanah](legal/SURAT_PERNYATAAN_AMANAH_PENGGUNAAN_HYDROFLOW.md) dan [LICENSE.md](LICENSE.md).

Hydroflow System Monitoring adalah dashboard IoT berbasis Laravel untuk memantau kualitas, level, dan aliran air. Aplikasi menyimpan data telemetry, mengevaluasi alarm, menyediakan data historis, serta mengirim perintah valve ke device melalui MQTT.

## Fitur

- Dashboard status device: online, pending, dan offline.
- Monitoring pH, turbidity, sample flow, level air, tegangan, dan kekuatan sinyal.
- Penyimpanan histori pada `telemetry_raw` dan snapshot pada `telemetry_latest`.
- Kalibrasi tinggi/volume tangki dan pulse per liter.
- Alarm berdasarkan threshold dan tiga pembacaan anomali berturut-turut.
- Datalogger raw, hourly, daily, monthly, dan yearly.
- Filter berdasarkan device/tanggal serta export CSV.
- Berbagi akses device dengan role owner, operator, dan viewer.
- Kontrol valve melalui MQTT beserta acknowledgement dari telemetry.
- Dashboard dan detail device realtime melalui private WebSocket Laravel Reverb.

## Penerimaan telemetry melalui MQTT

### Monitoring multi-sumber (schema versi 2)

Untuk gateway pompa, buka halaman detail device lalu **Sumber data**. Buat lokasi produksi, aset pompa/motor dengan pilihan 1 atau 3 fasa, dan sumber data. Setiap sumber memakai `source_key` unik dalam satu gateway. Power meter otomatis mendapat parameter tegangan/arus per fasa, daya aktif, faktor daya, frekuensi, dan energi total; sumber lain dapat diberi parameter sendiri (misalnya `motor_temp`, `pressure`, `flow_rate`). Ambang alarm per parameter dapat diatur pada halaman yang sama. Viewer dapat melihat konfigurasi, sedangkan owner/operator dapat mengubahnya.

Gateway tetap publish ke `devices/{device_sn}/telemetry` dengan QoS 1. Untuk sumber yang sudah terdaftar, kirim payload berikut:

```json
{
  "schema_version": 2,
  "device_sn": "GW-001",
  "message_id": "GW-001-12345",
  "recorded_at": "2026-10-01T10:00:05+07:00",
  "sources": [
    {
      "source": "PM-01",
      "values": {"voltage_l1": 229.1, "current_l1": 12.4, "active_power": 2.35}
    },
    {
      "source": "TEMP-01",
      "values": {"motor_temp": 63.2},
      "quality": {"motor_temp": "good"}
    }
  ]
}
```

`source` dan seluruh kunci di `values` harus sudah terdaftar untuk gateway tersebut. Nilai harus berupa angka JSON. Jika pembacaan gagal, kirim `null` dengan kualitas `uncertain` atau `error`; kualitas `good` adalah default. Waktu pengukuran tidak boleh lebih dari 5 menit di masa depan. `message_id` digunakan untuk deduplikasi QoS 1. Pesan retained diabaikan.

Setiap pesan valid langsung memperbarui nilai di Redis dan mengirim event Reverb `parameters.updated` pada private channel `devices.{id}`. Dashboard detail menampilkan nilai terbaru tanpa menunggu database. Scheduler menyimpan satu baris `source_intervals` per sumber per interval (`MQTT_HISTORY_INTERVAL_SECONDS`, default 60 detik) dengan `sum`, `count`, `min`, `max`, nilai terakhir, dan kualitas untuk tiap parameter. Rata-rata historis dihitung dari `sum / count`. Alarm minimum/maksimum aktif setelah tiga pembacaan berkualitas baik berturut-turut di luar ambang; pembacaan baik yang pulih menyelesaikan alarm. Pesan schema lama tetap diproses melalui alur telemetry lama.

Jalankan migrasi terbaru, lalu jalankan `php artisan telemetry:subscribe` sebagai proses terpisah. Docker Compose menjalankannya melalui service `telemetry-subscriber`. Gateway mengirim JSON ke topic `devices/{device_sn}/telemetry` dengan QoS 1. Topic filter dan client ID subscriber dapat diatur lewat `MQTT_TELEMETRY_TOPIC` dan `MQTT_TELEMETRY_CLIENT_ID`.

```json
{
  "device_sn": "GW-001",
  "message_id": "GW-001-10482",
  "recorded_at": "2026-10-01T10:00:05+07:00",
  "ph": 7.2,
  "delta_pulse": 10
}
```

`device_sn` harus sama dengan serial pada topic dan sudah terdaftar. `message_id` wajib unik per device agar kiriman ulang tidak menggandakan telemetry atau total pulsa. Pesan retained diabaikan. Subscriber memvalidasi setiap pesan, memperbarui nilai terakhir di Redis, memeriksa alarm, lalu mengirim pembaruan Reverb. Data MQTT reguler dikumpulkan menjadi satu baris `telemetry_raw` per gateway per interval (default 60 detik). Baris ini berisi jumlah sampel, total pulsa, pemakaian, dan statistik min/maks/rata-rata di `raw_payload.metrics`. Pesan yang datang terlambat disimpan tersendiri dengan waktu pengukuran aslinya, lalu agregasi historis dihitung ulang melalui queue. Scheduler menjalankan `telemetry:flush-buckets` setiap menit untuk menutup interval meskipun gateway berhenti mengirim. Jalur HTTP lama masih menyimpan setiap pesan secara langsung.

Redis menggunakan AOF dan volume persisten pada deployment Docker. Jalankan migrasi sebelum mengaktifkan subscriber baru. Konfigurasi parameter listrik, pressure, flow produksi, dan temperatur motor belum termasuk tahap ini.

`MQTT_HISTORY_INTERVAL_SECONDS` mengatur ukuran interval histori, minimum 60 detik dan harus membagi satu jam tanpa sisa (misalnya 60, 120, atau 300). Nilai lain menggunakan 60 detik. `MQTT_LIVE_CACHE_STORE` harus menunjuk ke cache bersama yang mendukung lock; konfigurasi Docker menggunakan Redis. Jalankan satu subscriber aktif untuk client ID MQTT yang sama, dan pastikan scheduler serta queue worker berjalan. Hindari mengirim HTTP dan MQTT secara bersamaan untuk gateway yang sama selama migrasi.

Untuk penggunaan melalui jaringan publik, batasi hak publish tiap gateway pada topic miliknya di broker dan gunakan TLS. Endpoint HTTP lama tetap tersedia selama migrasi gateway.

## Teknologi

- PHP 8.3+
- Laravel 13 dan Laravel Breeze
- Blade, Tailwind CSS, dan Alpine.js
- Chart.js dan Vite
- Laravel Reverb dan Laravel Echo
- `php-mqtt/client`
- SQLite secara default

## Kebutuhan Sistem

- PHP 8.3 atau lebih baru
- Composer
- Node.js dan npm
- Database
- MQTT broker untuk fitur kontrol valve

## Instalasi

Clone repository, masuk ke direktori proyek, lalu jalankan:

```bash
composer run setup
```

Script tersebut menginstal dependency, membuat `.env` dan application key, menjalankan migration, lalu membangun aset frontend.

Jalankan environment development dengan:

```bash
composer run dev
```

Perintah tersebut menjalankan web server Laravel, queue listener, Vite, dan WebSocket Reverb. Komponen juga dapat dijalankan secara terpisah:

```bash
php artisan serve
npm run dev
php artisan queue:listen
php artisan reverb:start --debug
```

Laravel Pail tidak disertakan dalam `composer run dev` karena membutuhkan ekstensi `pcntl` yang tidak tersedia pada PHP Windows. Di PowerShell, log Laravel dapat dipantau terpisah dengan:

```powershell
Get-Content storage/logs/laravel.log -Wait -Tail 100
```

### Realtime lokal dengan Reverb

Realtime menggunakan private channel `devices.{deviceId}`. User hanya dapat berlangganan device yang terhubung melalui tabel `device_user`. Pada jalur MQTT, event dikirim setelah state live diperbarui di Redis; histori disimpan per interval. Pada jalur HTTP lama, event dikirim setelah telemetry tersimpan. Kegagalan broadcast dicatat tanpa membatalkan pengolahan data.

Untuk instalasi yang sudah ada, pasang dependency lalu pastikan database queue telah dimigrasikan:

```bash
composer install
npm install
php artisan migrate
php artisan optimize:clear
```

Konfigurasi lokal minimum di `.env`:

```dotenv
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=hydroflow-local
REVERB_APP_KEY=GANTI_DENGAN_KEY_ACAK
REVERB_APP_SECRET=GANTI_DENGAN_SECRET_ACAK
REVERB_HOST=127.0.0.1
REVERB_PORT=8081
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8081
REVERB_ALLOWED_ORIGINS=localhost,127.0.0.1,water-analyzer-dashboard.test

REVERB_PUBLIC_HOST=water-analyzer-dashboard.test
REVERB_PUBLIC_PORT=80
REVERB_PUBLIC_SCHEME=http
```

Nilai `REVERB_APP_KEY` dan `REVERB_APP_SECRET` harus konsisten antara Laravel, Reverb, dan hasil build Vite. `REVERB_PORT` adalah port yang dituju Laravel/browser, sedangkan `REVERB_SERVER_PORT` adalah port listener Reverb; untuk lokal keduanya harus sama. Port `8081` dipakai agar tidak berbenturan dengan Nginx Laragon yang sering memakai `8080`. Setelah mengubah variabel `VITE_*`, restart `npm run dev` atau jalankan ulang `npm run build`.
`REVERB_ALLOWED_ORIGINS` berisi hostname tanpa skema dan port. `REVERB_PUBLIC_*` merupakan endpoint yang dibuka browser dan dibaca saat runtime. Jika memakai virtual host Laragon lain, sesuaikan hostname tersebut.

Jalankan seluruh proses lokal dengan:

```bash
composer run dev
```

Atau gunakan terminal terpisah untuk `artisan serve`, `queue:work`, `npm run dev`, dan `reverb:start`. Queue wajib aktif karena event `TelemetryUpdated` menggunakan broadcasting queue.

Untuk menguji, buka dashboard atau detail device, lalu kirim payload ke `POST /api/v1/telemetry`. Browser akan menerima event `.telemetry.updated` tanpa reload. Pada DevTools browser, koneksi dapat diperiksa di **Network > WS**. Grafik periode 1H menerima titik terbaru secara langsung; periode agregasi lain tetap mengikuti hasil scheduler.

Jika private channel menghasilkan 403, pastikan user memiliki akses ke device dan session login masih aktif. Jika koneksi WebSocket gagal, periksa kesamaan key/host/port, allowed origin, proses Reverb, dan queue:

```bash
php artisan channel:list
php artisan queue:failed
php artisan reverb:start --debug
```

## Konfigurasi

Sesuaikan konfigurasi utama di `.env`:

```dotenv
APP_NAME="HYDROFLOW SYSTEM MONITORING"
APP_URL=http://localhost
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=sqlite

MQTT_HOST=192.168.0.20
MQTT_PORT=1883
MQTT_CLIENT_ID_PREFIX=hydroflow-dashboard
```

Untuk SQLite, pastikan `database/database.sqlite` tersedia. Untuk MySQL, gunakan konfigurasi seperti berikut:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=water_analyzer
DB_USERNAME=root
DB_PASSWORD=
```

Kemudian jalankan:

```bash
php artisan migrate
```

## Deployment Docker

Stack Docker production mencakup:

- `nginx`: web server pada port host `8080` secara default.
- `app`: Laravel PHP-FPM 8.4 dengan OPcache.
- `queue`: pemroses job MQTT untuk kontrol valve dan job Laravel lainnya.
- `telemetry-subscriber`: proses penerima telemetry dari broker MQTT.
- `reverb`: server WebSocket private untuk update dashboard dan detail device secara realtime.
- `scheduler`: menjalankan aggregation scheduler.
- `mysql`: MySQL 8.4 dengan persistent volume.
- `mqtt`: Eclipse Mosquitto dengan autentikasi, TCP MQTT, dan WebSocket.
- `cloudflared` (profile opsional `tunnel`): koneksi outbound menuju Cloudflare Tunnel tanpa membuka port dashboard pada router.

Data MySQL, storage Laravel, dan data Mosquitto disimpan pada named volume sehingga tetap tersedia ketika container dibuat ulang.

### Distribusi ke server client tanpa repository source

Deployment internal di atas tetap mendukung `build` dari repository untuk developer. Untuk instalasi client, gunakan paket [`deployment`](deployment/README.md) yang hanya menarik image versioned dari private registry dan tidak memiliki bagian `build`.

Developer menerbitkan release setelah login ke registry:

```bash
chmod +x scripts/publish-release.sh
./scripts/publish-release.sh ghcr.io/nama-organisasi 1.0.0
```

Perintah tersebut membangun dan mengirim tiga image:

```text
ghcr.io/nama-organisasi/hydroflow-app:1.0.0
ghcr.io/nama-organisasi/hydroflow-nginx:1.0.0
ghcr.io/nama-organisasi/hydroflow-mqtt:1.0.0
```

Salin hanya isi folder `deployment/` ke server client. Client mengisi `.env.docker`, login menggunakan token registry read-only, lalu menjalankan:

```bash
chmod +x scripts/*.sh
./scripts/install.sh
```

Update dilakukan dengan mengganti `HYDROFLOW_VERSION` dan `APP_VERSION`, lalu menjalankan `./scripts/update.sh`. Skrip update membuat backup database sebelum menarik image dan menjalankan migration. Prosedur backup/restore tersedia pada README paket deployment.

Setiap workflow release juga menghasilkan artifact offline `hydroflow-<versi>-amd64-offline`. Artifact tersebut berisi image aplikasi, Nginx, MQTT, MySQL, Cloudflared, file Compose, dan script instalasi. Setelah artifact diunduh dari tab **Actions**, client cukup memverifikasi checksum, menjalankan `load-images.sh`, lalu `install.sh`; login registry tidak diperlukan. Untuk membuat bundle secara lokal atau untuk target ARM64 gunakan:

```bash
chmod +x scripts/build-offline-bundle.sh
./scripts/build-offline-bundle.sh 1.0.0 linux/amd64
# atau
./scripts/build-offline-bundle.sh 1.0.0 linux/arm64
```

Hasil lokal disimpan di folder `dist/` sebagai file `.tar` beserta checksum SHA-256. Build lintas arsitektur memerlukan builder Buildx/QEMU yang mendukung platform target.

Private registry membatasi distribusi repository dan workflow pengembangan, tetapi administrator dengan akses root tetap dapat memeriksa filesystem image. Gunakan kontrak lisensi, akun registry terpisah per client, dan pembatasan akses server sebagai lapisan perlindungan tambahan.

### Persiapan server

Install Docker Engine dan Docker Compose plugin pada server, kemudian clone repository:

```bash
git clone <repository-url> water-analyzer-dashboard
cd water-analyzer-dashboard
cp .env.docker.example .env.docker
```

Edit `.env.docker` dan minimal ubah:

```env
APP_URL=https://water.example.internal
APP_KEY=base64:HASIL_KEY_RANDOM

DB_PASSWORD=PASSWORD_DATABASE_KUAT
MYSQL_PASSWORD=PASSWORD_DATABASE_KUAT
MYSQL_ROOT_PASSWORD=PASSWORD_ROOT_DATABASE_KUAT

MQTT_PASSWORD=PASSWORD_MQTT_KUAT
MQTT_BROKER_PASSWORD=PASSWORD_MQTT_KUAT

REVERB_APP_ID=hydroflow-production
REVERB_APP_KEY=KEY_REVERB_ACAK
REVERB_APP_SECRET=SECRET_REVERB_ACAK
REVERB_PUBLIC_HOST=water.example.internal
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https
```

`DB_PASSWORD` harus sama dengan `MYSQL_PASSWORD`, sedangkan `MQTT_PASSWORD` harus sama dengan `MQTT_BROKER_PASSWORD`. Generate application key pada server Linux:

```bash
echo "base64:$(openssl rand -base64 32)"
```

Salin hasilnya ke `APP_KEY`. Buat kredensial Reverb terpisah:

```bash
echo "REVERB_APP_KEY=$(openssl rand -hex 16)"
echo "REVERB_APP_SECRET=$(openssl rand -hex 32)"
```

App key Reverb dikirim ke browser saat runtime dan boleh diketahui client, tetapi `REVERB_APP_SECRET` harus tetap rahasia. File `.env.docker` sudah diabaikan Git dan tidak boleh dimasukkan ke repository. Image Docker tidak lagi terikat pada domain atau Reverb key tertentu.

### Build dan startup pertama

Gunakan `--env-file .env.docker` agar konfigurasi port Compose juga dibaca dari file production:

```bash
docker compose --env-file .env.docker build --pull
docker compose --env-file .env.docker up -d mysql mqtt
docker compose --env-file .env.docker ps mysql
```

Tunggu MySQL berstatus `healthy`, lalu jalankan migration tanpa menyalakan dependency lain:

```bash
docker compose --env-file .env.docker run --rm --no-deps app php artisan migrate --force
docker compose --env-file .env.docker up -d --no-deps app reverb
docker compose --env-file .env.docker up -d --no-deps nginx
curl -i http://127.0.0.1:8080/up
```

Jika health endpoint sudah menghasilkan `200 OK`, aktifkan service background secara bertahap:

```bash
docker compose --env-file .env.docker up -d --no-deps queue scheduler
```

Periksa kondisi container:

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f app queue reverb scheduler nginx mqtt
```

Status akhir yang diharapkan adalah MySQL, MQTT, Reverb, dan Nginx `healthy`; sedangkan app, queue, dan scheduler berstatus `Up`. Perintah `--no-deps` berguna ketika ingin menyalakan atau mendiagnosis satu service tanpa otomatis menjalankan service lain.

Aplikasi tersedia pada:

```text
http://IP-SERVER:8080
```

Port default yang digunakan:

| Layanan | Port host | Keterangan |
| --- | ---: | --- |
| HTTP dashboard | `8080` | Ubah melalui `APP_PORT`. |
| MQTT TCP | `1883` | Ubah melalui `MQTT_EXTERNAL_PORT`. |
| MQTT WebSocket | `9001` | Ubah melalui `MQTT_WEBSOCKET_PORT`. |

MySQL dan Reverb tidak dipublikasikan ke host. Browser mengakses Reverb melalui path `/app/*` pada Nginx, sedangkan queue Laravel menghubungi hostname internal `reverb:8080`.

`APP_BIND_IP` dan `MQTT_BIND_IP` menentukan interface host yang menerima koneksi. Gunakan `127.0.0.1` untuk layanan yang hanya boleh diakses dari host/tunnel, atau `0.0.0.0` jika dashboard/device perlu mengaksesnya melalui LAN.

### Deployment demo di STB dengan Cloudflare Tunnel

Pastikan STB menjalankan Linux 64-bit dengan Docker Engine dan Compose plugin. Image stack mendukung host `amd64` maupun `arm64`; periksa arsitektur dengan `uname -m`. Untuk STB dengan RAM terbatas, siapkan swap dan jangan menjalankan proses build bersamaan dengan aplikasi lain yang berat.

Di Cloudflare Zero Trust, buka **Networks > Tunnels**, buat Cloudflared tunnel, lalu tambahkan Public Hostname, misalnya `water.example.com`, dengan service:

```text
Type : HTTP
URL  : nginx:80
```

Nama `nginx` adalah nama service di network Docker, bukan `localhost`. Salin token tunnel dari langkah instalasi connector, lalu atur `.env.docker`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://water.example.com
APP_BIND_IP=127.0.0.1
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=hydroflow-production
REVERB_APP_KEY=KEY_REVERB_ACAK
REVERB_APP_SECRET=SECRET_REVERB_ACAK
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=water.example.com

REVERB_PUBLIC_HOST=water.example.com
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https

CLOUDFLARE_TUNNEL_TOKEN=TOKEN_DARI_CLOUDFLARE
```

`REVERB_ALLOWED_ORIGINS` menggunakan hostname tanpa `https://` dan tanpa port. Nilai `REVERB_HOST=reverb` ditujukan untuk komunikasi internal container, sedangkan `REVERB_PUBLIC_*` adalah endpoint publik yang dibuka browser. Nilai publik dibaca saat runtime sehingga perubahan domain tidak memerlukan build image ulang.

Build dan jalankan stack beserta profile tunnel:

```bash
docker compose --env-file .env.docker build --pull
docker compose --env-file .env.docker up -d mysql mqtt
docker compose --env-file .env.docker run --rm app php artisan migrate --force
docker compose --env-file .env.docker --profile tunnel up -d
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f cloudflared nginx app reverb queue
```

Cloudflared membuat koneksi outbound, sehingga port `8080` tidak perlu diteruskan pada router. Koneksi WebSocket memakai hostname Cloudflare yang sama dan diteruskan Nginx ke Reverb melalui `/app/*`; tidak diperlukan Public Hostname atau port Reverb terpisah. Dengan `APP_BIND_IP=127.0.0.1`, dashboard juga tidak terbuka langsung ke LAN; akses publik hanya melalui hostname Cloudflare. Aktifkan Cloudflare Access bila demo harus dibatasi ke email tertentu.

MQTT over WebSocket Secure tersedia melalui hostname yang sama pada endpoint
`wss://<domain-dashboard>/mqtt` port `443`. Cloudflare menangani TLS, kemudian
Nginx meneruskan WebSocket secara internal ke Mosquitto port `9001`. MQTT client
harus mengirim subprotocol `mqtt` dan tetap menggunakan nilai
`MQTT_BROKER_USERNAME` serta `MQTT_BROKER_PASSWORD`. ESP32 dengan ESP-MQTT atau
PsychicMqttClient dapat menggunakan endpoint ini tanpa membuka port MQTT pada
router.

Cloudflare Tunnel HTTP biasa **tidak meneruskan MQTT TCP port 1883**. Untuk demo paling sederhana, device yang berada di LAN STB tetap memakai `IP-STB:1883` dan set `MQTT_BIND_IP=0.0.0.0`. Jangan membuka port 1883 ke internet. Device dari luar LAN sebaiknya masuk melalui VPN seperti WireGuard/Tailscale, atau gunakan broker MQTT publik/managed yang mendukung TLS. MQTT TCP publik melalui Cloudflare memerlukan produk/protokol yang sesuai dan bukan konfigurasi Public Hostname HTTP di atas.

Setelah reboot, Docker akan menghidupkan kembali container karena seluruh service memakai `restart: unless-stopped`. Profile tunnel tetap tersimpan pada container yang telah dibuat. Untuk update berikutnya gunakan profile yang sama:

```bash
git pull
docker compose --env-file .env.docker build --pull
docker compose --env-file .env.docker run --rm app php artisan migrate --force
docker compose --env-file .env.docker --profile tunnel up -d --remove-orphans
```

Jaga token Cloudflare dan password hanya di `.env.docker`. Jika token sempat terlihat di log/chat/repository, rotasi tunnel token dari dashboard Cloudflare.

### Verifikasi realtime production

Pastikan Reverb sehat dan queue tidak memiliki job broadcast yang gagal:

```bash
docker compose --env-file .env.docker ps reverb queue nginx
docker compose --env-file .env.docker logs --tail=100 reverb queue nginx
docker compose --env-file .env.docker exec app php artisan queue:failed
```

Buka dashboard melalui domain, lalu periksa **Developer Tools > Network > WS**. Koneksi `/app/{REVERB_APP_KEY}` harus menghasilkan status `101 Switching Protocols`. Setelah API telemetry menerima payload, hasil yang diharapkan adalah:

```text
queue   : App\Events\TelemetryUpdated ... DONE
reverb  : container berstatus healthy tanpa restart/error berulang
browser : indikator Realtime aktif dan nilai berubah tanpa reload
```

Jika WebSocket gagal tetapi halaman tetap dapat dibuka, periksa `REVERB_APP_KEY`, `REVERB_PUBLIC_HOST`, hostname pada `REVERB_ALLOWED_ORIGINS`, serta log Nginx/Reverb. Jika queue menampilkan job gagal setelah konfigurasi diperbaiki, retry dengan:

```bash
docker compose --env-file .env.docker exec app php artisan queue:retry all
```

### Upgrade STB lama ke versi realtime

File `.env.docker` tidak diperbarui oleh `git pull`. Sebelum build pertama versi realtime, buka file tersebut dan tambahkan seluruh variabel Reverb. Untuk domain demo yang sudah digunakan sebelumnya, bentuknya seperti:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=hydroflow-production
REVERB_APP_KEY=HASIL_OPENSSL_16_BYTE
REVERB_APP_SECRET=HASIL_OPENSSL_32_BYTE
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=hydroflow.arnur.id

REVERB_PUBLIC_HOST=hydroflow.arnur.id
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https
```

Generate key dan secret dengan `openssl rand -hex 16` serta `openssl rand -hex 32`. Setelah `.env.docker` selesai diperbarui, jalankan:

```bash
git pull
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker build --pull app nginx
docker compose --env-file .env.docker run --rm app php artisan migrate --force
docker compose --env-file .env.docker up -d --no-deps --force-recreate app queue reverb scheduler
docker compose --env-file .env.docker up -d --no-deps --force-recreate nginx
docker compose --env-file .env.docker ps
```

Tidak perlu menghapus atau membuat ulang volume. MySQL, telemetry, user, dan data MQTT yang lama tetap dipertahankan.

### Konfigurasi device MQTT

Device fisik menggunakan alamat server, port external MQTT, dan kredensial broker:

```text
Host     : IP atau domain server
Port     : 1883
Username : nilai MQTT_BROKER_USERNAME
Password : nilai MQTT_BROKER_PASSWORD
Topic    : devices/{device_sn}/commands/drain-valve
QoS      : 1
```

Laravel menggunakan hostname internal `mqtt:1883`; jangan mengganti `MQTT_HOST=mqtt` menjadi IP publik ketika semua service berjalan dalam Compose.

Konfigurasi bawaan membuka MQTT tanpa TLS karena ditujukan untuk private server/network. Batasi port `1883` dan `9001` menggunakan firewall atau VPN. Untuk akses internet publik, pasang TLS pada reverse proxy/dashboard dan listener TLS Mosquitto sebelum menghubungkan perangkat produksi.

### HTTPS

Container Nginx menerima HTTP internal. Tempatkan Caddy, Traefik, Nginx Proxy Manager, atau reverse proxy milik server di depannya untuk terminasi HTTPS, kemudian arahkan proxy ke `127.0.0.1:8080` dan ubah:

```env
APP_URL=https://domain-dashboard
SESSION_SECURE_COOKIE=true
REVERB_ALLOWED_ORIGINS=domain-dashboard
REVERB_PUBLIC_HOST=domain-dashboard
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https
```

Setelah mengubah environment Reverb, buat ulang container agar semua proses membaca konfigurasi baru:

```bash
docker compose --env-file .env.docker build app nginx
docker compose --env-file .env.docker up -d --force-recreate app queue reverb scheduler nginx
```

### Membuat user dan data demo

Untuk server kosong, registrasikan user melalui aplikasi. Dataset demo untuk dokumentasi bersifat opsional:

```bash
docker compose --env-file .env.docker exec app php artisan db:seed --force
```

Jangan menjalankan DemoDataSeeder pada production apabila device dengan serial demo tidak diperlukan.

Setelah mengisi dataset demo, bangun data agregasi historis:

```bash
docker compose --env-file .env.docker exec app php artisan aggregate:all
```

Seeder sebaiknya hanya dijalankan sekali kecuali implementasi seeder telah dipastikan idempotent.

### Verifikasi queue, Reverb, scheduler, dan MQTT

Pastikan jadwal aggregation dikenali Laravel:

```bash
docker compose --env-file .env.docker exec scheduler php artisan schedule:list
docker compose --env-file .env.docker exec app php artisan queue:failed
docker compose --env-file .env.docker ps reverb
```

Tes broker dari dalam container tanpa mengekspos password ke output:

```bash
docker compose --env-file .env.docker exec mqtt sh -lc '
mosquitto_sub -h 127.0.0.1 -p 1883 -u "$MQTT_BROKER_USERNAME" -P "$MQTT_BROKER_PASSWORD" -t "hydroflow/test" -C 1 -W 5 > /tmp/hydroflow-test.txt &
subscriber_pid=$!
sleep 1
mosquitto_pub -h 127.0.0.1 -p 1883 -u "$MQTT_BROKER_USERNAME" -P "$MQTT_BROKER_PASSWORD" -t "hydroflow/test" -m "mqtt-ok" -q 1
wait "$subscriber_pid"
cat /tmp/hydroflow-test.txt
'
```

Output terakhir harus `mqtt-ok`. Untuk memantau publish kontrol valve:

```bash
docker compose --env-file .env.docker logs -f queue mqtt
```

### Troubleshooting deployment

**Endpoint `/up` menghasilkan 500**

Lihat log aplikasi karena production menggunakan output `stderr`:

```bash
docker compose --env-file .env.docker logs --tail=150 app
```

Konfigurasi Nginx menyediakan fallback untuk `X-Forwarded-Host` dan `X-Forwarded-Proto`. Jangan meneruskan kedua header sebagai string kosong karena trusted proxy akan membuat Symfony gagal membentuk URL request.

**Halaman terbuka tetapi tidak memiliki CSS**

Build frontend dijalankan otomatis oleh Dockerfile. Pastikan CSS hasil Tailwind berukuran puluhan KB, bukan hanya sekitar 57 byte:

```bash
docker compose --env-file .env.docker exec nginx sh -lc 'ls -lh /var/www/html/public/build/assets/*.css'
```

Dockerfile harus menyalin `vite.config.js`, `postcss.config.js`, dan `tailwind.config.js` sebelum `npm run build`. Setelah memperbaiki konfigurasi frontend, build dan buat ulang app serta Nginx bersama-sama agar manifest dan file asset memakai hash yang sama:

```bash
docker compose --env-file .env.docker build app nginx
docker compose --env-file .env.docker up -d --no-deps --force-recreate app nginx
```

Periksa asset melalui domain dan lakukan hard refresh browser apabila hasilnya sudah `200` dengan `Content-Type: text/css`:

```bash
curl -I https://domain-dashboard/build/assets/NAMA-ASSET.css
```

**Perintah Compose tergabung ketika ditempel ke terminal**

Jalankan perintah satu per satu dan tunggu prompt kembali. Hindari menyalin karakter lanjutan baris `\` jika terminal atau chat menggabungkan baris.

### Deployment update aplikasi

```bash
git pull
docker compose --env-file .env.docker build --pull
docker compose --env-file .env.docker run --rm app php artisan migrate --force
docker compose --env-file .env.docker --profile tunnel up -d --remove-orphans
```

Image baru akan digunakan oleh web, queue, Reverb, dan scheduler. Named volume tidak dihapus. Jangan menjalankan `docker compose down -v` kecuali memang ingin menghapus seluruh database, storage, dan data MQTT.

### Backup dan restore MySQL

Backup:

```bash
docker compose --env-file .env.docker exec -T mysql sh -c \
  'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  > hydroflow-backup.sql
```

Restore ke database kosong atau database yang memang ingin ditimpa:

```bash
docker compose --env-file .env.docker exec -T mysql sh -c \
  'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  < hydroflow-backup.sql
```

Backup file upload/storage dapat dibuat dari volume `app_storage`. Simpan backup di luar host Docker jika memungkinkan.

### Command operasional

```bash
# Menjalankan migration
docker compose --env-file .env.docker exec app php artisan migrate --force

# Melihat job yang gagal
docker compose --env-file .env.docker exec app php artisan queue:failed

# Retry semua job gagal
docker compose --env-file .env.docker exec app php artisan queue:retry all

# Rebuild seluruh aggregation
docker compose --env-file .env.docker exec app php artisan aggregate:all

# Mengikuti log
docker compose --env-file .env.docker logs -f --tail=200
```

## API Telemetry

```http
POST /api/v1/telemetry
Content-Type: application/json
```

Contoh payload:

```json
{
  "device_sn": "WATER-001",
  "ph": 7.12,
  "turbidity": 4.5,
  "delta_pulse": 25,
  "sample_flow": 0.75,
  "level_distance_cm": 80,
  "device_voltage_mcu": 3.3,
  "device_voltage_5v": 5.0,
  "device_voltage_input": 12.1,
  "signal_strength": -67,
  "alarm_code": null,
  "valve_state": true,
  "recorded_at": "2026-08-23T10:30:00+07:00"
}
```

Hanya `device_sn` yang wajib dikirim. `recorded_at` akan menggunakan waktu server jika dikosongkan dan field sensor dapat dikirim secara parsial. Endpoint memverifikasi perangkat terdaftar melalui `device_sn`, sesuai penggunaan pada jaringan internal perusahaan.

`level_distance_cm` adalah jarak mentah `d` dari muka sensor ultrasonik ke permukaan air. Konfigurasi `tank_height_cm` adalah jarak acuan `H₀` dari muka sensor ke dasar bagian dalam tandon, bukan batas level 100%. Backend menghitung:

```text
water_height_cm = clamp(H₀ - d, 0, H₀)
level_percent   = water_height_cm / H₀ × 100
```

Sebagai transisi, field lama `level_cm` pada payload masih diterima dan diperlakukan sebagai jarak sensor, tetapi firmware baru disarankan memakai `level_distance_cm`. Jangan mengirim kedua field tersebut bersamaan. Pada database, `level_distance_cm` menyimpan pembacaan mentah sedangkan `level_cm` menyimpan tinggi air hasil konversi.

`quality_status` dihitung oleh backend dari pH dan turbidity berdasarkan threshold device. Device tidak perlu menentukan status kualitas sendiri. Payload parsial, termasuk acknowledgement valve, mempertahankan nilai sensor terakhir yang tidak ikut dikirim.

### Status kualitas air

Status setiap parameter ditentukan sebagai berikut:

| Parameter | Good | Warning / perlu perhatian | Bad |
| --- | --- | --- | --- |
| pH | `ph_min <= nilai <= ph_max` | Hingga `0.5` di bawah `ph_min` atau hingga `0.5` di atas `ph_max` | Lebih dari `0.5` di luar batas minimum/maksimum |
| Turbidity | `nilai <= turbidity_max` | Di atas `turbidity_max` sampai dengan `50 NTU` | Di atas `50 NTU` |

Nilai parameter yang tidak dikirim dan belum mempunyai data terakhir diberi status `unknown`. Status kualitas gabungan menggunakan prioritas berikut:

1. `bad`: salah satu parameter bad, atau pH dan turbidity keduanya warning.
2. `unknown`: tidak ada parameter bad, tetapi pH atau turbidity belum tersedia.
3. `warning`: tepat satu parameter warning dan parameter lainnya good.
4. `good`: pH dan turbidity keduanya good.

Contoh apabila threshold pH adalah `6.5-8.5` dan `turbidity_max` adalah `5 NTU`:

| pH | Turbidity | Hasil |
| ---: | ---: | --- |
| `7.0` (good) | `3` (good) | `good` |
| `6.3` (warning) | `3` (good) | `warning` |
| `6.3` (warning) | `10` (warning) | `bad` |
| `5.8` (bad) | `3` (good) | `bad` |

`delta_pulse` merupakan pulse pemakaian air pada output tandon. Backend mengonversinya menjadi `usage_liter` menggunakan nilai kalibrasi `pulse_per_liter` yang aktif. `sample_flow` merupakan debit aliran sample menuju chamber dalam liter/menit. Field lama `flow` dan `flow_total` tidak digunakan lagi dan akan ditolak oleh validasi API.

Setiap telemetry raw menyimpan `pulse_per_liter_used` dan `usage_liter`. Dengan demikian, histori volume tidak berubah ketika nilai kalibrasi device diperbarui.

Alur pemrosesan telemetry:

```text
Device
  -> validasi payload dan device_sn
  -> hitung tinggi air = tank_height_cm - level_distance_cm
  -> hitung level_percent dari tinggi air / tank_height_cm
  -> simpan ke telemetry_raw
  -> perbarui telemetry_latest dan total pulse
  -> evaluasi alarm
  -> periksa acknowledgement command valve
```

Contoh respons berhasil:

```json
{
  "success": true,
  "message": "Telemetry berhasil disimpan",
  "data": {
    "id": 1,
    "device_id": 1,
    "device_sn": "WATER-001",
    "recorded_at": "2026-08-23T03:30:00.000000Z",
    "usage_liter": 0.055556,
    "usage_month_liter": 1250.755556,
    "level_distance_cm": 80,
    "water_height_cm": 120,
    "level_percent": 60,
    "quality_status": "good",
    "parameter_health": {}
  }
}
```

`usage_month_liter` merupakan akumulasi pemakaian device tersebut sejak awal bulan menurut timezone pada Device Settings sampai saat response dibuat.

## MQTT dan Kontrol Valve

Kontrol drain valve menggunakan MQTT untuk jalur command dari Laravel menuju device. Feedback eksekusi dikirim kembali melalui endpoint API telemetry.

```text
Dashboard
  -> simpan device_commands (queued)
  -> queue worker
  -> publish MQTT (sent)
  -> device mengeksekusi valve
  -> device POST telemetry + command_id
  -> command menjadi ack
  -> dashboard memperbarui toggle
```

### Konfigurasi broker

Konfigurasi MQTT memiliki dua sumber:

1. **Environment server** melalui `.env` dan `config/mqtt.php` sebagai konfigurasi awal/fallback.
2. **Web configuration** pada menu **MQTT Broker** sebagai override global untuk seluruh command perangkat.

Menu MQTT Broker hanya dapat dibuka oleh user dengan status administrator. User pertama pada instalasi baru menjadi administrator; ketika migration dijalankan pada database lama, user dengan ID paling awal dipromosikan menjadi administrator. Buat akun pertama sebelum dashboard dibuka ke jaringan publik.

Pada halaman tersebut administrator dapat memilih broker internal Docker atau broker external/server terpisah, mengatur host, port, autentikasi, TLS, timeout, dan melakukan **Test Connection**. Pengujian koneksi tidak menyimpan perubahan. Password broker disimpan terenkripsi menggunakan `APP_KEY`, tidak pernah ditampilkan kembali, dan input password yang dikosongkan akan mempertahankan nilai lama.

Setelah **Save Configuration**, queue worker diberi sinyal restart agar command berikutnya memakai konfigurasi baru. Jika **Use web configuration** dimatikan, aplikasi kembali memakai nilai environment. Contoh fallback `.env` untuk broker TCP tanpa autentikasi:

```env
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_CLIENT_ID_PREFIX=hydroflow-dashboard
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_CLEAN_SESSION=true
MQTT_CONNECT_TIMEOUT=5
MQTT_SOCKET_TIMEOUT=5
MQTT_KEEP_ALIVE=60
MQTT_VALVE_ACK_TIMEOUT=15
MQTT_VALVE_QUEUE_TIMEOUT=30

MQTT_TLS_ENABLED=false
MQTT_TLS_VERIFY_PEER=true
MQTT_TLS_VERIFY_PEER_NAME=true
MQTT_TLS_ALLOW_SELF_SIGNED=false
MQTT_TLS_CA_FILE=
MQTT_TLS_CLIENT_CERTIFICATE_FILE=
MQTT_TLS_CLIENT_CERTIFICATE_KEY_FILE=
MQTT_TLS_CLIENT_CERTIFICATE_KEY_PASSPHRASE=

MQTT_LAST_WILL_TOPIC=dashboard/status
MQTT_LAST_WILL_MESSAGE=offline
MQTT_LAST_WILL_QOS=1
MQTT_LAST_WILL_RETAIN=false
```

Broker external dengan TLS umumnya menggunakan port `8883`. Pertahankan verifikasi peer dan hostname. Untuk CA public, kosongkan custom CA agar trust store sistem digunakan. Jika broker memakai CA private, simpan certificate sebagai `docker/certificates/mqtt-ca.crt` pada development atau `deployment/certificates/mqtt-ca.crt` pada paket deployment, kemudian isi **Custom CA File** di UI dengan:

```text
/run/secrets/hydroflow/mqtt-ca.crt
```

Direktori certificate dimount read-only ke container. Jangan memasukkan private key ke repository atau bundle distribusi. `MQTT_VALVE_ACK_TIMEOUT`, `MQTT_VALVE_QUEUE_TIMEOUT`, client certificate/mTLS, dan Last Will tetap dikelola melalui environment karena merupakan konfigurasi tingkat server.

Client ID diberi suffix acak pada setiap koneksi agar beberapa queue worker tidak menggunakan ID yang sama. Username, password, dan path certificate yang kosong akan diperlakukan sebagai `null`. Jika konfigurasi hanya dikelola dari `.env`, muat ulang konfigurasi dan worker setelah perubahan:

```bash
php artisan config:clear
php artisan queue:restart
php artisan queue:work --tries=3
```

### Command drain valve

Perintah dipublikasikan dengan QoS 1 dan `retain=false` ke topic:

```text
devices/{device_sn}/commands/drain-valve
```

Contoh payload MQTT:

```json
{
  "device_sn": "WATER-001",
  "command": "set_drain_valve",
  "value": 1,
  "command_id": 10,
  "issued_at": "2026-08-23T10:30:00+07:00",
  "expires_at": "2026-08-23T10:30:15+07:00"
}
```

Nilai `value=1` berarti valve dibuka dan `value=0` berarti valve ditutup. Batas waktu ACK (default 15 detik) mulai dihitung ketika job akan memublikasikan command, bukan ketika command masih menunggu antrean. Command yang tidak diproses queue dalam 30 detik akan kedaluwarsa. Kedua durasi dapat diatur melalui `MQTT_VALVE_ACK_TIMEOUT` dan `MQTT_VALVE_QUEUE_TIMEOUT`. Karena QoS 1 dapat mengirim message lebih dari sekali, firmware harus memperlakukan `command_id` secara idempotent: ID yang sudah dieksekusi tidak boleh menggerakkan valve untuk kedua kalinya.

### Acknowledgement device

Setelah posisi valve benar-benar berubah, device wajib mengirim `command_id` yang diterimanya bersama kondisi aktual melalui API:

```http
POST /api/v1/telemetry
Content-Type: application/json
```

```json
{
  "device_sn": "WATER-001",
  "valve_state": true,
  "command_id": 10
}
```

Acknowledgement diterima hanya jika:

- `device_sn` mengarah ke device pemilik command.
- `command_id` sama dengan ID pada payload MQTT.
- `valve_state` sama dengan `value` yang diminta.
- Command masih berstatus `queued` atau `sent` dan belum kedaluwarsa.

Payload ACK dapat dikirim bersama telemetry sensor reguler. Payload parsial yang hanya berisi `valve_state` dan `command_id` tidak menghapus nilai sensor terakhir.

Subscriber MQTT menerima feedback valve jika `valve_state` dan `command_id` dikirim bersama telemetry ke `devices/{device_sn}/telemetry`. Topic ACK terpisah belum didukung.

### Status dan feedback dashboard

| Status | Arti |
| --- | --- |
| `queued` | Command tersimpan dan menunggu queue worker. |
| `sent` | Publish MQTT berhasil. |
| `ack` | Device melaporkan posisi aktual yang sesuai. |
| `failed` | Publish gagal setelah maksimal tiga percobaan. |
| `expired` | Tidak ada ACK yang valid dalam 15 detik. |

Dashboard memeriksa status setiap 750 ms. Selama proses, toggle dinonaktifkan dan footer menampilkan `Opening...` atau `Closing...`. Setelah ACK, footer menampilkan `Valve opened` atau `Valve closed`. Jika gagal atau kedaluwarsa, toggle dikembalikan ke kondisi aktual terakhir.

### Pengujian dengan public broker

Untuk pengembangan dapat digunakan HiveMQ public broker:

```env
MQTT_HOST=broker.hivemq.com
MQTT_PORT=1883
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_TLS_ENABLED=false
```

HiveMQ WebSocket Client dapat subscribe sebagai simulator device ke `devices/{device_sn}/commands/drain-valve`. Setelah menerima command, simulasikan ACK dengan melakukan POST ke API telemetry. Gunakan serial number dan topic yang unik karena public broker dapat diakses siapa pun dan tidak boleh digunakan untuk data sensitif atau production.

## Alarm

Threshold dapat dikonfigurasi per device untuk pH, turbidity, sample flow, level air, serta tegangan MCU, 5V, dan input. `sample_flow` menggunakan satuan liter/menit dan hanya merepresentasikan aliran sample menuju chamber. Alarm dibuat setelah tiga data terakhir berada di luar threshold. Satu pembacaan normal akan mengubah alarm aktif atau acknowledged menjadi resolved.

## Agregasi Data

Data diringkas melalui alur berikut:

```text
telemetry_raw
  -> telemetry_hourly
  -> telemetry_daily
  -> telemetry_monthly
  -> telemetry_yearly
```

Agregasi sample flow disimpan sebagai `avg_sample_flow`, `min_sample_flow`, dan `max_sample_flow`. Kolom `usage_liter` pada tabel hourly, daily, monthly, dan yearly merupakan jumlah volume pemakaian pada periode tersebut. Kolom total flow lama tidak digunakan karena pemakaian air dihitung dari `delta_pulse / pulse_per_liter`.

Chart Water Usage pada detail device menggunakan bucket 5 menit dari telemetry raw untuk periode 1H. Periode 7H dan 24H menggunakan agregasi hourly, sedangkan 7D dan 30D menggunakan agregasi daily. Bucket jam atau hari yang sedang berjalan dihitung sementara dari telemetry raw agar grafik tetap aktual tanpa menunggu scheduler menutup periode.

Command yang tersedia:

```bash
php artisan aggregate:hourly
php artisan aggregate:daily
php artisan aggregate:monthly
php artisan aggregate:yearly
```

Untuk membangun ulang seluruh agregasi yang tersedia di database secara berurutan
dari data raw hingga yearly (termasuk periode yang sedang berjalan), jalankan:

```bash
php artisan aggregate:all
```

Command ini aman dijalankan ulang karena setiap periode disimpan menggunakan
`updateOrInsert`. Command terjadwal di atas tetap hanya memproses periode sebelumnya.

Command memproses periode sebelumnya dan menggunakan `updateOrInsert`, sehingga dapat dijalankan kembali untuk periode yang sama. Scheduler telah didaftarkan dengan jadwal berikut:

- Hourly: setiap awal jam.
- Daily: setiap hari pukul 00:10.
- Monthly: tanggal 1 pukul 00:20.
- Yearly: 1 Januari pukul 00:30.

Pastikan `php artisan schedule:run` dipanggil setiap menit oleh task scheduler pada server deployment.

## Pengujian

### Dataset demo untuk dokumentasi

Seeder demo menghasilkan tiga device dengan raw telemetry aktual dari waktu seed hingga maksimal 30 hari ke belakang. Data lama milik serial `DEMO-DEVICE-001` sampai `DEMO-DEVICE-003` dibersihkan sebelum dibuat ulang sehingga command aman dijalankan berulang.

Dataset mencakup:

- Data per jam untuk histori 30 hari.
- Data per 30 menit untuk tujuh jam terakhir.
- Data per menit untuk satu jam terakhir.
- pH, turbidity, sample flow, level, tegangan, sinyal, pulse, volume penggunaan, dan state valve.
- Pola penggunaan air berdasarkan jam sibuk, malam hari, dan akhir pekan agar bar chart mudah dibaca.
- `telemetry_latest` dan agregasi hourly, daily, monthly, serta yearly.
- Device normal, device bermasalah, device campuran, serta alarm aktif/resolved.

Untuk membuat ulang dataset berdasarkan waktu sekarang:

```bash
php artisan db:seed --class=DemoDataSeeder
```

Jika database masih kosong, jalankan seluruh seeder agar akun demo ikut dibuat:

```bash
php artisan db:seed
```

Akun demo menggunakan `owner@example.com` atau `editor@example.com` dengan password `password`. Akun editor mendapat role operator pada Demo Water Tank A.

```bash
composer test
```

Atau:

```bash
php artisan test
```

## Struktur Utama

```text
app/
  Console/Commands/       Command agregasi telemetry
  Http/Controllers/       Dashboard, device, alarm, dan datalogger
  Http/Controllers/Api/   Endpoint penerimaan telemetry
  Models/                 Device, telemetry, alarm, dan command
  Services/               Evaluasi kesehatan, alarm, dan MQTT
database/migrations/      Skema database
resources/views/          Tampilan Blade
routes/                   Route web, API, auth, dan console
tests/                    Automated tests
```

## Catatan Pengembangan

- Halaman web dilindungi middleware autentikasi.
- Owner dan operator dapat mengubah device, setting, serta mengoperasikan valve; viewer hanya memiliki akses baca.
- Terapkan otorisasi pada setiap fitur device baru.
- Endpoint telemetry berbasis `device_sn` ditujukan untuk jaringan internal; tambahkan kredensial device jika nantinya dibuka ke jaringan publik.

## Lisensi

Proyek ini dibangun di atas Laravel yang menggunakan lisensi MIT. Kebijakan lisensi kode aplikasi ditentukan oleh pemilik repository.
