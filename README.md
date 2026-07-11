# 📡 TeleOps — Telecom Operations Dashboard

**Live Demo:** [telecomops.free.nf]

A full-stack PHP-based internal operations dashboard for telecom companies, designed to track SIM lifecycle, customer complaints, tower outages, and auto-identify high-churn risk zones in real-time.

## 🚀 Features

- **Role-Based Access** — Admin vs Agent permissions
- **SIM Lifecycle** — Activate, deactivate, suspend, port
- **Auto-Priority Complaints** — SIM swap = Critical, Network = High, etc.
- **Tower Outage Logger** — Track affected SIMs, resolve incidents
- **Live Dashboard** — KPIs, churn risk zones, SLA tracking

## 🛠 Tech Stack

PHP 8.x | MySQL | PDO | HTML5 | CSS3

## 🔐 Security

- 100% prepared statements (SQL injection proof)
- Password hashing with bcrypt
- Session-based authentication
-  rate limiting isn't implemented at the app layer here — for a real deployment this belongs at the reverse-proxy level (e.g. Nginx `limit_req`) so it protects the login endpoint even before PHP runs

## 🎯 Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@teleops.in` | `password` |
| Agent | `agent@teleops.in` | `password` |

## 🚀 Local Setup

1. Install XAMPP
2. Import `telecom.sql` via phpMyAdmin
3. Start Apache + MySQL
4. Open `http://localhost/telecom-ops/`

## 📁 Files

- `index.php` — Login
- `dashboard.php` — Main dashboard
- `sims.php` — SIM inventory
- `complaints.php` — Complaint ticketing
- `outages.php` — Tower outages
- `db.php` — Database connection
- `auth.php` — Session guard
- `telecom.sql` — Database schema

---

**Built for telecom operations simulation**
