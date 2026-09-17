# AGRO MARKETING APP (AMA)
## System Documentation / Software Requirements & Architecture Baseline
**Version:** 1.0  
**Status:** Baseline for implementation  
**Intended audience:** Product Owner, Software Architect, Claude Code / AI implementation agent, Backend Developer, Android Developer, QA  
**Primary purpose:** Menjadi *single source of truth* awal untuk pembangunan Agro Marketing App (AMA).

---

# 1. Ringkasan Sistem

## 1.1 Nama Aplikasi
**Agro Marketing App (AMA)**

AMA adalah sistem untuk mendukung kegiatan marketing/agronomi lapangan PT Dummy. Sistem digunakan untuk:

- membuat rencana kegiatan lapangan;
- merealisasikan kegiatan berdasarkan rencana maupun tanpa rencana;
- mencatat jenis kegiatan dan produk terkait;
- mengambil foto sebagai bukti kegiatan;
- merekam lokasi GPS kegiatan;
- mendeteksi indikasi lokasi palsu / manipulasi lokasi;
- menyimpan kegiatan secara offline ketika jaringan tidak tersedia;
- menyinkronkan data ke server ketika jaringan tersedia;
- memantau aktivitas user melalui Web Admin;
- menyediakan notifikasi dan laporan aktivitas.

Sistem terdiri dari **dua aplikasi utama**:

1. **Web Admin**
2. **Android APK**

Kedua aplikasi menggunakan satu backend/API pusat.

---

# 2. Tujuan Bisnis

AMA dibangun untuk meningkatkan:

- keterlacakan kegiatan marketing/agronomi lapangan;
- validitas bukti pelaksanaan kegiatan;
- disiplin pencatatan aktivitas;
- kualitas data lokasi dan dokumentasi;
- kemudahan monitoring oleh manajemen;
- konsistensi antara rencana dan realisasi;
- efektivitas reporting kegiatan lapangan.

---

# 3. Prinsip Arsitektur

Arsitektur utama yang digunakan adalah:

> **API-First Modular Monolith + Native Android Offline-First + Evidence-Based Location Integrity**

Prinsip utamanya:

1. Backend dibuat sebagai **Modular Monolith**, bukan microservices.
2. Android dibuat **native menggunakan Kotlin**.
3. Android harus **offline-first**.
4. Web Admin menggunakan backend yang sama.
5. Semua komunikasi client-server melalui API yang terversi.
6. Server tidak boleh mempercayai data dari mobile secara mentah.
7. GPS, foto, device integrity, timestamp, dan capture session diperlakukan sebagai satu kesatuan bukti aktivitas.
8. Master data berasal dari server, bukan di-hardcode di APK.
9. Semua perubahan penting harus dapat diaudit.

---

# 4. High-Level Architecture

```text
                         AGRO MARKETING APP (AMA)

 ┌──────────────────────────┐       ┌──────────────────────────┐
 │        WEB ADMIN         │       │       ANDROID APP        │
 │ Laravel + Inertia + React│       │ Kotlin Native            │
 │                          │       │ Jetpack Compose           │
 │ Dashboard                │       │ Offline-first             │
 │ User Management          │       │ GPS                       │
 │ Master Data              │       │ Camera                    │
 │ Monitoring               │       │ Anti Fake GPS             │
 │ Reports                  │       │ Background Sync           │
 └──────────────┬───────────┘       └─────────────┬────────────┘
                │                                 │
                └──────────── HTTPS / REST API ───┘
                                  │
                         ┌────────▼────────┐
                         │  AMA BACKEND    │
                         │ Laravel         │
                         │ Modular Monolith│
                         └────────┬────────┘
                                  │
              ┌───────────────────┼───────────────────┐
              │                   │                   │
        ┌─────▼──────┐      ┌─────▼──────┐      ┌─────▼──────┐
        │ PostgreSQL │      │   Redis    │      │   Object   │
        │ + PostGIS  │      │ Queue/Cache│      │   Storage  │
        └────────────┘      └────────────┘      └────────────┘
                                                      │
                                              S3 Compatible
                                              Photo Evidence

                                  │
                         ┌────────▼────────┐
                         │ Firebase FCM    │
                         │ Notification    │
                         └─────────────────┘
```

