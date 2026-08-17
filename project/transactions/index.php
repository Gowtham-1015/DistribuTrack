<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireLogin();

$db   = Database::getInstance();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

  // Add Distribution (creates a new invoice/bill)
if ($action === 'add_distribution') {
    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
    $cid    = (int)($_POST['customer_id'] ?? 0);
    $coid   = (int)($_POST['company_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');
    $today  = date('Y-m-d');

    if (!$invoiceNumber) {
        setFlash('error', 'Please enter an invoice number.');

    } elseif (!($cid && $coid && $amount > 0)) {
        setFlash('error', 'Please fill all required fields.');

    } else {
        // Verify that the selected customer actually exists
        $customerStmt = $db->prepare(
            "SELECT COUNT(*) as c FROM customers WHERE customer_id=?"
        );
        $customerStmt->bind_param('i', $cid);
        $customerStmt->execute();
        $customerExists = $customerStmt->get_result()->fetch_assoc()['c'];

        // Verify that the selected company actually exists
        $companyStmt = $db->prepare(
            "SELECT COUNT(*) as c FROM companies WHERE company_id=?"
        );
        $companyStmt->bind_param('i', $coid);
        $companyStmt->execute();
        $companyExists = $companyStmt->get_result()->fetch_assoc()['c'];

        if (!$customerExists) {
            setFlash(
                'error',
                'The selected customer no longer exists. Please refresh the page and select a valid customer.'
            );

        } elseif (!$companyExists) {
            setFlash(
                'error',
                'The selected company no longer exists. Please refresh the page and select a valid company.'
            );

        } else {
            // Check whether invoice number already exists
            $checkStmt = $db->prepare(
                "SELECT COUNT(*) as c FROM invoices WHERE invoice_number=?"
            );
            $checkStmt->bind_param('s', $invoiceNumber);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->fetch_assoc()['c'];

            if ($exists) {
                setFlash(
                    'error',
                    'Invoice number already exists. Please enter a different invoice number.'
                );

            } else {
                $db->beginTransaction();

                $stmt = $db->prepare(
                    "INSERT INTO invoices
                    (invoice_number, customer_id, company_id, amount, amount_paid, invoice_date, status, note)
                    VALUES (?, ?, ?, ?, 0, ?, 'OPEN', ?)"
                );
                $stmt->bind_param(
                    'siidss',
                    $invoiceNumber,
                    $cid,
                    $coid,
                    $amount,
                    $today,
                    $note
                );
                $stmt->execute();

                $invoiceId = $db->lastInsertId();

                $stmt = $db->prepare(
                    "INSERT INTO transactions
                    (customer_id, company_id, invoice_id, transaction_type, amount, transaction_date, note)
                    VALUES (?, ?, ?, 'CREDIT', ?, ?, ?)"
                );
                $stmt->bind_param(
                    'iiidss',
                    $cid,
                    $coid,
                    $invoiceId,
                    $amount,
                    $today,
                    $note
                );
                $stmt->execute();

                $db->commit();

                setFlash(
                    'success',
                    "Distribution recorded as invoice $invoiceNumber."
                );
            }
        }
    }
}
// Add Collection (pays down a specific invoice, found by invoice number)
if ($action === 'add_collection') {
    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
    $amount        = (float)($_POST['amount'] ?? 0);
    $note          = trim($_POST['note'] ?? '');
    $today         = date('Y-m-d');

    if (!$invoiceNumber) {
        setFlash('error', 'Please enter or select an invoice number.');
    } else {
        $stmt = $db->prepare(
            "SELECT * FROM invoices WHERE invoice_number=?"
        );
        $stmt->bind_param('s', $invoiceNumber);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();

        if (!$invoice) {
            setFlash('error', "Invoice '$invoiceNumber' was not found.");

        } elseif ($invoice['status'] !== 'OPEN') {
            setFlash(
                'error',
                "Invoice {$invoice['invoice_number']} is not open for collection (status: {$invoice['status']})."
            );

        } elseif ($amount <= 0) {
            setFlash('error', 'Amount must be greater than zero.');

        } else {
            $balance = $invoice['amount'] - $invoice['amount_paid'];

            if ($amount > $balance + 0.001) {
                setFlash(
                    'error',
                    'Amount exceeds the outstanding balance of ' .
                    formatCurrency($balance) .
                    ' for invoice ' .
                    $invoice['invoice_number'] .
                    '.'
                );
            } else {
                $newPaid  = $invoice['amount_paid'] + $amount;
                $isClosed = $newPaid >= $invoice['amount'] - 0.001;

                try {
                    $db->beginTransaction();

                    $status   = $isClosed ? 'CLOSED' : 'OPEN';
                    $closedAt = $isClosed ? date('Y-m-d H:i:s') : null;

                    // Update invoice payment information
                    $stmt = $db->prepare(
                        "UPDATE invoices
                         SET amount_paid=?,
                             status=?,
                             closed_at=?,
                             last_payment_date=?
                         WHERE invoice_id=?"
                    );

                    $stmt->bind_param(
                        'dsssi',
                        $newPaid,
                        $status,
                        $closedAt,
                        $today,
                        $invoice['invoice_id']
                    );

                    $stmt->execute();

                    // Record the collection transaction
                    $stmt = $db->prepare(
                        "INSERT INTO transactions
                         (customer_id, company_id, invoice_id, transaction_type, amount, transaction_date, note)
                         VALUES (?, ?, ?, 'COLLECTION', ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        'iiidss',
                        $invoice['customer_id'],
                        $invoice['company_id'],
                        $invoice['invoice_id'],
                        $amount,
                        $today,
                        $note
                    );

                    $stmt->execute();

                    $db->commit();

                    setFlash(
                        'success',
                        'Collection recorded against invoice ' .
                        $invoice['invoice_number'] .
                        ($isClosed
                            ? ' - bill fully settled.'
                            : '.')
                    );

                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    setFlash(
                        'error',
                        'Unable to record the collection. No changes were saved.'
                    );
                }
            }
        }
    }
}

    // Edit Distribution (updates the invoice)
    if ($action === 'edit_distribution') {
        $invoiceId     = (int)$_POST['invoice_id'];
        $invoiceNumber = trim($_POST['invoice_number'] ?? '');
        $amount        = (float)$_POST['amount'];
        $note          = trim($_POST['note'] ?? '');

        $stmt = $db->prepare("SELECT * FROM invoices WHERE invoice_id=?");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();

        $dupStmt = $db->prepare("SELECT COUNT(*) as c FROM invoices WHERE invoice_number=? AND invoice_id!=?");
        $dupStmt->bind_param('si', $invoiceNumber, $invoiceId);
        $dupStmt->execute();
        $dup = $dupStmt->get_result()->fetch_assoc()['c'];

        if (!$invoice) {
            setFlash('error', 'Invoice not found.');
        } elseif (!$invoiceNumber) {
            setFlash('error', 'Please enter an invoice number.');
        } elseif ($dup) {
            setFlash('error', 'Invoice number already exists. Please enter a different invoice number.');
        } elseif ($amount < $invoice['amount_paid']) {
            setFlash('error', 'New amount cannot be less than the ' . formatCurrency($invoice['amount_paid']) . ' already collected.');
        } else {
            $isClosed = $invoice['amount_paid'] >= $amount - 0.001 && $invoice['amount_paid'] > 0;
            $status   = $isClosed ? 'CLOSED' : 'OPEN';
            $closedAt = $isClosed ? ($invoice['closed_at'] ?: date('Y-m-d H:i:s')) : null;

            $db->beginTransaction();
            $stmt = $db->prepare("UPDATE invoices SET invoice_number=?, amount=?, note=?, status=?, closed_at=? WHERE invoice_id=?");
            $stmt->bind_param('sdsssi', $invoiceNumber, $amount, $note, $status, $closedAt, $invoiceId);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE transactions SET amount=?, note=? WHERE invoice_id=? AND transaction_type='CREDIT'");
            $stmt->bind_param('dsi', $amount, $note, $invoiceId);
            $stmt->execute();
            $db->commit();

            setFlash('success', 'Invoice ' . $invoiceNumber . ' updated.');
        }
    }

    // Delete Distribution (removes the invoice, only if unpaid)
    if ($action === 'delete_distribution') {
        $invoiceId = (int)$_POST['invoice_id'];
        $stmt = $db->prepare("SELECT * FROM invoices WHERE invoice_id=?");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();

        if (!$invoice) {
            setFlash('error', 'Invoice not found.');
        } elseif ($invoice['amount_paid'] > 0) {
            setFlash('error', 'Cannot delete invoice ' . $invoice['invoice_number'] . ' - it already has collections recorded against it.');
        } else {
            $db->beginTransaction();
            $stmt = $db->prepare("DELETE FROM transactions WHERE invoice_id=?");
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $stmt = $db->prepare("DELETE FROM invoices WHERE invoice_id=?");
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $db->commit();
            setFlash('success', 'Invoice ' . $invoice['invoice_number'] . ' deleted.');
        }
    }

    // Archive Distribution (manual, only when CLOSED)
    if ($action === 'archive_distribution') {
        $invoiceId = (int)$_POST['invoice_id'];
        $stmt = $db->prepare("SELECT * FROM invoices WHERE invoice_id=?");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();

        if (!$invoice) {
            setFlash('error', 'Invoice not found.');
        } elseif ($invoice['status'] !== 'CLOSED') {
            setFlash('error', 'Only fully settled (CLOSED) invoices can be archived.');
        } else {
            $stmt = $db->prepare("UPDATE invoices SET status='ARCHIVED', archived_at=datetime('now') WHERE invoice_id=?");
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            setFlash('success', 'Invoice ' . $invoice['invoice_number'] . ' archived.');
        }
    }

    // Delete Collection (reverses the payment on its invoice)
    if ($action === 'delete_collection') {
        $tid = (int)$_POST['transaction_id'];
        $stmt = $db->prepare("SELECT * FROM transactions WHERE transaction_id=? AND transaction_type='COLLECTION'");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $txn = $stmt->get_result()->fetch_assoc();

        if ($txn) {
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM transactions WHERE transaction_id=?");
            $stmt->bind_param('i', $tid);
            $stmt->execute();

            if ($txn['invoice_id']) {
                // Find the most recent remaining collection (if any) so the
                // aging clock reflects what's actually left, not the deleted one.
                $stmt = $db->prepare("SELECT MAX(transaction_date) as d FROM transactions WHERE invoice_id=? AND transaction_type='COLLECTION'");
                $stmt->bind_param('i', $txn['invoice_id']);
                $stmt->execute();
                $lastRemaining = $stmt->get_result()->fetch_assoc()['d'];

                $stmt = $db->prepare("UPDATE invoices SET amount_paid = amount_paid - ?, status='OPEN', closed_at=NULL, archived_at=NULL, last_payment_date=? WHERE invoice_id=?");
                $stmt->bind_param('dsi', $txn['amount'], $lastRemaining, $txn['invoice_id']);
                $stmt->execute();
            }

            $db->commit();
            setFlash('success', 'Collection deleted and invoice balance restored.');
        }
    }

    header('Location: index.php' . (isset($_POST['redirect_type']) ? '?type=' . urlencode($_POST['redirect_type']) : ''));
    exit();
}

// GET / display
$filterCustomer = (int)($_GET['customer_id'] ?? 0);
$filterCompany  = (int)($_GET['company_id'] ?? 0);
$filterType     = $_GET['type'] ?? '';
$filterFrom     = $_GET['from'] ?? '';
$filterTo       = $_GET['to'] ?? '';

$custList = []; $compList = [];
$r = $conn->query("SELECT * FROM customers ORDER BY customer_name");
while ($row = $r->fetch_assoc()) $custList[] = $row;
$r = $conn->query("SELECT * FROM companies ORDER BY company_name");
while ($row = $r->fetch_assoc()) $compList[] = $row;

if ($filterType === 'CREDIT') {
    $pageTitle = 'Distribution';
    $pageIcon  = 'bi-arrow-up-circle';
    $pageSub   = 'Bills issued to customers, tracked to settlement';
} elseif ($filterType === 'COLLECTION') {
    $pageTitle = 'Collections';
    $pageIcon  = 'bi-arrow-down-circle';
    $pageSub   = 'Payments applied against open invoices';
} else {
    $pageTitle = 'Transactions';
    $pageIcon  = 'bi-arrow-left-right';
    $pageSub   = 'Full credits and collections ledger';
}

if ($filterType === 'CREDIT') {
    $where = ["i.status != 'ARCHIVED'"]; $params = []; $types = '';
    if ($filterCustomer) { $where[] = "i.customer_id=?"; $params[] = $filterCustomer; $types .= 'i'; }
    if ($filterCompany)  { $where[] = "i.company_id=?";  $params[] = $filterCompany;  $types .= 'i'; }
    if ($filterFrom) { $where[] = "i.invoice_date>=?"; $params[] = $filterFrom; $types .= 's'; }
    if ($filterTo)   { $where[] = "i.invoice_date<=?"; $params[] = $filterTo;   $types .= 's'; }
    $whereStr = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT i.*, c.customer_name, co.company_name, (i.amount - i.amount_paid) as balance
        FROM invoices i
        JOIN customers c  ON i.customer_id = c.customer_id
        JOIN companies co ON i.company_id  = co.company_id
        WHERE $whereStr
        ORDER BY i.invoice_date DESC, i.invoice_id DESC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $invoiceRows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $invoiceRows[] = $row;

} elseif ($filterType === 'COLLECTION') {
    $where = ["t.transaction_type='COLLECTION'"]; $params = []; $types = '';
    if ($filterCustomer) { $where[] = "t.customer_id=?"; $params[] = $filterCustomer; $types .= 'i'; }
    if ($filterCompany)  { $where[] = "t.company_id=?";  $params[] = $filterCompany;  $types .= 'i'; }
    if ($filterFrom) { $where[] = "t.transaction_date>=?"; $params[] = $filterFrom; $types .= 's'; }
    if ($filterTo)   { $where[] = "t.transaction_date<=?"; $params[] = $filterTo;   $types .= 's'; }
    $whereStr = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT t.*, c.customer_name, co.company_name, i.invoice_number
        FROM transactions t
        JOIN customers c  ON t.customer_id = c.customer_id
        JOIN companies co ON t.company_id  = co.company_id
        LEFT JOIN invoices i ON t.invoice_id = i.invoice_id
        WHERE $whereStr
        ORDER BY t.transaction_date DESC, t.transaction_id DESC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $transactions = $stmt->get_result();

    $openInvoices = [];
    $res = $conn->query("
        SELECT i.*, c.customer_name, co.company_name, (i.amount - i.amount_paid) as balance
        FROM invoices i
        JOIN customers c  ON i.customer_id = c.customer_id
        JOIN companies co ON i.company_id  = co.company_id
        WHERE i.status='OPEN'
        ORDER BY i.invoice_number ASC
    ");
    while ($row = $res->fetch_assoc()) $openInvoices[] = $row;

} else {
    $where  = ['1=1']; $params = []; $types = '';
    if ($filterCustomer) { $where[] = "t.customer_id=?"; $params[] = $filterCustomer; $types .= 'i'; }
    if ($filterCompany)  { $where[] = "t.company_id=?";  $params[] = $filterCompany;  $types .= 'i'; }
    if ($filterFrom) { $where[] = "t.transaction_date>=?"; $params[] = $filterFrom; $types .= 's'; }
    if ($filterTo)   { $where[] = "t.transaction_date<=?"; $params[] = $filterTo;   $types .= 's'; }
    $whereStr = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT t.*, c.customer_name, co.company_name, i.invoice_number
        FROM transactions t
        JOIN customers c  ON t.customer_id = c.customer_id
        JOIN companies co ON t.company_id  = co.company_id
        LEFT JOIN invoices i ON t.invoice_id = i.invoice_id
        WHERE $whereStr
        ORDER BY t.transaction_date DESC, t.transaction_id DESC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $transactions = $stmt->get_result();
}

include '../includes/header.php';
?>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>
        <div class="page-body">

            <div class="page-header">
                <div>
                    <div class="page-header-title"><i class="bi <?= $pageIcon ?>"></i> <?= htmlspecialchars($pageTitle) ?></div>
                    <div class="page-header-sub"><?= htmlspecialchars($pageSub) ?></div>
                </div>
                <div class="d-flex gap-2">
                    <a href="../reports/date_wise.php" class="btn btn-outline-secondary">
                        <i class="bi bi-calendar3"></i> Date Report
                    </a>
                    <?php if ($filterType === 'CREDIT'): ?>
                    <a href="../archive/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-archive"></i> Archive
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDistModal">
                        <i class="bi bi-plus-lg"></i> Add Distribution
                    </button>
                    <?php elseif ($filterType === 'COLLECTION'): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollModal" <?= empty($openInvoices) ? 'disabled title="No open invoices to collect against"' : '' ?>>
                        <i class="bi bi-plus-lg"></i> Add Collection
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <form method="GET" class="filter-bar mb-4">
                <?php if ($filterType): ?><input type="hidden" name="type" value="<?= htmlspecialchars($filterType) ?>"><?php endif; ?>
                <div style="flex:1;min-width:140px;">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        <?php foreach($custList as $c): ?>
                        <option value="<?= $c['customer_id'] ?>" <?= $filterCustomer==$c['customer_id']?'selected':'' ?>><?= htmlspecialchars($c['customer_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:140px;">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select">
                        <option value="">All Companies</option>
                        <?php foreach($compList as $c): ?>
                        <option value="<?= $c['company_id'] ?>" <?= $filterCompany==$c['company_id']?'selected':'' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!$filterType): ?>
                <div style="min-width:130px;">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="CREDIT">Credit</option>
                        <option value="COLLECTION">Collection</option>
                    </select>
                </div>
                <?php endif; ?>
                <div style="min-width:130px;">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filterFrom) ?>">
                </div>
                <div style="min-width:130px;">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filterTo) ?>">
                </div>
                <div style="align-self:flex-end;display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                    <a href="index.php<?= $filterType ? '?type='.$filterType : '' ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i> Clear</a>
                </div>
            </form>

            <?php if ($filterType === 'CREDIT'): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Distribution Invoices</h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToPDF('invTable','Distribution','distribution.pdf')"><i class="bi bi-filetype-pdf"></i></button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToExcel('invTable','Distribution','distribution.xlsx')"><i class="bi bi-filetype-xlsx"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dt-table" id="invTable">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Company</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Days Outstanding</th>
                                    <th>Status</th>
                                    <th>Note</th>
                                    <th class="no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($invoiceRows as $inv): $days = daysOutstanding($inv['invoice_date'], $inv['closed_at'], $inv['status'], $inv['last_payment_date']); ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                                    <td><a href="../customers/view.php?id=<?= $inv['customer_id'] ?>" style="color:var(--accent);text-decoration:none;"><?= htmlspecialchars($inv['customer_name']) ?></a></td>
                                    <td><?= htmlspecialchars($inv['company_name']) ?></td>
                                    <td class="amount-credit">+<?= formatCurrency($inv['amount']) ?></td>
                                    <td class="amount-collection"><?= formatCurrency($inv['amount_paid']) ?></td>
                                    <td class="<?= $inv['balance'] > 0 ? 'amount-neutral' : 'amount-collection' ?>"><?= formatCurrency($inv['balance']) ?></td>
                                    <td class="<?= ($inv['status']==='OPEN' && $days > 30) ? 'text-danger fw-display' : '' ?>"><?= $days ?> Day<?= $days == 1 ? '' : 's' ?></td>
                                    <td>
                                        <?php if ($inv['status'] === 'CLOSED'): ?>
                                            <span class="badge-collection">CLOSED</span>
                                        <?php else: ?>
                                            <span style="background:var(--accent-dim);color:#8a6c00;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;">OPEN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12.5px;color:var(--text-muted);max-width:180px;"><?= htmlspecialchars($inv['note'] ?: '-') ?></td>
                                    <td class="no-export">
                                        <button class="btn btn-outline-secondary btn-sm btn-icon" onclick='editInvoice(<?= json_encode($inv) ?>)' title="Edit"><i class="bi bi-pencil"></i></button>
                                        <?php if ($inv['status'] === 'CLOSED'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="archive_distribution">
                                            <input type="hidden" name="redirect_type" value="CREDIT">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['invoice_id'] ?>">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm btn-icon" title="Archive"><i class="bi bi-archive"></i></button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="action" value="delete_distribution">
                                            <input type="hidden" name="redirect_type" value="CREDIT">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['invoice_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm btn-icon" <?= $inv['amount_paid']>0 ? 'title="Has payments - cannot delete" disabled' : '' ?>><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            </tbody>
                        </table>
                    </div>
                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:10px;">
                        <i class="bi bi-info-circle"></i> Settled invoices move to the <a href="../archive/index.php" style="color:var(--accent);">Archive</a> automatically 60 days after closing, or you can archive them manually. Nothing is ever deleted.
                    </p>
                </div>
            </div>

            <?php elseif ($filterType === 'COLLECTION'): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Collections</h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToPDF('collTable','Collections','collections.pdf')"><i class="bi bi-filetype-pdf"></i></button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToExcel('collTable','Collections','collections.xlsx')"><i class="bi bi-filetype-xlsx"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dt-table" id="collTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Company</th>
                                    <th>Invoice #</th>
                                    <th>Amount</th>
                                    <th>Note</th>
                                    <th class="no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($t = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $t['transaction_id'] ?></td>
                                    <td><?= formatDate($t['transaction_date']) ?></td>
                                    <td><a href="../customers/view.php?id=<?= $t['customer_id'] ?>" style="color:var(--accent);text-decoration:none;"><?= htmlspecialchars($t['customer_name']) ?></a></td>
                                    <td><?= htmlspecialchars($t['company_name']) ?></td>
                                    <td><?= $t['invoice_number'] ? htmlspecialchars($t['invoice_number']) : '<span style="color:var(--text-muted);">-</span>' ?></td>
                                    <td class="amount-collection">-<?= formatCurrency($t['amount']) ?></td>
                                    <td style="font-size:12.5px;color:var(--text-muted);"><?= htmlspecialchars($t['note'] ?: '-') ?></td>
                                    <td class="no-export">
                                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="action" value="delete_collection">
                                            <input type="hidden" name="redirect_type" value="COLLECTION">
                                            <input type="hidden" name="transaction_id" value="<?= $t['transaction_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete (restores invoice balance)"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Transaction Records</h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToPDF('txnTable','Transactions','transactions.pdf')"><i class="bi bi-filetype-pdf"></i></button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToExcel('txnTable','Transactions','transactions.xlsx')"><i class="bi bi-filetype-xlsx"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dt-table" id="txnTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Company</th>
                                    <th>Invoice #</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($t = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $t['transaction_id'] ?></td>
                                    <td><?= formatDate($t['transaction_date']) ?></td>
                                    <td><a href="../customers/view.php?id=<?= $t['customer_id'] ?>" style="color:var(--accent);text-decoration:none;"><?= htmlspecialchars($t['customer_name']) ?></a></td>
                                    <td><?= htmlspecialchars($t['company_name']) ?></td>
                                    <td><?= $t['invoice_number'] ? htmlspecialchars($t['invoice_number']) : '-' ?></td>
                                    <td><span class="badge-<?= strtolower($t['transaction_type']) ?>"><?= $t['transaction_type'] ?></span></td>
                                    <td class="<?= $t['transaction_type']==='CREDIT'?'amount-credit':'amount-collection' ?>">
                                        <?= ($t['transaction_type']==='CREDIT'?'+':'-').formatCurrency($t['amount']) ?>
                                    </td>
                                    <td style="font-size:12.5px;color:var(--text-muted);"><?= htmlspecialchars($t['note'] ?: '-') ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php if ($filterType === 'CREDIT'): ?>
<div class="modal fade" id="addDistModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_distribution">
                <input type="hidden" name="redirect_type" value="CREDIT">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Distribution (New Invoice)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Invoice Number *</label>
                            <input type="text" name="invoice_number" class="form-control" placeholder="e.g. INV-1009" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (Rs.) *</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customer *</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Select customer...</option>
                                <?php foreach($custList as $c): ?>
                                <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['customer_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company *</label>
                            <select name="company_id" class="form-select" required>
                                <option value="">Select company...</option>
                                <?php foreach($compList as $c): ?>
                                <option value="<?= $c['company_id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Note</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="Delivery info, remarks, special instructions..."></textarea>
                        </div>
                    </div>
                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:12px;margin-bottom:0;">
                        <i class="bi bi-info-circle"></i> Issue date is recorded automatically.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDistModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_distribution">
                <input type="hidden" name="redirect_type" value="CREDIT">
                <input type="hidden" name="invoice_id" id="ei_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Invoice Number *</label>
                            <input type="text" name="invoice_number" id="ei_invoice_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (Rs.) *</label>
                            <input type="number" name="amount" id="ei_amount" class="form-control" step="0.01" min="0.01" required>
                            <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;" id="ei_paid_hint"></div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Note</label>
                            <textarea name="note" id="ei_note" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function editInvoice(d) {
    document.getElementById('ei_id').value = d.invoice_id;
    document.getElementById('ei_invoice_number').value = d.invoice_number;
    document.getElementById('ei_amount').value = d.amount;
    document.getElementById('ei_note').value = d.note || '';
    document.getElementById('ei_paid_hint').textContent = 'Already collected: Rs. ' + parseFloat(d.amount_paid).toFixed(2) + ' - amount cannot go below this.';
    new bootstrap.Modal(document.getElementById('editDistModal')).show();
}
</script>

<?php elseif ($filterType === 'COLLECTION'): ?>
<div class="modal fade" id="addCollModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_collection">
                <input type="hidden" name="redirect_type" value="COLLECTION">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Collection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Invoice Number *</label>
                            <input type="text" name="invoice_number" id="coll_invoice_number" class="form-control"
                                   list="openInvoicesList" placeholder="Start typing an invoice number..."
                                   autocomplete="off" required oninput="lookupInvoice()">
                            <datalist id="openInvoicesList">
                                <?php foreach($openInvoices as $inv): ?>
                                <option value="<?= htmlspecialchars($inv['invoice_number']) ?>">
                                    <?= htmlspecialchars($inv['customer_name']) ?> - Balance: <?= formatCurrency($inv['balance']) ?>
                                </option>
                                <?php endforeach; ?>
                            </datalist>
                            <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;" id="coll_lookup_hint"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Collection Amount (Rs.) *</label>
                            <input type="number" name="amount" id="coll_amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Note</label>
                            <input type="text" name="note" class="form-control" placeholder="Cash / bank transfer / cheque...">
                        </div>
                    </div>
                    <p style="font-size:11.5px;color:var(--text-muted);margin-top:12px;margin-bottom:0;">
                        <i class="bi bi-info-circle"></i> Collection date is recorded automatically.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Collection</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const openInvoicesData = <?= json_encode($openInvoices) ?>;

function lookupInvoice() {
    const typed = document.getElementById('coll_invoice_number').value.trim().toLowerCase();
    const hint = document.getElementById('coll_lookup_hint');
    const amountInput = document.getElementById('coll_amount');
    const match = openInvoicesData.find(inv => inv.invoice_number.toLowerCase() === typed);

    if (match) {
        hint.textContent = match.customer_name + ' (' + match.company_name + ') - Outstanding balance: Rs. ' + parseFloat(match.balance).toFixed(2);
        hint.style.color = '';
        amountInput.max = match.balance;
    } else if (typed) {
        hint.textContent = 'No open invoice found matching "' + document.getElementById('coll_invoice_number').value + '"';
        hint.style.color = 'var(--red)';
        amountInput.removeAttribute('max');
    } else {
        hint.textContent = '';
        amountInput.removeAttribute('max');
    }
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
