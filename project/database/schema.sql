-- DistribuTrack — SQLite Schema & Seed Data
-- Auto-executed once by config/database.php when database/distribu_track.db
-- does not yet contain the expected tables. Safe to re-run (IF NOT EXISTS).

PRAGMA foreign_keys = ON;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    full_name TEXT NOT NULL,
    email TEXT,
    role TEXT NOT NULL DEFAULT 'staff' CHECK (role IN ('admin','staff')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Companies table
CREATE TABLE IF NOT EXISTS companies (
    company_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
    customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_name TEXT NOT NULL,
    phone TEXT,
    address TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Invoices table (one row per "bill" issued via Distribution)
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number TEXT NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    company_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    amount_paid REAL NOT NULL DEFAULT 0,
    invoice_date TEXT NOT NULL,
    due_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'OPEN' CHECK (status IN ('OPEN','CLOSED')),
    closed_at TEXT,
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_invoice_customer ON invoices(customer_id);
CREATE INDEX IF NOT EXISTS idx_invoice_company  ON invoices(company_id);
CREATE INDEX IF NOT EXISTS idx_invoice_status    ON invoices(status);

-- Transactions table (CREDIT = Distribution, COLLECTION = Collection)
-- invoice_id links a transaction to the bill it created (CREDIT) or paid down (COLLECTION)
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    company_id INTEGER NOT NULL,
    invoice_id INTEGER,
    transaction_type TEXT NOT NULL CHECK (transaction_type IN ('CREDIT','COLLECTION')),
    amount REAL NOT NULL,
    transaction_date TEXT NOT NULL,
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_txn_customer ON transactions(customer_id);
CREATE INDEX IF NOT EXISTS idx_txn_company  ON transactions(company_id);
CREATE INDEX IF NOT EXISTS idx_txn_date     ON transactions(transaction_date);

-- Default admin user (password: admin123)
INSERT OR IGNORE INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$hTOaYwDhS1Ds/RN.b6ABQee/73fWKsGYRWKxV3I2hi57Vt31rfQA.', 'Administrator', 'admin@distribution.com', 'admin');

-- Sample companies
INSERT OR IGNORE INTO companies (company_id, company_name) VALUES
(1, 'Alpha Trading Co.'),
(2, 'Beta Distributors'),
(3, 'Gamma Supplies Ltd.');

-- Sample customers
INSERT OR IGNORE INTO customers (customer_id, customer_name, phone, address) VALUES
(1, 'Mohammed Rashid', '0771234567', '45 Main Street, Colombo 03'),
(2, 'Priya Wickramasinghe', '0779876543', '12 Temple Road, Kandy'),
(3, 'Suresh Kumar', '0762345678', '78 Lake View, Galle'),
(4, 'Fatima Nazar', '0754321098', '23 Beach Road, Negombo');

-- Sample invoices (bills) — due 30 days after issue
INSERT OR IGNORE INTO invoices (invoice_id, invoice_number, customer_id, company_id, amount, amount_paid, invoice_date, due_date, status, closed_at, note) VALUES
(1, 'INV-0001', 1, 1, 15000.00, 5000.00, date('now','-5 day'), date('now','-5 day','+30 day'), 'OPEN',   NULL, 'Invoice #1001'),
(2, 'INV-0002', 1, 2, 8500.00,  0.00,    date('now','-4 day'), date('now','-4 day','+30 day'), 'OPEN',   NULL, 'Invoice #1002'),
(3, 'INV-0003', 2, 1, 22000.00, 10000.00,date('now','-7 day'), date('now','-7 day','+30 day'), 'OPEN',   NULL, 'Invoice #1003'),
(4, 'INV-0004', 3, 2, 5500.00,  0.00,    date('now','-6 day'), date('now','-6 day','+30 day'), 'OPEN',   NULL, 'Invoice #1004'),
(5, 'INV-0005', 3, 3, 12000.00, 12000.00,date('now','-8 day'), date('now','-8 day','+30 day'), 'CLOSED', datetime('now','-1 day'), 'Invoice #1005'),
(6, 'INV-0006', 4, 2, 9000.00,  0.00,    date('now'),          date('now','+30 day'),          'OPEN',   NULL, 'Invoice #1006'),
(7, 'INV-0007', 4, 3, 3500.00,  0.00,    date('now'),          date('now','+30 day'),          'OPEN',   NULL, 'Invoice #1007'),
(8, 'INV-0008', 1, 1, 7000.00,  0.00,    date('now'),          date('now','+30 day'),          'OPEN',   NULL, 'Invoice #1008');

-- Sample transactions (ledger), each linked to its invoice
INSERT OR IGNORE INTO transactions (customer_id, company_id, invoice_id, transaction_type, amount, transaction_date, note) VALUES
(1, 1, 1, 'CREDIT',      15000.00, date('now','-5 day'), 'Invoice #1001'),
(1, 1, 1, 'COLLECTION',   5000.00, date('now','-3 day'), 'Partial payment'),
(1, 2, 2, 'CREDIT',       8500.00, date('now','-4 day'), 'Invoice #1002'),
(2, 1, 3, 'CREDIT',      22000.00, date('now','-7 day'), 'Invoice #1003'),
(2, 1, 3, 'COLLECTION',  10000.00, date('now','-2 day'), 'Payment received'),
(3, 2, 4, 'CREDIT',       5500.00, date('now','-6 day'), 'Invoice #1004'),
(3, 3, 5, 'CREDIT',      12000.00, date('now','-8 day'), 'Invoice #1005'),
(3, 3, 5, 'COLLECTION',  12000.00, date('now','-1 day'), 'Full payment'),
(4, 2, 6, 'CREDIT',       9000.00, date('now'),          'Invoice #1006'),
(4, 3, 7, 'CREDIT',       3500.00, date('now'),          'Invoice #1007'),
(1, 1, 8, 'CREDIT',       7000.00, date('now'),          'Invoice #1008'),
(2, 2, NULL, 'COLLECTION', 5000.00, date('now'),          'Unallocated legacy collection');
