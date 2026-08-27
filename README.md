# Prezenca — Sistem Prezence për Biznese (SaaS)

Aplikacion web për menaxhimin e prezencës së punonjësve, i ndërtuar si **produkt multi-tenant**:
pronari i platformës krijon llogarinë e çdo biznesi klient, dhe secili biznes menaxhon vetë
menaxherët, punonjësit, orarin dhe rrjetin e punës.

Veçoria kryesore: **check-in-i lejohet vetëm nga rrjeti i punës**, i identifikuar me adresë IP
ose rang CIDR. Mungesat dhe kërkesat për leje raportohen nga kudo — prezenca vërtetohet vetëm
nga zyra.

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Tests](https://img.shields.io/badge/tests-42%20passing-brightgreen)

> Ndërfaqja dhe të gjitha mesazhet janë në **shqip** (`APP_LOCALE=sq`).

---

## Përmbajtja

- [Veçoritë](#veçoritë)
- [Teknologjitë](#teknologjitë)
- [Struktura e projektit](#struktura-e-projektit)
- [Kërkesat](#kërkesat)
- [Instalimi](#instalimi)
- [Konfigurimi i databazës](#konfigurimi-i-databazës)
- [Nisja e projektit](#nisja-e-projektit)
- [Llogaritë demo](#llogaritë-demo)
- [Si funksionon kufizimi me rrjet](#si-funksionon-kufizimi-me-rrjet)
- [Rolet](#rolet)
- [API-ja](#api-ja)
- [Skema e databazës](#skema-e-databazës)
- [Testet](#testet)
- [Probleme të zakonshme](#probleme-të-zakonshme)
- [Zhvillime të mëtejshme](#zhvillime-të-mëtejshme)

---

## Veçoritë

- **Multi-tenancy i plotë** — çdo query filtrohet me `business_id`; asnjë biznes nuk sheh të dhënat e tjetrit.
- **Check-in / check-out i kufizuar me rrjet** — IP e vetme ose CIDR, IPv4 dhe IPv6; mund të fiket për ekipe në distancë.
- **Raportim arsyeje** — sëmundje, vizitë te mjeku, emergjencë familjare, transport etj., plus shënim i lirë.
- **Kërkesa për leje me certifikatë** — ngarkim skedari, aprovim/refuzim nga menaxheri, opsion aprovimi automatik.
- **Tabela e ditës për menaxherin** — statuse me ngjyra, vonesat në minuta, veprime Aprovo / Refuzo / Kërko info.
- **Raport mujor** — përmbledhje për punonjës dhe për ekip.
- **Njoftime automatike pa cron** — gjenerohen kur hapet tabela e ditës, me `dedupe_key` unik kundër dublikimit.
- **Cilësime për çdo biznes** — orar, ditë pune, tolerancë vonese, prag njoftimi, rrjete të lejuara.
- **Autentikim me token** — Laravel Sanctum (`Authorization: Bearer <token>`).

---

## Teknologjitë

| Shtresa | Teknologjia |
|---|---|
| Backend | **Laravel 12** (PHP 8.2+), **Laravel Sanctum** për autentikim me token |
| Databazë | **MySQL / MariaDB** |
| Frontend | **React 19**, **React Router 7**, **Vite 8**, **Axios** |
| Stilim | CSS i shkruar me dorë (`src/styles.css`) |
| Testim | PHPUnit (Laravel Test) — 42 teste |
| Lint | Oxlint |

---

## Struktura e projektit

```
.
├── backend/          # API REST në Laravel 12
│   ├── app/          # Modelet, kontrollerët, katalogët e statuseve
│   ├── database/     # Migrimet dhe seeder-at me të dhëna demo
│   ├── routes/api.php
│   └── tests/        # AttendanceFlowTest, TenancyAndNetworkTest
└── frontend/         # SPA në React 19 + Vite
    └── src/
        ├── pages/        # Faqet sipas rolit
        ├── components/   # Komponentët e përbashkët
        └── api.js        # Klienti Axios
```

---

## Kërkesat

| Mjeti | Versioni minimal | I verifikuar me |
|---|---|---|
| PHP | 8.2 | 8.2.12 |
| Composer | 2.x | 2.8.6 |
| MySQL / MariaDB | 5.7 / 10.4 | MariaDB 10.4.32 |
| Node.js | 20 | 22.12.0 |
| npm | 10 | 10.9.0 |

Ekstensionet e PHP-së që kërkon Laravel: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`,
`xml`, `ctype`, `json`, `fileinfo`, `curl`.

> **Shënim:** Laravel 13 kërkon PHP ≥ 8.3. Ky projekt përdor Laravel 12, që punon me PHP 8.2.

---

## Instalimi

### 1. Klono repository-n

```bash
git clone <URL-i-repository-t>
cd emloyee
```

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

### 3. Frontend

```bash
cd ../frontend
npm install
cp .env.example .env      # opsionale
```

---

## Konfigurimi i databazës

Krijo databazën:

```sql
CREATE DATABASE employee_attendance
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Përditëso `backend/.env` me kredencialet e tua:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=employee_attendance
DB_USERNAME=root
DB_PASSWORD=
```

Pastaj ekzekuto migrimet dhe të dhënat demo:

```bash
cd backend
php artisan migrate --seed
php artisan storage:link     # që të shfaqen certifikatat e ngarkuara
```

> `php artisan migrate:fresh --seed` e rindërton databazën nga zero (fshin çdo të dhënë ekzistuese).

---

## Nisja e projektit

Nevojiten **dy terminale**.

**Terminali 1 — backend:**

```bash
cd backend
php artisan serve
```

→ <http://127.0.0.1:8000> · kontroll shëndeti: <http://127.0.0.1:8000/api/health>

**Terminali 2 — frontend:**

```bash
cd frontend
npm run dev
```

→ <http://127.0.0.1:5173>

Vite-i i proxy-on `/api` dhe `/storage` te backend-i, kështu që shfletuesi flet me një origjinë
të vetme dhe CORS nuk hyn në lojë. Origjina e backend-it ndryshohet me `VITE_BACKEND_ORIGIN`.

**Build për prodhim:**

```bash
cd frontend && npm run build      # del te dist/
```

---

## Llogaritë demo

Fjalëkalimi për të gjitha llogaritë: **`password`**

| Email | Roli | Biznesi |
|---|---|---|
| `super@demo.com` | Pronar i platformës | — |
| `admin@demo.com` | Administrator | Teknologji Prishtina |
| `manager@demo.com` | Menaxher | Teknologji Prishtina |
| `ardit@demo.com` | Punonjës (ka leje mjekësore në pritje) | Teknologji Prishtina |
| `besnik@demo.com` | Punonjës (vonohet shpesh) | Teknologji Prishtina |
| `drita@demo.com` · `erion@demo.com` | Punonjës | Teknologji Prishtina |
| `admin2@demo.com` · `manager2@demo.com` | Administrator · Menaxher | Market Dardania |
| `blerim@demo.com` | Punonjës — **check-in i bllokuar** (për të parë mbrojtjen) | Market Dardania |
| `teuta@demo.com` | Punonjës | Market Dardania |

*Teknologji Prishtina*: orar 08:00–16:00, Hën–Pre, check-in nga `127.0.0.1`.
*Market Dardania*: orar 07:00–15:00, Hën–Sht, check-in vetëm nga IP-ja e marketit.

---

## Si funksionon kufizimi me rrjet

Asnjë shfletues nuk e lexon dot emrin e WiFi-t (SSID) — kjo është e bllokuar nga vetë browser-i
për arsye privatësie. Prandaj "rrjeti i punës" identifikohet me **adresën IP**: të gjitha pajisjet
e lidhura me të njëjtin WiFi dalin në internet me të njëjtën IP publike. Biznesi e regjistron atë
IP (ose një rang CIDR) te faqja *Orari dhe rrjeti*, dhe backend-i e krahason me IP-në e kërkesës.

| Veprim | Kufizohet nga rrjeti? |
|---|---|
| Check-in / Check-out | ✅ po |
| Raportimi i arsyes | ❌ jo |
| Kërkesa për leje + certifikatë | ❌ jo |
| Pamja e menaxherit / raportet | ❌ jo |

Çdo check-in ruan IP-në te `attendance_records.checkin_ip`. Kontrolli fiket për një biznes me
`require_network_for_checkin = false`; sistemi nuk e lejon aktivizimin pa së paku një rrjet të
regjistruar.

**Kufizim i ndershëm:** IP-ja tregon *rrjetin*, jo *vendndodhjen fizike* — një punonjës i lidhur
me VPN-in e zyrës nga shtëpia do të dukej si brenda.

---

## Rolet

| Roli | Çfarë bën |
|---|---|
| **Pronar i platformës** (`super_admin`) | krijon dhe pezullon llogaritë e bizneseve klientë, bashkë me adminin e parë të secilit |
| **Administrator** (`admin`) | krijon menaxherë dhe punonjës, cakton orarin, ditët e punës dhe rrjetet e lejuara |
| **Menaxher** (`manager`) | tabela ditore, aprovon/refuzon lejet, raporti mujor |
| **Punonjës** (`employee`) | check-in / check-out, raporton arsye, kërkon leje |

Super-admini nuk i përket asnjë biznesi dhe nuk sheh të dhëna prezence — vetëm regjistrin e klientëve.

---

## API-ja

Të gjitha rrugët nën `/api`, me header `Authorization: Bearer <token>`.

| Rruga | Aksesi |
|---|---|
| `POST /login`, `GET /meta`, `GET /health` | publik |
| `GET /me`, `POST /logout`, `/notifications*` | i kyçur |
| `/attendance/today`, `/check-in`, `/check-out`, `/reason`, `/history` | punonjës |
| `GET\|POST /leave-requests`, `DELETE /leave-requests/{id}` | punonjës |
| `POST /leave-requests/{id}/decide` | menaxher, admin |
| `/manager/dashboard`, `/manager/monthly-report`, `/manager/employees/{user}` | menaxher, admin |
| `/admin/users` (CRUD) | admin |
| `/business/settings`, `/business/networks` (CRUD) | admin |
| `/super/businesses` (CRUD), `/super/businesses/{b}/users`, `/reset-password` | super-admin |

`check-in` dhe `check-out` kthejnë **422** me objektin `network` kur kërkesa vjen jashtë rrjetit
të lejuar.

---

## Skema e databazës

| Tabela | Kolonat kryesore |
|---|---|
| `businesses` | `slug`, `default_start_time`, `default_end_time`, `working_days` (json), `late_grace_minutes`, `manager_alert_after_minutes`, `auto_approve_sick_with_certificate`, `require_network_for_checkin`, `is_active` |
| `business_networks` | `business_id`, `label`, `ip_range`, `is_active` |
| `users` | `role`, `business_id`, `department`, `expected_start_time`, `manager_id`, `phone`, `is_active` |
| `attendance_records` | `business_id`, `work_date`, `status`, `checkin_time`, `checkin_ip`, `checkout_time`, `checkout_ip`, `late_minutes`, `reason_category`, `reason_note`, `excused`, `reported_at` — unik për `(user_id, work_date)` |
| `leave_requests` | `business_id`, `type`, `start_date`, `end_date`, `description`, `certificate_path`, `status`, `decided_by`, `manager_note` |
| `notifications` | `business_id`, `user_id`, `subject_user_id`, `type`, `title`, `body`, `dedupe_key` (unik), `read_at` |

Fshirja e një biznesi fshin në kaskadë përdoruesit, prezencën, lejet dhe njoftimet e tij.

---

## Testet

```bash
cd backend
php artisan test
```

**42 teste / 132 assertions** në dy suita:

- **`AttendanceFlowTest`** — llogaritja e vonesës, prompti i arsyes, njoftimi 15-minutësh dhe
  mos-dublikimi i tij, leja me certifikatë, rrëzimi i lejes, raporti mujor, CRUD.
- **`TenancyAndNetworkTest`** — izolimi mes bizneseve, rolet, biznesi i pezulluar, bllokimi i
  check-in-it jashtë rrjetit, përputhja CIDR/IPv6, orari për çdo biznes.

Testet përdorin një databazë të veçantë (shih `phpunit.xml`); krijoje me:

```sql
CREATE DATABASE employee_attendance_test;
```

Lint i frontend-it:

```bash
cd frontend && npm run lint
```

---

## Probleme të zakonshme

| Simptomë | Zgjidhja |
|---|---|
| `Request failed with status code 502` | backend-i nuk po xhiron — nis `php artisan serve` në terminal të dytë |
| `SQLSTATE[HY000] [2002]` | MySQL/MariaDB nuk po xhiron |
| Porta 8000 e zënë | gjej procesin (`netstat -ano \| findstr :8000` në Windows, `lsof -i :8000` në Linux/macOS) dhe mbylle |
| Faqja bëhet HTTPS vetë në Edge | përdor `http://127.0.0.1:5173` në vend të `localhost` |
| Check-in i bllokuar edhe në zyrë | IP-ja publike ka ndryshuar — shto rangun te *Orari dhe rrjeti*; faqja e tregon IP-në aktuale me një buton "përdore këtë" |
| Certifikatat nuk shfaqen | ekzekuto `php artisan storage:link` |
| Orët me diferencë | kontrollo `APP_TIMEZONE`, pastaj `php artisan config:clear` |

---

## Zhvillime të mëtejshme

- **Geofence me GPS** si shtresë e dytë mbi IP-në — kërkon HTTPS dhe leje nga përdoruesi.
- **PWA** — instalim në telefon (manifest + service worker).
- **App native** (React Native / Capacitor) mbi të njëjtin API.
- Faturim/abonim për bizneset, njoftime push ose email.
- Mbështetje për hostname dinamik në vend të IP-së fikse.
