 HEAD
# 🎓 ClassPulse — Classroom Analytics System

A production-ready full-stack classroom attendance analytics system with real-time dashboards, trend charts, automated at-risk student alerts, and exportable reports.

## ✨ Features

### 📊 Core Analytics Dashboard
- **Real-time Statistics Cards** — Total students, present today, absent today, overall attendance rate, at-risk count
- **Interactive Trend Charts** — Weekly and monthly attendance trends using Chart.js (Line, Bar, Doughnut)
- **Subject-wise Breakdown** — Per-subject average attendance with color-coded progress bars
- **Top Students Leaderboard** — Students with highest attendance ranked with medals
- **Recent Activity Feed** — Live log of the latest attendance check-ins

### ⚠️ Smart Alert System
- **Automated At-Risk Detection** — Auto-flags students below configurable threshold (default 75%)
- **Alert Banner** — Prominent warning banners on the dashboard showing flagged students
- **Color-coded Status** — Green/Purple/Orange/Red color system for excellent/good/at-risk/critical

### ✅ Attendance Management
- **Student Check-In Terminal** — Dedicated portal with real-time clock display
- **Time In / Time Out** — Dual-mode attendance recording
- **Duplicate Prevention** — Prevents double check-ins on the same day
- **Success Animation** — Visual feedback overlay after each check-in

### 📋 Reports & Export
- **CSV Export** — One-click download of any attendance table as `.csv`
- **PDF Export** — Print-friendly PDF generation via browser dialog
- **Filterable Reports** — Filter by course, year level, and time period
- **Per-Student Reports** — Individual student attendance across all subjects

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| **Frontend** | HTML5, CSS3 (custom design system), Vanilla JS ES6+ |
| **Charts** | Chart.js 4.x (CDN) |
| **Styling** | Bootstrap 5 + Custom ClassPulse CSS |
| **Fonts** | Google Fonts — Inter & Outfit |
| **Backend** | PHP 8.0+ (OOP, PDO) |
| **Database** | MySQL 8.0+ / MariaDB 10.4+ |
| **Auth** | PHP Sessions with password_hash/verify |

---

## 🚀 Quick Start (Local Development)

### Prerequisites
- PHP >= 8.0 (with PDO and PDO_MySQL extensions)
- MySQL >= 8.0 or MariaDB >= 10.4
- Apache / Nginx or PHP built-in server
- XAMPP / WAMP / MAMP (recommended)

### Step 1: Clone the Repository
```bash
git clone https://github.com/YOUR_USERNAME/classpulse.git
cd classpulse/attendance_system-main
```

### Step 2: Database Setup
1. Create a new database named `attendance`:
   ```sql
   CREATE DATABASE attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the schema:
   ```bash
   mysql -u root -p attendance < DB/attendance.sql
   ```

### Step 3: Configure Environment
```bash
cp .env.example .env
```

Edit `.env` with your database credentials:
```env
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASS=your_password
DB_NAME=attendance
ATTENDANCE_THRESHOLD=75
```

### Step 4: Run the Application

**Option A — XAMPP/WAMP:**
Place project in `htdocs/` and visit: `http://localhost/classpulse/attendance_system-main/`

**Option B — PHP Built-in Server:**
```bash
cd attendance_system-main
php -S localhost:8080
```

### Default Login Credentials
| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `admin123` |

> ⚠️ Change the default password immediately after first login!

---

## 🗄️ Database Schema

```
attendance (database)
├── admin              — Administrator accounts
├── school_year        — Academic year management
├── course_list        — Programs/Courses (BSCS, BSIT, etc.)
├── year_level         — Year levels (1st–5th Year)
├── subjects           — Subject catalog with class totals (v2 NEW)
├── students           — Student enrollment records
├── attendance_list    — Attendance log with timestamps
├── user_list          — Additional staff/instructor accounts
└── v_attendance_summary (VIEW) — Pre-computed attendance percentages
```

### Key View
```sql
-- Returns attendance percentages per student per subject
SELECT * FROM v_attendance_summary WHERE attendance_pct < 75;
```

---

## 🌐 Deployment Guide

### Deploy on Render (Free Tier)
1. Push repository to GitHub
2. Create a MySQL database on PlanetScale or Railway
3. Import `DB/attendance.sql`
4. Create a Render Web Service pointing to your repo
5. Set Environment Variables in Render dashboard:
   ```
   DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME, ATTENDANCE_THRESHOLD
   ```

### Deploy on cPanel / Shared Hosting
1. Upload files via FTP to `public_html/classpulse/`
2. Import `DB/attendance.sql` via phpMyAdmin
3. Add env vars in `.htaccess`:
   ```apache
   SetEnv DB_HOST     localhost
   SetEnv DB_USER     cpanel_user
   SetEnv DB_PASS     your_password
   SetEnv DB_NAME     cpanel_dbname
   ```

### Deploy on Railway
1. Push to GitHub
2. New Railway project → Deploy from GitHub
3. Add MySQL plugin → note connection credentials
4. Set environment variables from Railway dashboard

---

## 📁 Project Structure

```
attendance_system-main/
├── DB/
│   └── attendance.sql          # Enhanced schema + seed data
├── classes/
│   ├── db_conn.class.php       # Secure PDO connection
│   ├── EnvLoader.php           # .env file loader
│   └── Paginator.class.php     # Pagination helper
├── css/
│   ├── classpulse.css          # Main design system (dark theme)
│   └── bootstrap.min.css
├── js/
│   ├── classpulse.js           # Toast, charts, export utilities
│   └── bootstrap.bundle.min.js
├── index.php                   # App shell (authenticated)
├── login.php                   # Login page
├── nav.php                     # Sidebar navigation
├── home.php                    # Dashboard with charts & alerts
├── analytics.php               # Analytics & Reports page
├── attendance.php              # Student Check-In Terminal
├── attendance_list.php         # Attendance records
├── students.php                # Student management
├── Actions.php                 # All backend AJAX handlers
├── .env.example                # Environment template
└── README.md
```

---

## 🎨 Design System

ClassPulse uses a custom dark-mode design system:

```css
--cp-primary:   #6C63FF   /* Purple — primary actions */
--cp-secondary: #00D4AA   /* Teal   — success/present */
--cp-accent:    #FF6B6B   /* Coral  — danger/absent */
--cp-warning:   #FFB347   /* Amber  — at-risk */
```

**Attendance Color Scale:**
- ≥ 90% → Green  — Excellent
- ≥ 75% → Purple — Good (meets threshold)
- ≥ 60% → Amber  — At Risk
- < 60% → Red    — Critical

---

## 📄 License

MIT License — see the [LICENSE](LICENSE) file for details.
=======
# ClassPulse
>>>>>>> 7b15e6148017a71b62d49f6fa78ee2bd9e0c92ee
