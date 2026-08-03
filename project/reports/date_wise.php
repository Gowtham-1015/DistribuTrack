<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireLogin();

$pageTitle = 'Date-wise Report';
$db   = Database::getInstance();
$conn = $db->getConnection();

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

$stmt = $db->prepare("
    SELECT
    COALESCE(SUM(CASE WHEN transaction_type='CREDIT' THEN amount ELSE 0 END),0) as total_credit,
    COALESCE(SUM(CASE WHEN transaction_type='COLLECTION' THEN amount ELSE 0 END),0) as total_collection,
    COUNT(*) as total_txn
    FROM transactions
    WHERE transaction_date BETWEEN ? AND ?
");
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$pendingBalance = $summary['total_credit'] - $summary['total_collection'];

$stmt = $db->prepare("
    SELECT
    transaction_date,
    COALESCE(SUM(CASE WHEN transaction_type='CREDIT' THEN amount ELSE 0 END),0) as credits,
    COALESCE(SUM(CASE WHEN transaction_type='COLLECTION' THEN amount ELSE 0 END),0) as collections,
    COUNT(*) as txn_count
    FROM transactions
    WHERE transaction_date BETWEEN ? AND ?
    GROUP BY transaction_date
    ORDER BY transaction_date DESC
");
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$daily = $stmt->get_result();

include '../includes/header.php';
?>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>
        <div class="page-body">

            <div class="page-header">
                <div>
                    <div class="page-header-title"><i class="bi bi-calendar3"></i> Date-wise Report</div>
                    <div class="page-header-sub">Daily credit & collection summary</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="exportToPDF('reportTable','Date-wise Report','date_report.pdf')"><i class="bi bi-filetype-pdf"></i> PDF</button>
                    <button class="btn btn-outline-secondary" onclick="exportToExcel('reportTable','Date Report','date_report.xlsx')"><i class="bi bi-filetype-xlsx"></i> Excel</button>
                </div>
            </div>

            <!-- Filter -->
            <form method="GET" class="filter-bar mb-4">
                <div>
                    <label class="form-label">From Date</label>
                    <input type="date" name="from" class="form-control" value="<?= $fromDate ?>">
                </div>
                <div>
                    <label class="form-label">To Date</label>
                    <input type="date" name="to" class="form-control" value="<?= $toDate ?>">
                </div>
                <div style="align-self:flex-end;">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Generate</button>
                </div>
            </form>

            <!-- Summary -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card red">
                        <div class="stat-card-header"><span class="stat-label">Total Credit</span><div class="stat-icon red"><i class="bi bi-arrow-up-circle-fill"></i></div></div>
                        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($summary['total_credit']) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card green">
                        <div class="stat-card-header"><span class="stat-label">Total Collection</span><div class="stat-icon green"><i class="bi bi-arrow-down-circle-fill"></i></div></div>
                        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($summary['total_collection']) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card accent">
                        <div class="stat-card-header"><span class="stat-label">Pending Balance</span><div class="stat-icon accent"><i class="bi bi-hourglass-split"></i></div></div>
                        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($pendingBalance) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card blue">
                        <div class="stat-card-header"><span class="stat-label">Total Transactions</span><div class="stat-icon blue"><i class="bi bi-receipt"></i></div></div>
                        <div class="stat-value"><?= $summary['total_txn'] ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Daily Breakdown: <?= formatDate($fromDate) ?> – <?= formatDate($toDate) ?></h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dt-table" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transactions</th>
                                    <th>Total Credit</th>
                                    <th>Total Collection</th>
                                    <th>Day Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($row = $daily->fetch_assoc()): $bal = $row['credits'] - $row['collections']; ?>
                            <tr>
                                <td><?= formatDate($row['transaction_date']) ?></td>
                                <td><?= $row['txn_count'] ?></td>
                                <td class="amount-credit"><?= formatCurrency($row['credits']) ?></td>
                                <td class="amount-collection"><?= formatCurrency($row['collections']) ?></td>
                                <td class="<?= $bal > 0 ? 'amount-credit' : 'amount-collection' ?> fw-display"><?= formatCurrency($bal) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--bg-surface);font-weight:700;">
                                    <td>TOTAL</td>
                                    <td><?= $summary['total_txn'] ?></td>
                                    <td class="amount-credit"><?= formatCurrency($summary['total_credit']) ?></td>
                                    <td class="amount-collection"><?= formatCurrency($summary['total_collection']) ?></td>
                                    <td class="<?= $pendingBalance>0?'amount-credit':'amount-collection' ?>"><?= formatCurrency($pendingBalance) ?></td>
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
