# Pump Monitoring

Dashboard untuk memantau gateway IoT, pompa, motor, dan sensor di lokasi produksi yang jauh dari kantor. Satu device mewakili satu gateway. Setiap gateway dapat memiliki beberapa sumber data, misalnya power meter RS485, sensor debit, tekanan, dan temperatur. Aset pompa dapat dikonfigurasi untuk satu atau tiga fasa.

> Penggunaan dan distribusi proyek mengikuti [LICENSE.md](LICENSE.md) dan [Surat Pernyataan Amanah](legal/SURAT_PERNYATAAN_AMANAH_PENGGUNAAN_HYDROFLOW.md).

## Alur data

Gateway mengirim JSON ke topic `devices/{device_sn}/telemetry` melalui MQTT. Subscriber memvalidasi identitas gateway, sumber, dan parameter yang telah didaftarkan. Setiap pesan valid memperbarui cache Redis dan dikirim ke dashboard melalui Laravel Reverb tanpa menunggu penulisan histori. Scheduler menyimpan ringkasan per sumber data ke `source_intervals` sesuai `MQTT_HISTORY_INTERVAL_SECONDS` (default 60 detik). Alarm ambang minimum atau maksimum aktif setelah tiga pembacaan berkualitas baik berturut-turut di luar batas; pembacaan normal menyelesaikan alarm.

Contoh payload gateway:

```json
{
  "schema_version": 2,
  "device_sn": "GW-SUMUR-01",
  "message_id": "GW-SUMUR-01-000123",
  "recorded_at": "2026-10-01T10:00:00+07:00",
  "sources": [
    {"source": "PM-01", "values": {"voltage_l1": 228.4, "current_l1": 12.8, "active_power": 5.2}},
    {"source": "MOTOR-01", "values": {"motor_temp": 61.5, "pressure": 3.7, "flow_rate": 21.4}}
  ]
}
```

`source` dan nama parameter harus sesuai konfigurasi gateway di dashboard. Nilai parameter berupa angka atau `null`; untuk nilai yang tidak valid sertakan `quality` per parameter dengan nilai `uncertain` atau `error`. Pesan MQTT retained diabaikan. `message_id` dipakai untuk menolak duplikasi dalam cache aktif.

## Menjalankan secara lokal

Kebutuhan: PHP 8.3+, ekstensi `pdo_pgsql`, Composer, Node.js, PostgreSQL, dan broker MQTT eksternal. Reverb dijalankan sebagai proses Laravel; Redis diperlukan hanya bila memilih cache Redis.

1. Buat database PostgreSQL `pump_monitoring` dan salin `.env.example` ke `.env`.
2. Isi `DB_*`, `MQTT_*`, `REDIS_*`, dan `REVERB_*` sesuai lingkungan lokal.
3. Jalankan:

```bash
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Jalankan `php artisan telemetry:subscribe`, `php artisan schedule:work`, dan `php artisan reverb:start` pada proses terpisah. `php artisan db:seed` membuat contoh lokasi, gateway, pompa satu/tiga fasa, parameter, dan histori.

`PumpDemoSeeder` mengisi histori 30 hari untuk tiga gateway demo: sumur utara yang stabil, sumur selatan dengan tren motor memanas dan debit menurun, serta booster dengan siklus hidup-mati. Sampel demo dibuat lebih rapat mendekati waktu sekarang (30 menit, 10 menit, 2 menit, lalu 1 menit) agar seluruh rentang grafik tetap informatif tanpa mengisi 30 hari data per menit. Untuk memperbarui hanya data demo, jalankan `php artisan db:seed --class=PumpDemoSeeder`; perintah ini mengganti histori sumber `PM-01` dan `MOTOR-01` pada tiga gateway `DEMO-PUMP-*`.

### PostgreSQL di Docker, aplikasi di Laragon

Gunakan [`docker-compose.database.yml`](docker-compose.database.yml) jika hanya database yang ingin dijalankan lewat Docker. Salin `.env.example` menjadi `.env` dan isi `DB_PASSWORD` dengan password lokal yang kuat. File Compose khusus ini membaca `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, dan `DB_PORT` dari `.env`; PostgreSQL hanya terbuka pada `127.0.0.1:5432`. Gunakan `DB_HOST=127.0.0.1` pada Laravel, bukan hostname `postgres` milik jaringan Docker.