---

# 5. Technology Stack

## 5.1 Backend
- **Framework:** Laravel
- **Architecture:** Modular Monolith
- **API:** REST JSON
- **API Versioning:** `/api/v1`
- **Authentication:** Access Token + Refresh Token untuk mobile
- **Web Authentication:** Secure session cookie
- **Queue:** Redis
- **Scheduler:** Laravel Scheduler
- **Web Server:** Nginx
- **Containerization:** Docker

## 5.2 Web Admin
- Laravel
- Inertia.js
- React
- Komponen dashboard, tabel, chart, filter, dan map

## 5.3 Android
- **Language:** Kotlin
- **UI:** Jetpack Compose
- **Architecture:** Clean-ish Architecture + MVVM
- **Dependency Injection:** Hilt
- **Networking:** Retrofit + OkHttp
- **Serialization:** Kotlin Serialization atau Moshi
- **Local Database:** Room
- **Preferences:** DataStore
- **Background Sync:** WorkManager
- **Camera:** CameraX
- **Location:** FusedLocationProviderClient
- **Push Notification:** Firebase Cloud Messaging
- **Security:** Android Keystore
- **App Integrity:** Google Play Integrity API

## 5.4 Database
- PostgreSQL
- PostGIS untuk fungsi geospatial

## 5.5 Object Storage
Gunakan storage S3-compatible, misalnya:
- AWS S3;
- Cloudflare R2;
- MinIO;
- provider S3-compatible lain.

Foto tidak disimpan sebagai binary di database.

---

# 6. Alasan Memilih Modular Monolith

Sistem **tidak menggunakan microservices pada versi awal**.

Alasan:

- domain belum membutuhkan distributed architecture;
- menghindari kompleksitas deployment;
- lebih mudah dikembangkan dengan AI coding agent;
- transaction lebih sederhana;
- debugging lebih mudah;
- biaya operasional lebih rendah;
- tetap dapat dipecah menjadi service terpisah di masa depan jika dibutuhkan.

Backend tetap harus modular secara kode.

Contoh modul:

```text
Modules/
├── Identity/
├── Users/
├── Organization/
├── MasterData/
├── Planning/
├── Activities/
├── Products/
├── Location/
├── Media/
├── Notifications/
├── Reports/
└── Audit/
```

---

# 7. Struktur Backend yang Direkomendasikan

Setiap domain besar dapat memiliki struktur:

```text
Modules/Activity/
├── Domain/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Rules/
│   └── Repositories/
├── Application/
│   ├── Commands/
│   ├── Queries/
│   └── UseCases/
├── Infrastructure/
│   ├── Persistence/
│   └── Services/
└── Presentation/
    ├── Controllers/
    ├── Requests/
    └── Resources/
```

Business rule tidak boleh diletakkan seluruhnya di controller.

---

# 8. Struktur Android yang Direkomendasikan

```text
ama-android/
├── app/
├── core/
│   ├── common/
│   ├── network/
│   ├── database/
│   ├── security/
│   ├── location/
│   ├── camera/
│   └── sync/
└── feature/
    ├── auth/
    ├── dashboard/
    ├── planning/
    ├── activity/
    ├── notification/
    └── profile/
```

Alur arsitektur:

```text
Compose UI
    ↓
ViewModel
    ↓
Use Case
    ↓
Repository
   /      \
Room      REST API
Local     Remote
```

---

# 9. Struktur Repository

Direkomendasikan menggunakan dua repository:

```text
ama-backend/
├── Laravel Backend
├── Web Admin
├── API
└── docs/

ama-android/
├── Kotlin Android
└── docs/
```

Keuntungan:
- konteks AI lebih kecil;
- build lifecycle terpisah;
- release lifecycle terpisah;
- security boundary lebih jelas.

