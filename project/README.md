# DistribuTrack — Distribution Credit & Collection Management System

## Quick Setup

### Requirements
- PHP 7.4+ with the **pdo_sqlite** extension (bundled with PHP by default)
- Apache/Nginx, or Electron's bundled PHP server
- No MySQL/MariaDB server required — the app is fully offline/self-contained

### Installation Steps

1. **Copy to server**: Place the `/project` folder in your web root (e.g., `htdocs/project` for XAMPP), or point Electron's PHP process at it.

2. **Just run it** — no installer step needed. The first request automatically:
   - Creates `project/database/` if it doesn't exist
   - Creates the SQLite file at `project/database/distribu_track.db`
   - Runs `project/database/schema.sql` to build all tables and seed sample data

3. **Login**: Go to `http://localhost/project/index.php`
   - Username: `admin`
   - Password: `admin123`
   - Change this password from **Settings** after first login.

---

## What Changed In This Redesign

- **Database**: MySQL/mysqli → **SQLite** (via PDO), stored at `database/distribu_track.db`. All queries touching user input now run through prepared statements.
- **Removed**: Customer Report and Company Report pages, their sidebar links, dashboard shortcuts, and PHP files. Their functionality now lives in the Customer/Company management pages themselves (view details + Print + PDF/Excel export).
- **Theme**: New navy & gold palette (`#001D3D` sidebar, `#003566` navbar, `#CCA000` buttons, `#F0CB46` hover, `#000814` page background, white cards).
- **Dashboard**: Simplified to 5 core stats — Total Customers, Total Companies, Total Distributions, Total Collections, Outstanding Balance — plus a compact recent-activity list. The old monthly chart widget was removed.
- **Sidebar**: Trimmed to Dashboard, Customers, Companies, Distribution, Collections, Users, Settings.
- **New pages**: `users/index.php` (admin-only user management) and `settings/index.php` (profile + password) so the sidebar's Users/Settings links are fully functional, not placeholders.
- **Transactions**: One shared page now serves both **Distribution** (`?type=CREDIT`) and **Collections** (`?type=COLLECTION`) from the sidebar; the original combined Transactions view is still reachable without a type filter.
- **Date-wise report**: Kept (not requested for removal) and reachable via a "Date Report" button on the Distribution/Collections page, since it isn't one of the sidebar's listed items.

## Features

| Module | Features |
|--------|----------|
| Dashboard | 5 key stats + recent activity |
| Companies | Add/Edit/Delete/Search, detail view with Print + PDF/Excel export |
| Customers | Add/Edit/Delete/Search, ledger view with running balance, Print + PDF/Excel export |
| Distribution / Collections | Add/Edit/Delete, filter by customer/company/date/type |
| Users | Add/Edit/Delete (admin only) |
| Settings | Update profile, change password |
| Reports | Date-wise report (linked from Distribution/Collections) |

## Folder Structure

```
/project
├── index.php              ← Login page
├── dashboard.php           ← Simplified dashboard
├── logout.php
├── config/
│   ├── database.php        ← SQLite (PDO) connection + mysqli-style compatibility layer
│   └── auth.php             ← Session & helper functions
├── database/
│   ├── schema.sql           ← SQLite schema & seed data (auto-run on first launch)
│   └── distribu_track.db    ← Created automatically at runtime
├── includes/
│   ├── header.php
│   ├── sidebar.php          ← Trimmed nav
│   ├── topbar.php
│   └── footer.php
├── companies/
│   ├── index.php
│   └── view.php             ← Company detail (replaces Company Report)
├── customers/
│   ├── index.php
│   └── view.php             ← Customer ledger
├── transactions/
│   └── index.php            ← Serves Distribution, Collections, and combined views
├── users/
│   └── index.php            ← User management (admin only)
├── settings/
│   └── index.php            ← Profile & password
├── reports/
│   └── date_wise.php         ← Kept; linked from Distribution/Collections
└── assets/
    ├── css/main.css          ← New navy & gold theme
    └── js/main.js
```

## Balance Logic
- **Credit (Distribution)** = Customer owes money (balance increases)
- **Collection** = Customer pays back (balance decreases)
- **Formula**: `Balance = Σ Credits − Σ Collections`
- Red figures = customer owes money
- Green figures = overpaid or cleared

## Security Notes
- Change the default admin password from **Settings** after first login
- All user-supplied query values use prepared statements
- Use HTTPS in production
- Consider adding CSRF tokens for forms in production

## Note on Electron packaging
This zip contains the PHP application only (no Electron `main.js`/`package.json` was present in the uploaded project). If your Electron shell spawns a PHP server and points its working directory at `/project`, no changes are needed there — the app now manages its own SQLite file under `project/database/` instead of requiring a MySQL server, which is what makes it truly work offline as a packaged desktop app.