Compose khusus database memakai volume sendiri, terpisah dari `postgres_data` pada stack Docker lengkap. Data di kedua volume tidak berpindah otomatis.

Contoh `.env.example` sudah memakai `MQTT_LIVE_CACHE_STORE=file`, `CACHE_STORE=file`, dan `REVERB_PUBLIC_PORT=8081` agar PHP Laragon tidak memerlukan ekstensi Redis dan browser terhubung langsung ke Reverb lokal. Cache file dibagi oleh proses PHP pada direktori proyek yang sama. Sesuaikan `APP_URL`, `REVERB_ALLOWED_ORIGINS`, dan `MQTT_HOST` dengan alamat situs Laragon serta broker MQTT eksternal yang benar. Broker MQTT tidak dijalankan oleh aplikasi.

Di PowerShell pada folder proyek:

```powershell
Copy-Item .env.example .env
# Isi DB_PASSWORD dan nilai lokal lain di .env sebelum menjalankan perintah berikut.
docker compose --env-file .env -f docker-compose.database.yml up -d
docker compose --env-file .env -f docker-compose.database.yml ps
```

Di terminal Laragon, jalankan `composer install`, `php artisan key:generate`, `php artisan migrate`, `npm install`, dan `npm run build`. Buka situs lewat virtual host Laragon, atau jalankan `php artisan serve` jika belum mengaktifkan virtual host. Untuk telemetry realtime, jalankan `php artisan telemetry:subscribe`, `php artisan schedule:work`, dan `php artisan reverb:start` di terminal terpisah. Untuk menghentikan database tanpa menghapus datanya, jalankan `docker compose --env-file .env -f docker-compose.database.yml down`.

Tes otomatis memakai database PostgreSQL `pump_monitoring_test` yang harus dibuat terlebih dahulu. Jalankan `composer test` hanya dengan database khusus tes karena `RefreshDatabase` membuat ulang tabel.

## Docker

Salin `.env.docker.example` ke `.env.docker`, isi semua nilai `CHANGE_THIS_*`, lalu jalankan:

```bash
docker compose --env-file .env.docker build
docker compose --env-file .env.docker up -d postgres redis
docker compose --env-file .env.docker run --rm app php artisan migrate --force
docker compose --env-file .env.docker up -d
```

Database menggunakan PostgreSQL 17 pada volume `postgres_data`. Subscriber menghubungi broker MQTT eksternal sesuai `.env.docker` atau pengaturan web. Data dari instalasi MySQL lama tidak berpindah otomatis. Migrasi `2026_10_03_000000_remove_legacy_water_analyzer_schema.php` membuang tabel dan kolom water analyzer lama dari database aplikasi; buat backup sebelum memperbarui instalasi yang masih menyimpan data lama.

Panduan distribusi image, instalasi offline, backup, restore, dan update berada di [deployment/README.md](deployment/README.md) serta [deployment/INSTALLATION.md](deployment/INSTALLATION.md).

## Konfigurasi monitoring

Tambahkan gateway melalui menu **Gateway**, lalu atur lokasi produksi, aset pompa, sumber data, dan parameter. Power meter dapat mengisi parameter dasar berdasarkan jumlah fasa aset. Ambang alarm diatur per parameter; data logger menampilkan dan mengekspor interval historis. Pengaturan broker MQTT tersedia bagi admin sistem.

Setelah mengubah pengaturan broker MQTT melalui dashboard, restart proses subscriber. Pada Docker jalankan `docker compose --env-file .env.docker restart telemetry-subscriber`.