---

# 10. User Roles

Minimal role awal:

- `SUPER_ADMIN`
- `ADMIN`
- `MANAGER`
- `SUPERVISOR`
- `AGRONOMIST`

Versi awal dapat dimulai dari:
- `ADMIN`
- `AGRONOMIST`

Gunakan permission, bukan hanya hardcoded role checks.

Contoh:

```text
users.manage
master_data.manage
plans.view
plans.create
activities.view
activities.create
activities.verify
reports.view
```

---

# 11. Profil Pengguna

Data user minimal:

- Nama Lengkap
- NIP
- Nomor HP
- Email
- Jabatan / Posisi
- Lokasi Tugas
- Foto Profil
- Status Aktif
- Role
- Last Login

Jabatan dan lokasi tugas sebaiknya berasal dari master data.

---

# 12. Menu Utama Android

Dashboard Android memiliki menu:

1. **Daftar Kegiatan**
2. **Upload Kegiatan**
3. **Ambil Gambar**
4. **Daftar Rencana**
5. **Buat Rencana**
6. **Pengaturan Akun**

Tambahan:
- Notification bell
- Identitas user
- Jabatan / area kerja

---

# 13. Modul Rencana Kegiatan

## 13.1 Tujuan
Menyimpan kegiatan yang direncanakan sebelum pelaksanaan.

## 13.2 Data minimal
- tanggal rencana;
- jenis kegiatan;
- lokasi;
- kategori produk;
- produk/merk;
- catatan;
- creator;
- waktu pembuatan.

## 13.3 Status
Contoh:

```text
PLANNED
READY
REALIZED
CANCELLED
```

Rencana yang sudah direalisasikan harus memiliki referensi ke realisasi.

---

# 14. Modul Realisasi Kegiatan

Terdapat dua mode.

## 14.1 Realisasi dari Rencana

User:
1. memilih rencana;
2. data rencana dimuat otomatis;
3. user menambahkan bukti realisasi;
4. user mengambil foto;
5. sistem mengambil GPS;
6. user melengkapi data bila diperlukan;
7. data disimpan lokal;
8. data disinkronkan ke server.

Istilah UI yang direkomendasikan:

> **Realisasi dari Rencana**

bukan hanya “Input Otomatis”.

## 14.2 Input Manual / Tanpa Rencana

Digunakan untuk kegiatan spontan.

User mengisi form dari awal.

---

# 15. Jenis Kegiatan

Master awal:

1. Demonstrasi Plot
2. Penyuluhan
3. Farm Field Day
4. Study Banding
5. Big Farmer Day
6. Kunjungan Toko / Kios
7. Cek Stok Toko / Kios

Data tidak boleh di-hardcode pada aplikasi.

---

# 16. Produk

Kategori produk awal:

| Category | Product / Merk |
|---|---|
| Decomposer | BEKA |
| Senyawa Humat | POMMIX |
| Pupuk Organik Cair (POC) | POMI |

Model data direkomendasikan:

```text
product_categories
products
```

Jangan terlalu mengikat database pada istilah `brand`.

Relasi:

```text
PRODUCT CATEGORY
      ↓
PRODUCT
```

Dropdown Android harus dependent:

```text
Kategori Produk
      ↓
filter product
      ↓
Produk / Merk
```

---

# 17. Foto Kegiatan

## 17.1 Prinsip
Foto digunakan sebagai bukti aktivitas.

## 17.2 Sumber Foto
Untuk evidence utama, direkomendasikan hanya:

> **Camera internal AMA menggunakan CameraX**

Gallery dapat dipertimbangkan untuk kebutuhan lain, tetapi foto evidence utama sebaiknya tidak berasal dari gallery.

## 17.3 Metadata Foto
Simpan minimal:

- photo_id;
- activity_id;
- capture_session_id;
- captured_at_device;
- received_at_server;
- latitude;
- longitude;
- accuracy;
- device_id;
- source;
- SHA-256 hash;
- file size;
- MIME type;
- storage path;
- integrity status.

