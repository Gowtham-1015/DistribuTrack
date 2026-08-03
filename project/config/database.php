<?php
/**
 * DistribuTrack — SQLite Database Layer
 * ---------------------------------------------------------------
 * Replaces the old MySQL/mysqli connection with a single reusable
 * PDO SQLite connection. The database file is created automatically
 * (along with its schema) the first time the app runs.
 *
 * A thin mysqli-style shim (Database/DBStatement/DBResult) is kept
 * so the rest of the application's existing query/prepare/bind_param/
 * fetch_assoc calls continue to work unchanged, while every query
 * underneath actually runs through PDO prepared statements.
 */

define('DB_PATH', __DIR__ . '/../database/distribu_track.db');
define('DB_SCHEMA', __DIR__ . '/../database/schema.sql');

class Database {
    private static $instance = null;
    /** @var PDO */
    private $pdo;
    private $lastAffected = 0;

    private function __construct() {
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Connection failed: ' . $e->getMessage()]));
        }

        $this->ensureSchema();
    }

    /**
     * Auto-creates all tables (and seed data) the first time the
     * database is used, so no manual installer step is required.
     * Also runs small additive migrations for databases created by
     * an earlier version of the app (e.g. adding the invoices table
     * and transactions.invoice_id to a pre-existing database), and
     * purges invoices that have been fully paid for 60+ days.
     */
    private function ensureSchema() {
        $exists = $this->pdo
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")
            ->fetch();

        if (!$exists) {
            $schema = file_get_contents(DB_SCHEMA);
            if ($schema === false) {
                die(json_encode(['error' => 'Missing database/schema.sql']));
            }
            $this->pdo->exec($schema);
        }

        $this->migrate();
        $this->purgeExpiredInvoices();
    }

    /**
     * Additive, idempotent migrations for databases created before a
     * given feature existed. Safe to run on every request.
     */
    private function migrate() {
        $hasInvoices = $this->pdo
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name='invoices'")
            ->fetch();

        if (!$hasInvoices) {
            $this->pdo->exec("
                CREATE TABLE invoices (
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
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoice_customer ON invoices(customer_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoice_company  ON invoices(company_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoice_status   ON invoices(status)");
        }

        $cols = $this->pdo->query("PRAGMA table_info(transactions)")->fetchAll(PDO::FETCH_ASSOC);
        $hasInvoiceId = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'invoice_id') { $hasInvoiceId = true; break; }
        }
        if (!$hasInvoiceId) {
            $this->pdo->exec("ALTER TABLE transactions ADD COLUMN invoice_id INTEGER REFERENCES invoices(invoice_id) ON DELETE SET NULL");
        }
    }

    /**
     * Deletes any invoice that has been fully paid (status=CLOSED) for
     * 60 days or more. Linked ledger transactions are kept for
     * accounting history — only the invoice/bill record itself is
     * removed (transactions.invoice_id is set to NULL automatically).
     */
    public function purgeExpiredInvoices() {
        $this->pdo->exec("
            DELETE FROM invoices
            WHERE status = 'CLOSED'
              AND closed_at IS NOT NULL
              AND datetime(closed_at) <= datetime('now', '-60 days')
        ");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Mirrors the old mysqli "connection" object: returns something
     * with ->query() / ->prepare() so existing `$conn = $db->getConnection();`
     * call sites keep working exactly as before.
     */
    public function getConnection() {
        return $this;
    }

    /** Raw PDO instance, for anything that needs it directly. */
    public function getPDO() {
        return $this->pdo;
    }

    /**
     * Run a plain (no user-input) SQL statement, mysqli-style.
     * Returns a DBResult so ->fetch_assoc() keeps working.
     */
    public function query($sql) {
        $stmt = $this->pdo->query($sql);
        return new DBResult($stmt);
    }

    /**
     * Prepared statement — always the safe path for any query that
     * includes user-supplied values, preventing SQL injection.
     */
    public function prepare($sql) {
        return new DBStatement($this->pdo->prepare($sql), $this);
    }

    public function escape($value) {
        // Kept for backwards compatibility with any legacy call sites.
        return substr($this->pdo->quote($value), 1, -1);
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function affectedRows() {
        return $this->lastAffected;
    }

    public function setLastAffected($n) {
        $this->lastAffected = $n;
    }

    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollBack() { return $this->pdo->rollBack(); }
}

/**
 * mysqli_stmt-compatible wrapper around PDOStatement.
 */
class DBStatement {
    private $stmt;
    private $db;
    private $params = [];

    public function __construct(PDOStatement $stmt, Database $db) {
        $this->stmt = $stmt;
        $this->db   = $db;
    }

    /**
     * Mirrors mysqli's bind_param('types', $a, $b, ...). The type
     * string is accepted for API compatibility but not required by
     * PDO/SQLite, which binds values dynamically.
     */
    public function bind_param($types, ...$vars) {
        $this->params = $vars;
        return true;
    }

    public function execute($params = null) {
        $ok = $this->stmt->execute($params ?? $this->params);
        $this->db->setLastAffected($this->stmt->rowCount());
        return $ok;
    }

    public function get_result() {
        return new DBResult($this->stmt);
    }
}

/**
 * mysqli_result-compatible wrapper around PDOStatement.
 */
class DBResult {
    private $stmt;

    public function __construct(PDOStatement $stmt) {
        $this->stmt = $stmt;
    }

    public function fetch_assoc() {
        $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function fetch_all() {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
