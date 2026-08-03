<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit(); }

$db   = Database::getInstance();
$conn = $db->getConnection();

$stmt = $db->prepare("SELECT * FROM companies WHERE company_id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
if (!$company) { header('Location: index.php'); exit(); }

$pageTitle = 'Company: ' . $company['company_name'];

// All transactions for this company, ordered by date
$stmt = $db->prepare("
    SELECT t.*, cu.customer_name, i.invoice_number, i.due_date
    FROM transactions t
    JOIN customers cu ON t.customer_id = cu.customer_id
    LEFT JOIN invoices i ON t.invoice_id = i.invoice_id
    WHERE t.company_id = ?
    ORDER BY t.transaction_date ASC, t.transaction_id ASC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$transactions = $stmt->get_result();

$txnRows = [];
while ($row = $transactions->fetch_assoc()) {
    $txnRows[] = $row;
}

// Customer-wise balance for this company
$stmt = $db->prepare("
    SELECT cu.customer_id, cu.customer_name,
    COALESCE(SUM(CASE WHEN t.transaction_type='CREDIT' THEN t.amount ELSE 0 END),0) as credit,
    COALESCE(SUM(CASE WHEN t.transaction_type='COLLECTION' THEN t.amount ELSE 0 END),0) as collection,
    COALESCE(SUM(CASE WHEN t.transaction_type='CREDIT' THEN t.amount ELSE -t.amount END),0) as balance
    FROM customers cu
    JOIN transactions t ON cu.customer_id = t.customer_id AND t.company_id = ?
    GROUP BY cu.customer_id
    HAVING credit > 0 OR collection > 0
    ORDER BY cu.customer_name
");
$stmt->bind_param('i', $id);
$stmt->execute();
$customerBalances = $stmt->get_result();
$cbRows = [];
while ($cb = $customerBalances->fetch_assoc()) $cbRows[] = $cb;

// Totals
$stmt = $db->prepare("
    SELECT
    COALESCE(SUM(CASE WHEN transaction_type='CREDIT' THEN amount ELSE 0 END),0) as total_credit,
    COALESCE(SUM(CASE WHEN transaction_type='COLLECTION' THEN amount ELSE 0 END),0) as total_collection
    FROM transactions WHERE company_id=?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$totalBalance = $totals['total_credit'] - $totals['total_collection'];

include '../includes/header.php';
?>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>
        <div class="page-body" id="printArea">

            <div class="page-header no-print">
                <div>
                    <div class="page-header-title"><i class="bi bi-building"></i> Company Details</div>
                    <div class="page-header-sub"><?= htmlspecialchars($company['company_name']) ?></div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
                    <button class="btn btn-outline-secondary" onclick="exportToPDF('companyTxnTable','Company - <?= addslashes($company['company_name']) ?>','company_<?= $id ?>.pdf')"><i class="bi bi-filetype-pdf"></i> PDF</button>
                    <button class="btn btn-outline-secondary" onclick="exportToExcel('companyTxnTable','Company','company_<?= $id ?>.xlsx')"><i class="bi bi-filetype-xlsx"></i> Excel</button>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
            </div>

            <!-- Company Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="stat-meta">COMPANY NAME</div>
                            <div class="fw-display" style="font-size:18px;"><?= htmlspecialchars($company['company_name']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-meta">ON RECORD SINCE</div>
                            <div><?= formatDate($company['created_at']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Totals -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card red">
                        <div class="stat-card-header"><span class="stat-label">Total Distributed</span><div class="stat-icon red"><i class="bi bi-arrow-up-circle-fill"></i></div></div>
                        <div class="stat-value" style="font-size:20px;"><?= formatCurrency($totals['total_credit']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card green">
                        <div class="stat-card-header"><span class="stat-label">Total Collected</span><div class="stat-icon green"><i class="bi bi-arrow-down-circle-fill"></i></div></div>
                        <div class="stat-value" style="font-size:20px;"><?= formatCurrency($totals['total_collection']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card <?= $totalBalance > 0 ? 'accent' : 'green' ?>">
                        <div class="stat-card-header"><span class="stat-label">Outstanding Balance</span><div class="stat-icon <?= $totalBalance > 0 ? 'accent' : 'green' ?>"><i class="bi bi-wallet2"></i></div></div>
                        <div class="stat-value" style="font-size:20px;"><?= formatCurrency($totalBalance) ?></div>
                    </div>
                </div>
            </div>

            <!-- Customer-wise Breakdown -->
            <?php if (!empty($cbRows)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title"><i class="bi bi-people-fill me-2"></i>Customer-wise Balance</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach($cbRows as $cb): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="company-balance-card">
                                <div class="company-balance-name"><?= htmlspecialchars($cb['customer_name']) ?></div>
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

            <!-- Transaction History -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="bi bi-list-ul me-2"></i>Transaction History</h6>
                    <span style="font-size:12px;color:var(--text-muted);"><?= count($txnRows) ?> records</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="companyTxnTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Invoice #</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($txnRows) as $t): ?>
                                <tr>
                                    <td><?= $t['transaction_id'] ?></td>
                                    <td><?= formatDate($t['transaction_date']) ?></td>
                                    <td><?= htmlspecialchars($t['customer_name']) ?></td>
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
                                    <td style="color:var(--text-muted);font-size:12.5px;"><?= htmlspecialchars($t['note'] ?: '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($txnRows)): ?>
                                <tr><td colspan="8" class="empty-state">No transactions recorded for this company yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