## 17.4 Compression
Foto dikompresi sebelum upload.

Target operasional awal:
- ukuran file wajar;
- kualitas cukup untuk dokumentasi;
- hindari foto kamera asli 5–15 MB jika tidak diperlukan.

---

# 18. Activity Evidence Bundle

Satu realisasi kegiatan dianggap sebagai satu paket bukti.

```text
ACTIVITY EVIDENCE
├── Activity Data
├── User
├── Device
├── Timestamp
├── GPS
│   ├── Latitude
│   ├── Longitude
│   └── Accuracy
├── Photo(s)
├── Capture Session
├── Device Integrity
└── Server Validation
```

Bukti tidak boleh hanya berupa watermark pada foto.

---

# 19. GPS dan Location Integrity

## 19.1 Prinsip
Tidak ada sistem Android yang dapat menjamin GPS 100% asli.

AMA harus menggunakan **multi-layer detection**.

## 19.2 Layer 1 — Mock Location Detection
Mobile membaca indikator mock location dari Android.

Jika mock terdeteksi:
- tandai aktivitas;
- kebijakan awal dapat `BLOCK` atau `FLAGGED`.

Final rule harus dapat dikonfigurasi.

## 19.3 Layer 2 — Play Integrity
Mobile meminta Play Integrity token.

Server melakukan validasi.

Jangan melakukan seluruh validasi hanya di APK.

## 19.4 Layer 3 — GPS Metadata
Simpan:

- latitude;
- longitude;
- accuracy;
- altitude bila tersedia;
- speed;
- bearing;
- provider;
- timestamp.

Lokasi dengan accuracy buruk harus dapat diberi status low confidence.

## 19.5 Layer 4 — Server-Side Plausibility
Backend mengecek perpindahan lokasi.

Contoh:

```text
08:00 Bojonegoro
08:15 Semarang
```

Jika membutuhkan kecepatan yang tidak realistis:

```text
LOCATION_ANOMALY
```

## 19.6 Layer 5 — GPS dan Foto Harus Berada Dalam Capture Session

Gunakan:

```text
capture_session_id
started_at
location_at
photo_at
submitted_at
```

Target awal:
- GPS dan foto diambil dalam selang waktu yang dekat;
- contoh baseline: 30–60 detik.

Nilai final dibuat configurable.

## 19.7 Layer 6 — Device & Behavioral Risk

Server menghasilkan status:

```text
TRUSTED
SUSPICIOUS
REJECTED
```

atau:

```text
NORMAL
REVIEW
HIGH_RISK
```

Bukan hanya boolean `fake=true/false`.

---

# 20. Location Confidence

Contoh internal validation:

```text
Integrity           PASS
Mock Location       FALSE
GPS Accuracy        8 meter
Impossible Travel   FALSE
Photo Source        CAMERA
Timestamp           NORMAL
```

Hasil:

```text
LOCATION_CONFIDENCE = HIGH
```

Detail algoritma risk scoring tidak perlu ditampilkan ke user lapangan.

Web Admin cukup menampilkan:

- Verified
- Needs Review
- Rejected

---

# 21. Offline-First

Android harus tetap dapat bekerja ketika jaringan buruk atau tidak tersedia.

Flow:

```text
User membuat realisasi
        ↓
Foto diambil
        ↓
GPS diperoleh
        ↓
Data disimpan di Room
        ↓
PENDING_SYNC
        ↓
Internet tersedia
        ↓
WorkManager
        ↓
Upload metadata
        ↓
Upload foto
        ↓
Server finalize
        ↓
SYNCED
```

User tidak boleh kehilangan data hanya karena koneksi internet terputus.

---

# 22. Sync Strategy

State lokal minimal:

```text
LOCAL_DRAFT
PENDING_SYNC
SYNCING
SYNCED
SYNC_FAILED
```

Retry harus:
- aman;
- idempotent;
- tidak membuat activity ganda;
- tidak mengupload foto ganda.

