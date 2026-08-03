<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit(); }

$db   = Database::getInstance();
$conn = $db->getConnection();

$customer = $conn->query("SELECT * FROM customers WHERE customer_id=$id")->fetch_assoc();
if (!$customer) { header('Location: index.php'); exit(); }

$pageTitle = 'Ledger: ' . $customer['customer_name'];

// All transactions ordered by date
$transactions = $conn->query("
    SELECT t.*, co.company_name, i.invoice_number, i.due_date
    FROM transactions t
    JOIN companies co ON t.company_id = co.company_id
    LEFT JOIN invoices i ON t.invoice_id = i.invoice_id
    WHERE t.customer_id = $id
    ORDER BY t.transaction_date ASC, t.transaction_id ASC
");

// Build running balance
$txnRows = [];
$running = 0;
while ($row = $transactions->fetch_assoc()) {
    if ($row['transaction_type'] === 'CREDIT') {
        $running += $row['amount'];
    } else {
        $running -= $row['amount'];
    }
    $row['running_balance'] = $running;
    $txnRows[] = $row;
}

// Company-wise balance
$companyBalances = $conn->query("
    SELECT co.company_name,
    COALESCE(SUM(CASE WHEN t.transaction_type='CREDIT' THEN t.amount ELSE 0 END),0) as credit,
    COALESCE(SUM(CASE WHEN t.transaction_type='COLLECTION' THEN t.amount ELSE 0 END),0) as collection,
    COALESCE(SUM(CASE WHEN t.transaction_type='CREDIT' THEN t.amount ELSE -t.amount END),0) as balance
    FROM companies co
    LEFT JOIN transactions t ON co.company_id = t.company_id AND t.customer_id = $id
    HAVING credit > 0 OR collection > 0
    ORDER BY co.company_name
");

// Totals
$totals = $conn->query("
    SELECT
    COALESCE(SUM(CASE WHEN transaction_type='CREDIT' THEN amount ELSE 0 END),0) as total_credit,
    COALESCE(SUM(CASE WHEN transaction_type='COLLECTION' THEN amount ELSE 0 END),0) as total_collection
    FROM transactions WHERE customer_id=$id
")->fetch_assoc();
$totalBalance = $totals['total_credit'] - $totals['total_collection'];

// Open invoices (bills) for this customer
$openInvoices = $conn->query("
    SELECT i.*, co.company_name, (i.amount - i.amount_paid) as balance
    FROM invoices i
    JOIN companies co ON i.company_id = co.company_id
    WHERE i.customer_id=$id AND i.status='OPEN'
    ORDER BY i.due_date ASC
");
$openInvRows = [];
while ($row = $openInvoices->fetch_assoc()) $openInvRows[] = $row;

include '../includes/header.php';
?>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>
        <div class="page-body" id="printArea">

            <div class="page-header no-print">
                <div>
                    <div class="page-header-title"><i class="bi bi-person-lines-fill"></i> Customer Ledger</div>
                    <div class="page-header-sub"><?= htmlspecialchars($customer['customer_name']) ?></div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                    <button class="btn btn-outline-secondary" onclick="exportToPDF('ledgerTable','Customer Ledger - <?= addslashes($customer['customer_name']) ?>','ledger_<?= $id ?>.pdf')"><i class="bi bi-filetype-pdf"></i> PDF</button>
                    <button class="btn btn-outline-secondary" onclick="exportToExcel('ledgerTable','Ledger','ledger_<?= $id ?>.xlsx')"><i class="bi bi-filetype-xlsx"></i> Excel</button>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stat-meta">CUSTOMER NAME</div>
                            <div class="fw-display" style="font-size:18px;"><?= htmlspecialchars($customer['customer_name']) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-meta">PHONE</div>
                            <div><?= htmlspecialchars($customer['phone'] ?: '—') ?></div>
                        </div>
                        <div class="col-md-5">
                            <div class="stat-meta">ADDRESS</div>
                            <div><?= htmlspecialchars($customer['address'] ?: '—') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Totals -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card red">
                        <div class="stat-card-header"><span class="stat-label">Total Credit</span><div class="stat-icon red"><i class="bi bi-arrow-up-circle-fill"></i></div></div>
                        <div class="stat-value" style="font-size:20px;"><?= formatCurrency($totals['total_credit']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card green">
                        <div class="stat-card-header"><span class="stat-label">Total Collection</span><div class="stat-icon green"><i class="bi bi-arrow-down-circle-fill"></i></div></div>
                        <div class="stat-value" style="font-size:20px;"><?= formatCurrency($totals['total_collection']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card <?= $totalBalance > 0 ? 'accent' : 'green' ?>">
                        <div class="stat-card-header"><span class="stat-label">Net Balance</span><div class="stat-icon <?= $totalBalance > 0 ? 'accent' : 'green' ?>"><i class="bi bi-wallet2"></i></div></div>
                        <div class="stat-value" style="font-size:20px;"><?= formatCurrency($totalBalance) ?></div>
                    </div>
                </div>
            </div>

            <!-- Company-wise Breakdown -->
            <?php
            $cbRows = [];
            while ($cb = $companyBalances->fetch_assoc()) $cbRows[] = $cb;
            if (!empty($cbRows)):
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title"><i class="bi bi-building me-2"></i>Company-wise Balance</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach($cbRows as $cb): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="company-balance-card">
                                <div class="company-balance-name"><?= htmlspecialchars($cb['company_name']) ?></div>
                                <div class="company-balance-amount <?= $cb['balance'] > 0 ? '' : 'amount-collection' ?>">
                                    <?= formatCurrency($cb['balance']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">
                                    Cr: <?= formatCurrency($cb['credit']) ?> · Co: <?= formatCurrency($cb['collection']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Open Bills -->
            <?php if (!empty($openInvRows)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title"><i class="bi bi-receipt me-2"></i>Open Bills</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr><th>Invoice #</th><th>Company</th><th>Due Date</th><th>Balance</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($openInvRows as $inv): $overdue = isInvoiceOverdue($inv['due_date'], $inv['status']); ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($inv['company_name']) ?></td>
                                    <td class="<?= $overdue ? 'text-danger fw-display' : '' ?>">
                                        <?= formatDate($inv['due_date']) ?>
                                        <?php if ($overdue): ?><i class="bi bi-exclamation-triangle-fill" title="Overdue"></i><?php endif; ?>
                                    </td>
                                    <td class="amount-neutral"><?= formatCurrency($inv['balance']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transaction History -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="bi bi-list-ul me-2"></i>Transaction History</h6>
                    <span style="font-size:12px;color:var(--text-muted);"><?= count($txnRows) ?> records</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="ledgerTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Company</th>
                                    <th>Invoice #</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Running Balance</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($txnRows) as $i => $t): ?>
                                <tr>
                                    <td><?= $t['transaction_id'] ?></td>
                                    <td><?= formatDate($t['transaction_date']) ?></td>
                                    <td><?= htmlspecialchars($t['company_name']) ?></td>
                                    <td><?= $t['invoice_number'] ? htmlspecialchars($t['invoice_number']) : '—' ?></td>
                                    <td>
                                        <span class="badge-<?= strtolower($t['transaction_type']) ?>">
                                            <?= $t['transaction_type'] ?>
                                        </span>
                                    </td>
                                    <td class="<?= $t['transaction_type']==='CREDIT'?'amount-credit':'amount-collection' ?>">
                                        <?= ($t['transaction_type']==='CREDIT' ? '+' : '-') . formatCurrency($t['amount']) ?>
                                    </td>
                                    <td><?= ($t['transaction_type']==='CREDIT' && $t['due_date']) ? formatDate($t['due_date']) : '—' ?></td>
                                    <td class="running-balance <?= $t['running_balance'] > 0 ? 'balance-positive' : 'balance-negative' ?>">
                                        <?= formatCurrency($t['running_balance']) ?>
                                    </td>
                                    <td style="color:var(--text-muted);font-size:12.5px;"><?= htmlspecialchars($t['note'] ?: '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--bg-surface);">
                                    <td colspan="6"><strong>TOTAL</strong></td>
                                    <td></td>
                                    <td class="running-balance <?= $totalBalance > 0 ? 'balance-positive' : 'balance-negative' ?>">
                                        <?= formatCurrency($totalBalance) ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
