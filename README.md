# MOM — Maternal Observation and Monitoring

**MOM** is a web-based maternal health monitoring platform that connects expectant mothers with doctors for continuous vital tracking, secure messaging, and remote care — only after both sides consent to connect.

> Closer care for every heartbeat.

---

## Features

### For patients
- Live vital monitoring (heart rate, blood pressure, temperature, oxygen saturation, fetal movement)
- Charts and previous vitals history
- AI-assisted health suggestions
- Search doctors by **name or phone** and send connection requests
- Message connected doctors only after mutual acceptance

### For doctors
- Search patients by **name or phone** and send connection requests
- Accept or reject incoming requests
- Monitor connected patients’ vitals remotely
- In-app messaging, contact notes, and medication messages
- Private clinical notes per patient

### Consent-based connections
Patients and doctors are **not** globally visible to each other.

1. Search by name or phone  
2. Send a connection request  
3. The other party accepts or rejects  
4. Only after **accepted** can they message — and doctors can access that patient’s data  

---

## Tech stack

| Layer | Technology |
|--------|------------|
| Backend | PHP 8, MySQL (mysqli / PDO) |
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5 |
| Charts | Chart.js |
| Server | Apache via XAMPP |
| Optional | Composer (`vlucas/phpdotenv`), Python embedding helper |

---

## Requirements

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8+)
- Modern browser (Chrome, Edge, Firefox)
- Windows / macOS / Linux

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/mhbappi05/ECG_Monitoring.git
```

Place the project folder in your XAMPP web root:

```text
C:\xampp\htdocs\ECG_Monitoring
```

### 2. Start XAMPP

1. Open **XAMPP Control Panel**
2. Start **Apache**
3. Start **MySQL**

### 3. Database

The app creates the database and core tables automatically on first load via `db.php`:

- Database: `ecg_monitoring`
- Tables: `users`, `messages`, `doctor_patient_connections`
- Per-patient vitals tables: `patient_{id}_data` (created on patient registration)

Default MySQL credentials in `db.php`:

```php
$servername = "localhost";
$username   = "root";
$password   = "";   // default XAMPP password (empty)
$database   = "ecg_monitoring";
```

Change these if your MySQL setup differs.

### 4. (Optional) Composer dependencies

```bash
cd C:\xampp\htdocs\ECG_Monitoring
composer install
```

### 5. Open the app

Visit:

```text
http://localhost/ECG_Monitoring/
```

This loads the **landing page** (`index.html`). Login / register from there.

---

## Usage

### Register
1. Open **Log in** → **Register**
2. Choose role: **Patient** or **Doctor**
3. Use an 11-digit phone number and a password

### Connect (required before chat / monitoring)
| Role | Where | What to do |
|------|--------|------------|
| Patient | Dashboard → **Your doctors** | Find & add → search → **Send request** |
| Doctor | Dashboard → **Find a patient** | Search → **Send request** |
| Either | Incoming requests | **Accept** or **Reject** |

### After connection
- **Patient:** Consult / message connected doctors  
- **Doctor:** **Monitor** vitals and **Message** connected patients only  

---

## Project structure

```text
ECG_Monitoring/
├── index.html                 # Landing page
├── login.html / login.php     # Auth UI + login handler
├── register.php               # Registration
├── logout.php
├── ecg.php                    # Patient dashboard
├── doctor-dashboard.php       # Doctor dashboard
├── monitor_patient.php        # Remote patient monitoring
├── db.php                     # DB connection + schema bootstrap
├── connections_helper.php     # Consent / connection helpers
├── api_search_users.php       # Search opposite role
├── api_connection.php         # Send / accept / reject / cancel
├── api_connections_list.php   # List connections & requests
├── send_message.php           # Messaging (connection-gated)
├── get_messages.php
├── save_vitals.php
├── save_doctor_note.php
├── css/                       # Styles (landing, dashboards, connections)
├── js/                        # Frontend scripts
└── README.md
```

---

## Roles & access

| Action | Patient | Doctor | Requires connection? |
|--------|---------|--------|----------------------|
| View own vitals | ✅ | — | — |
| Search opposite role | ✅ | ✅ | No |
| Send connection request | ✅ | ✅ | No |
| Message | ✅ | ✅ | **Yes** |
| Monitor patient data | — | ✅ | **Yes** |
| Doctor notes / prescribe message | — | ✅ | **Yes** |

---

## Screens

- **Landing** — product overview and CTAs  
- **Login / Register** — phone-based auth with role selection  
- **Patient dashboard** — vitals, charts, doctor connections, messenger, health assistant  
- **Doctor dashboard** — requests, search, connected patients  
- **Patient monitor** — detailed vitals view for a connected patient  

---

## Security notes

- Passwords are hashed with PHP `password_hash()` / `password_verify()`
- Messaging and patient-data endpoints check for an **accepted** connection
- This project is intended for **local / academic / demo** use; harden further before any production deployment (HTTPS, prepared statements everywhere, CSRF, env-based secrets, etc.)

---

## Disclaimer

MOM is a **monitoring and communication aid**, not a substitute for emergency medical care. Abnormal vitals or concerning symptoms should be handled through appropriate clinical channels.

---

## License

---

## Author

Built as a maternal observation and monitoring web application on the ECG Monitoring codebase.