Gunakan idempotency key / client-generated UUID.

---

# 23. Activity State Machine

Realisasi dapat menggunakan state:

```text
DRAFT
SUBMITTED
SYNCED
VERIFIED
REJECTED
```

Jika approval belum diperlukan pada V1, jangan memaksakan approval.

Tetapi desain harus memungkinkan approval ditambahkan nanti.

---

# 24. Device Registration

Setiap instalasi/perangkat dapat diregistrasikan.

Data:

- device UUID;
- user;
- app version;
- OS version;
- manufacturer;
- model;
- last active;
- integrity status;
- revoked_at.

Admin dapat melakukan revoke device.

---

# 25. Authentication

## 25.1 Android
Gunakan:
- access token;
- refresh token;
- secure storage.

Access token:
- short-lived.

Refresh token:
- disimpan aman;
- dapat dicabut server.

Token tidak disimpan dalam plain SharedPreferences.

Gunakan Android Keystore / encrypted mechanism.

## 25.2 Web
Gunakan:
- secure session cookie;
- CSRF protection;
- HTTPS.

---

# 26. Authorization

Setiap endpoint harus mempunyai permission check.

Contoh:

```text
GET /users
requires users.manage

POST /activities
requires activities.create

GET /reports
requires reports.view
```

---

# 27. Master Data

Master yang disediakan server:

- Activity Types
- Product Categories
- Products
- Positions
- Work Locations
- Roles / Permissions bila dibutuhkan
- Notification configuration bila dibutuhkan

Android menyimpan cache untuk offline use.

---

# 28. Notification

Gunakan Firebase Cloud Messaging.

Contoh notification:
- rencana kegiatan besok;
- rencana kegiatan hari ini;
- rencana belum direalisasikan;
- upload berhasil;
- aktivitas membutuhkan review;
- pengumuman admin.

Notifikasi juga disimpan dalam database agar notification center tetap memiliki history.

---

# 29. Web Admin

## 29.1 Dashboard
Contoh informasi:

```text
TODAY
Activities
Planned
Not Realized
Location Alerts
```

Filter:
- tanggal;
- area;
- agronomist;
- jenis kegiatan;
- produk;
- status.

## 29.2 User Management
- CRUD user;
- role;
- jabatan;
- lokasi tugas;
- aktivasi/deaktivasi;
- device registration;
- revoke device.

## 29.3 Master Data
- activity types;
- product categories;
- products;
- positions;
- work locations.

## 29.4 Activity Monitoring
Admin dapat melihat:
- user;
- waktu;
- jenis kegiatan;
- produk;
- foto;
- koordinat;
- accuracy;
- device;
- location integrity;
- sync status.

## 29.5 Map
Web dapat menampilkan titik kegiatan.

Klik lokasi:
- agronomist;
- activity;
- time;
- photo;
- product;
- GPS accuracy;
- integrity status.

## 29.6 Reporting
Laporan berdasarkan:
- tanggal;
- user;
- lokasi;
- activity type;
- product;
- realization rate;
- integrity status.

---

# 30. Audit Trail

Sistem wajib memiliki audit log untuk aksi penting.

Simpan:

- user;
- action;
- entity;
- entity_id;
- timestamp;
- IP;
- device bila relevan;
- old value;
- new value.

Contoh:

```text
User X changed activity #123
Old location = A
New location = B
```

Gunakan soft delete untuk data penting apabila sesuai.

---

# 31. Database Entity Baseline

Minimal:

```text
users
roles
permissions
positions
work_locations

devices

activity_types

product_categories
products

activity_plans
activity_plan_products

activities
activity_products

activity_locations

capture_sessions
activity_photos

device_integrity_checks

notifications

audit_logs
```

---

# 32. Geospatial Database

Gunakan PostGIS.

Kebutuhan:
- distance antar lokasi;
- radius;
- geofence;
- nearest location;
- activity in territory;
- impossible travel detection.

Contoh fungsi:

