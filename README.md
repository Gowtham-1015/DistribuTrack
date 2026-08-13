# DistribuTrack

## Offline Distribution Credit & Collection Management System

DistribuTrack is an offline desktop application designed for distribution businesses to manage credit sales, invoices, customer payments, and outstanding balances.

The application uses an Electron desktop shell with a PHP backend and SQLite database, allowing it to work completely offline without requiring MySQL or an internet connection.

---

## Features

### Customer Management
- Add, edit, delete, and search customers
- View customer transaction history
- Track outstanding balances

### Company Management
- Manage supplier/company information
- View company-wise customer balances
- Track company-related transactions

### Invoice / Distribution Management
- Create distribution invoices
- Track credit sales
- Manage invoice status
- Monitor remaining balances

### Collection Management
- Record customer payments
- Link payments to specific invoices
- Prevent overpayments
- Automatically update invoice balances

### Dashboard
- Total customers
- Total companies
- Total distributions
- Total collections
- Outstanding balance summary

### User Management
- Secure login system
- Admin and staff roles
- Password encryption using bcrypt

---

## Technology Stack

### Desktop
- Electron

### Backend
- PHP 7.4+

### Database
- SQLite (PDO)

### Frontend
- PHP Server Rendering
- Bootstrap 5
- Bootstrap Icons
- Vanilla JavaScript
- DataTables

---

## Project Structure
-DistribuTrack/
          │
          ├── main.js # Electron main process
          ├── package.json # Electron configuration
          ├── php/ # Embedded PHP runtime
          │
          └── project/ # PHP application
          ├── config/
          ├── customers/
          ├── companies/
          ├── transactions/
          ├── users/
          ├── reports/
          ├── assets/
          └── database/
