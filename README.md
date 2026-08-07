# OCT Feedback Board System

A full-stack **Capstone Project** web application that lets students, faculty and staff
submit campus concerns (feedback) and lets school offices (OSA, HR, Maintenance, Guidance…)
respond, assign, and resolve them — all tracked through unique ticket numbers.

Built with **PHP 8 + MySQL/MariaDB** (classic XAMPP stack), PDO prepared statements,
bcrypt password hashing, CSRF protection, and a fully responsive UI.

---

## Features

### Phase 1 — Authentication
- Login with **brute-force protection** (5 attempts lead to a 15-minute lockout)
- Registration with **ID Number, Name, Department/Course, Role** (Student/Faculty/Staff)
- Optional **school-email domain verification** (see `config.php` > `SCHOOL_EMAIL_DOMAINS`)
- **Forgot password** with one-time reset links (demo mode shows the link on screen)
- **Terms & Conditions / Data Privacy Act (RA 10173)** consent before sign-up

### Phase 2 — User Dashboard
- Welcome screen with live summary: *2 Pending · 1 Under Review · 1 Resolved*
- **Public Board** — approved concerns visible to everyone (reduces duplicate reports)
- Big, always-visible **Submit Feedback** button

### Phase 3 — Feedback Submission (core feature)
- **Category pills**: Facility / Maintenance · Academic · Environment / Safety · Social / Behavioral · Others
- Subject, detailed description, **optional photo upload** (JPG/PNG/GIF/WEBP, max 5 MB)
- **"Submit as Anonymous" toggle** — admins cannot see the identity, but the report stays
  securely linked to the account so the student can still track their ticket

### Phase 4 — Tracking & Notifications
- Unique **ticket numbers** (TKT-001, TKT-002…)
- Status badges: **Pending, then Under Review, then Resolved** (+ animated timeline)
- **Two-way comment thread** — admin replies, user responds
- **In-app notifications** when a ticket is updated or a new reply arrives

### Phase 5 — Admin Panel
- Analytics dashboard with **bar charts by category, department, and assigned office**
- Ticket management with **search + filters** (status, category, office)
- Action menu: **change status · assign to a department · make public/private ·
  public reply / private note · delete ticket**

---

## Setup (XAMPP)

1. **Install XAMPP**: [apachefriends.org](https://www.apachefriends.org) (PHP 8+ recommended).
2. Copy this whole folder into `C:\xampp\htdocs\` and rename it to `oct-feedback-board`
   (no spaces — makes URLs clean: `http://localhost/oct-feedback-board/`).
3. Start **Apache** and **MySQL** in the XAMPP Control Panel.
4. **Create the database**:
   - Open **phpMyAdmin** > *Import* > choose `database.sql`, or
   - Command line: `mysql -u root -p < database.sql`
5. Optional — edit `config.php`:
   - Database credentials if yours differ (`DB_USER`, `DB_PASS`).
   - `SCHOOL_EMAIL_DOMAINS` to enforce school emails (e.g. `'oct.edu.ph,oct.edu'`).
   - `APP_URL` if you host under a different folder.
6. Open **http://localhost/oct-feedback-board/**

> **No XAMPP?** You can also run it with the PHP dev server:
> `php -S localhost:8000` (requires MySQL running with the `oct_feedback` database).

### Demo accounts (seeded in `database.sql`)

| Role    | Email                  | Password    |
| ------- | ---------------------- | ----------- |
| Admin   | `admin@oct.edu.ph`     | `admin123`  |
| Student | `juan.delacruz@oct.edu.ph` | `student123` |
| Student | `maria.santos@oct.edu.ph`  | `student123` |

The seed data also includes sample tickets in every status and category so the
admin dashboard charts are populated on the very first run.

### Starting fresh
The sample data is the `-- SEED DATA --` section at the bottom of `database.sql`.
Delete it, re-import, and you have a clean system with only the admin account.

---

## Database design

| Table             | Purpose                                                       |
| ----------------- | ------------------------------------------------------------- |
| `users`           | id, ID number, name, email, bcrypt password, department, role |
| `feedbacks`       | ticket_no, user, category, title, description, image, `is_anonymous`, status, assigned_dept, `is_public` |
| `comments`        | feedback, author, message, visibility (public/private)        |
| `password_resets` | one-time reset tokens                                         |
| `notifications`   | in-app alerts per user                                        |

---

## Branding

The app shows `olivarez-college-tagaytay-logo.png` (from the project root) in the
navbar and on the login/register screens. To use your own school logo, simply
replace that file (keep the same filename) — no code changes needed.

## Project structure

```
├── config.php                  # DB credentials, options, session + PDO
├── database.sql                # schema + seed data (import this)
├── index.php                   # landing page + public board
├── dashboard.php               # user dashboard (stats, my tickets, board)
├── submit.php                  # feedback form (categories, photo, anonymous)
├── ticket.php                  # user ticket detail + comment thread
├── notifications_read.php      # AJAX endpoint for the bell
├── auth/                       # login, register, forgot/reset, logout
├── admin/                      # dashboard (charts), tickets (filters), ticket (actions)
├── includes/                   # header/footer, functions, auth layout shells
├── assets/                     # css/style.css, js/main.js
└── uploads/                    # uploaded evidence images
```

---

## Security implemented (great for the defense!)

- **Password hashing** with bcrypt (`password_hash` / `password_verify`)
- **Prepared statements** everywhere (PDO) — SQL-injection safe
- **CSRF tokens** on every form
- **XSS protection** — all output escaped with `htmlspecialchars()`
- **Login throttling** against brute force
- **Secure sessions** (HttpOnly + SameSite cookies, ID regenerated on login)
- **Upload validation** — extension + MIME sniffing + size limits
- **Anonymous submissions** — identity hidden from admins, still traceable internally
- **Data Privacy Act (RA 10173)** compliance — terms consent at sign-up

## Capstone defense talking points

1. **Anonymous feature** — removes the psychological barrier to reporting sensitive issues
   (bullying, corruption, safety). Privacy is preserved *without* losing accountability.
2. **Data Privacy Act** — explicit consent at registration; anonymous reports never expose
   the identity; data is stored securely.
3. **Analytics** — the admin dashboard shows *which category and which department* produce
   the most issues, enabling data-driven decisions (the pro-tip from the blueprint).
4. **End-to-end tracking** — from ticket number to resolved status with a full
   communication trail (audit-friendly).