```text
ST_Distance
ST_DWithin
ST_Within
```

---

# 33. API Baseline

Semua endpoint mobile menggunakan prefix:

```text
/api/v1/
```

Contoh:

```text
POST   /api/v1/auth/login
POST   /api/v1/auth/refresh
POST   /api/v1/auth/logout

GET    /api/v1/me

GET    /api/v1/master/activity-types
GET    /api/v1/master/product-categories
GET    /api/v1/master/products

GET    /api/v1/plans
POST   /api/v1/plans
GET    /api/v1/plans/{id}
PUT    /api/v1/plans/{id}

GET    /api/v1/activities
POST   /api/v1/activities
GET    /api/v1/activities/{id}

POST   /api/v1/activities/{id}/location
POST   /api/v1/activities/{id}/photos
POST   /api/v1/activities/{id}/complete

POST   /api/v1/integrity/play
POST   /api/v1/devices/register

GET    /api/v1/notifications
POST   /api/v1/notifications/{id}/read
```

Endpoint final dibuat berdasarkan detailed API contract.

---

# 34. Media Upload Strategy

Jangan menjadikan upload banyak foto sebagai satu transaksi HTTP panjang.

Flow:

```text
Create activity
    ↓
Receive activity ID
    ↓
Upload metadata
    ↓
Upload photos
    ↓
Complete / finalize activity
```

Upload harus mendukung retry.

---

# 35. File Integrity

Setiap photo evidence harus memiliki hash:

```text
SHA-256
```

Server dapat memverifikasi file saat upload.

Hash bukan bukti keaslian foto secara absolut, tetapi berguna untuk:
- integrity;
- duplicate detection;
- traceability.

---

# 36. Security Principles

1. Client dianggap tidak terpercaya.
2. Semua critical validation dilakukan server-side.
3. Jangan mempercayai field seperti:
   - `mock_location=false`
   - `integrity=true`
   tanpa server verification.
4. Gunakan HTTPS.
5. Rate limit endpoint sensitif.
6. Gunakan validation pada semua input.
7. Jangan menyimpan secret di APK secara hardcoded.
8. Jangan log access token/password.
9. Gunakan least privilege.
10. Audit perubahan penting.

---

# 37. Environment

Pisahkan:

```text
DEV
STAGING
PRODUCTION
```

Database masing-masing environment harus terpisah.

Tidak boleh melakukan testing dengan database production.

---

# 38. Deployment Baseline

```text
Internet
    ↓
Nginx
    ↓
Laravel Application
    │
    ├── PostgreSQL + PostGIS
    ├── Redis
    └── S3-Compatible Storage

Laravel Queue Worker
Laravel Scheduler
```

Gunakan Docker untuk konsistensi environment.

---

# 39. CI/CD

Minimal pipeline:

Backend:
1. install dependencies;
2. lint;
3. test;
4. security checks;
5. build;
6. deploy staging;
7. manual approval production.

Android:
1. Gradle validation;
2. lint;
3. unit test;
4. assembleDebug / assembleRelease;
5. artifact output.

---

# 40. Android Development Workflow

Editor utama dapat menggunakan:

> **VS Code + Claude Code**

Android Studio tetap digunakan sebagai tool Android pendukung.

Peran:

## VS Code
- edit Kotlin;
- edit Compose;
- edit Gradle;
- Claude Code;
- terminal.

## Android Studio
- SDK Manager;
- emulator;
- Logcat;
- profiler;
- advanced debugging;
- APK inspection;
- signing bila diperlukan.

Build ground truth:

```bash
./gradlew build
./gradlew test
```

Device test dapat menggunakan:

```bash
adb
```

---

# 41. AI-Assisted Development Rules

Dokumen ini digunakan sebagai source of truth bagi Claude.

Claude wajib mengikuti aturan berikut.

## 41.1 Jangan Ubah Arsitektur Tanpa Approval
Tidak boleh mengganti:
- Kotlin menjadi React Native;
- Laravel menjadi framework lain;
- PostgreSQL menjadi MySQL;
- Modular Monolith menjadi microservices;
- Compose menjadi framework lain;
tanpa persetujuan Product Owner.

## 41.2 Pin Dependency Versions
Jangan upgrade:
- Kotlin;
- Gradle;
- Android Gradle Plugin;
- compileSdk;
- dependency utama;
secara otomatis untuk menyelesaikan error.

Jika upgrade dibutuhkan, laporkan alasannya.

## 41.3 Build-Test-Fix
Setelah implementasi:
1. build;
2. test;
3. perbaiki error;
4. laporkan hasil.

Jangan menyatakan selesai bila build gagal.

## 41.4 Satu Task Satu Scope
Jangan mengimplementasikan terlalu banyak modul dalam satu perubahan.

Contoh urutan:

```text
Part 1  Project Bootstrap
Part 2  Authentication
Part 3  Master Data
Part 4  Planning
Part 5  Local Database
Part 6  Activity Realization
Part 7  Camera Evidence
Part 8  GPS
Part 9  Location Integrity
Part 10 Offline Sync
Part 11 Notifications
Part 12 Admin Monitoring
Part 13 Hardening
```

## 41.5 Jangan Hardcode Master Data
Activity type, products, category, work location harus berasal dari backend.

## 41.6 Jangan Menganggap Anti Fake GPS Selesai Dengan isMock
Anti-fake GPS adalah subsystem multi-layer.

## 41.7 Jangan Menaruh Business Rule di UI
UI hanya presentation.

## 41.8 Jangan Menghapus Data / Refactor Besar Tanpa Scope
Perubahan harus minimal dan terkontrol.

---

# 42. Non-Functional Requirements

## Performance
- UI Android harus tetap responsif.
- query admin harus memakai pagination.
- foto dikompresi.
- heavy job gunakan queue.

## Availability
- mobile tetap dapat bekerja offline.
- pending data tersinkron setelah jaringan kembali.

## Security
- HTTPS wajib.
- secure token storage.
- server-side authorization.
- Play Integrity.
- audit trail.

## Maintainability
- modular code.
- testable use cases.
- versioned API.

## Scalability
Target arsitektur awal harus dapat berkembang dari:
- puluhan user;
- ratusan user;
- ribuan user;
tanpa redesign fundamental.

---

# 43. Data yang Tidak Boleh Dipercaya Dari Client

Backend harus menganggap data berikut dapat dimanipulasi:

- GPS;
- timestamp device;
- device metadata;
- mock location flag;
- app version;
- user-entered location;
- file metadata;
- photo EXIF.

Karena itu server melakukan cross-validation.

---

# 44. Hal yang Masih Perlu Finalisasi

Berikut requirement yang belum final dan harus dikonfirmasi sebelum modul terkait selesai:

1. Apakah satu activity boleh mempunyai lebih dari satu produk?
2. Apakah satu activity boleh mempunyai lebih dari satu foto?
3. Apakah evidence photo wajib hanya CameraX atau gallery juga diizinkan?
4. Apakah GPS wajib untuk semua jenis activity?
5. Threshold minimum GPS accuracy.
6. Capture session time window.
7. Kebijakan ketika mock location terdeteksi: BLOCK atau REVIEW.
8. Apakah supervisor approval diperlukan.
9. Apakah user dapat melihat activity user lain.
10. Apakah rencana boleh diedit setelah tanggal kegiatan.
11. Apakah realisasi dapat diedit setelah submit.
12. Apakah geo-fencing area kerja diperlukan.
13. Apakah map wajib di V1.
14. Bentuk laporan export: Excel/PDF/both.
15. Detail notification rules.

Jangan mengasumsikan jawaban tanpa konfirmasi Product Owner.

---

# 45. Scope V1 yang Direkomendasikan

## Android
- login;
- dashboard;
- profile;
- sync master data;
- daftar rencana;
- buat rencana;
- realisasi dari rencana;
- realisasi manual;
- CameraX evidence;
- GPS;
- mock detection;
- local Room database;
- WorkManager sync;
- notification;
- activity history.

## Web Admin
- authentication;
- dashboard;
- user management;
- master data;
- plan monitoring;
- activity monitoring;
- photo viewer;
- location viewer;
- integrity status;
- simple reporting;
- notification management.

## Backend
- auth;
- user;
- device registration;
- master data;
- planning;
- activities;
- photo upload;
- location;
- integrity evaluation;
- offline sync support;
- notification;
- audit log.

---

# 46. Out of Scope V1

Kecuali diminta kemudian:

- microservices;
- machine learning fraud detection;
- complex route optimization;
- live GPS tracking continuous;
- payroll;
- CRM;
- sales order;
- inventory;
- accounting;
- face recognition;
- biometric attendance.

---

# 47. Definition of Done

Sebuah feature hanya dianggap selesai jika:

1. sesuai requirement;
2. build berhasil;
3. test terkait berhasil;
4. API contract sesuai;
5. error state ditangani;
6. offline behavior bila relevan ditangani;
7. permission/security ditangani;
8. tidak merusak modul lain;
9. dokumentasi diperbarui;
10. tidak mengandung hardcoded credential.

---

# 48. Implementation Milestones

## Milestone A — Foundation
- repository;
- Laravel bootstrap;
- Android bootstrap;
- Docker;
- PostgreSQL/PostGIS;
- Redis;
- environment;
- CI.

## Milestone B — Identity
- users;
- roles;
- login;
- token lifecycle;
- devices.

## Milestone C — Master Data
- activity types;
- product categories;
- products;
- positions;
- work locations.

## Milestone D — Planning
- create plan;
- list plan;
- detail plan;
- status.

## Milestone E — Activity Realization
- manual;
- from plan;
- local draft;
- validation.

## Milestone F — Evidence
- CameraX;
- capture session;
- GPS;
- metadata;
- photo hash.

## Milestone G — Integrity
- mock detection;
- Play Integrity;
- GPS confidence;
- impossible travel;
- server evaluation.

## Milestone H — Offline Sync
- Room;
- WorkManager;
- retry;
- idempotency;
- sync status.

## Milestone I — Web Monitoring
- activity list;
- map;
- evidence viewer;
- integrity review.

## Milestone J — Notification & Reporting
- FCM;
- notification center;
- reports.

## Milestone K — Hardening
- security review;
- performance;
- audit log;
- staging;
- release build.

---

# 49. Product Principle

AMA bukan sekadar form upload foto.

AMA adalah sistem:

> **Planning + Field Activity + Evidence + Location Integrity + Monitoring**

Arsitektur dan implementasi harus selalu mempertahankan prinsip tersebut.

---

# 50. AI Agent Handover Note

Claude harus mempelajari dokumen ini sebelum melakukan implementasi.

Sebelum coding:
1. review repository;
2. identifikasi state existing project;
3. bandingkan dengan dokumen;
4. jangan langsung refactor besar;
5. buat implementation plan per milestone;
6. implementasi bertahap;
7. selalu build/test;
8. laporkan file yang diubah;
9. laporkan keputusan teknis;
10. laporkan blocker secara jujur.

Jika requirement belum jelas:
- jangan menebak business rule penting;
- tanyakan atau tandai sebagai `OPEN QUESTION`.

Jika ada konflik antara code existing dengan dokumen:
- jangan diam-diam memilih salah satu;
- laporkan konflik;
- minta keputusan.

---

# 51. Baseline Architecture Decision

**Decision:** VALID for AMA V1.

Gunakan:

> Laravel Modular Monolith + React/Inertia Web Admin + Kotlin Native Android + Jetpack Compose + Room + WorkManager + CameraX + Fused Location + Play Integrity + PostgreSQL/PostGIS + Redis + S3-compatible storage + Firebase FCM.

Microservices tidak digunakan pada V1.

Dokumen ini menjadi baseline sampai Product Owner menyetujui revisi.
