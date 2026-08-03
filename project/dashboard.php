<?php
require_once 'config/auth.php';
require_once 'config/database.php';
requireLogin();

$pageTitle = 'Dashboard';
$db = Database::getInstance();
$conn = $db->getConnection();

// Core stats
$totalCustomers     = $conn->query("SELECT COUNT(*) as c FROM customers")->fetch_assoc()['c'];
$totalCompanies     = $conn->query("SELECT COUNT(*) as c FROM companies")->fetch_assoc()['c'];
$totalDistributions = $conn->query("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE transaction_type='CREDIT'")->fetch_assoc()['s'];
$totalCollections   = $conn->query("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE transaction_type='COLLECTION'")->fetch_assoc()['s'];
$outstandingBalance = $totalDistributions - $totalCollections;

// Recent activity
$recent = $conn->query("
    SELECT t.*, c.customer_name, co.company_name
    FROM transactions t
    JOIN customers c ON t.customer_id = c.customer_id
    JOIN companies co ON t.company_id = co.company_id
    ORDER BY t.created_at DESC, t.transaction_id DESC
    LIMIT 8
");

include 'includes/header.php';
?>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        <div class="page-body">

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card blue">
                        <div class="stat-card-header">
                            <span class="stat-label">Customers</span>
                            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
                        </div>
                        <div class="stat-value"><?= $totalCustomers ?></div>
                        <div class="stat-meta">Registered customers</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card purple">
                        <div class="stat-card-header">
                            <span class="stat-label">Companies</span>
                            <div class="stat-icon purple"><i class="bi bi-building"></i></div>
                        </div>
                        <div class="stat-value"><?= $totalCompanies ?></div>
                        <div class="stat-meta">Active companies</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card red">
                        <div class="stat-card-header">
                            <span class="stat-label">Total Distributions</span>
                            <div class="stat-icon red"><i class="bi bi-arrow-up-circle-fill"></i></div>
                        </div>
                        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($totalDistributions) ?></div>
                        <div class="stat-meta">Credit issued (all time)</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <div class="stat-card green">
                        <div class="stat-card-header">
                            <span class="stat-label">Total Collections</span>
                            <div class="stat-icon green"><i class="bi bi-arrow-down-circle-fill"></i></div>
                        </div>
                        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($totalCollections) ?></div>
                        <div class="stat-meta">Payments received (all time)</div>
                    </div>
                </div>
                <div class="col-12 col-md-4 col-xl">
                    <div class="stat-card accent">
                        <div class="stat-card-header">
                            <span class="stat-label">Outstanding Balance</span>
                            <div class="stat-icon accent"><i class="bi bi-hourglass-split"></i></div>
                        </div>
                        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($outstandingBalance) ?></div>
                        <div class="stat-meta">Distributions − collections</div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
                    <a href="transactions/index.php" class="btn btn-outline-secondary btn-sm">View All</a>
                </div>
                <div class="card-body" style="padding-top:12px!important;">
                    <?php if ($recent === null): ?>
                    <?php endif; ?>
                    <?php $hasRows = false; while ($txn = $recent->fetch_assoc()): $hasRows = true; ?>
                    <div class="txn-row">
                        <div class="txn-icon <?= strtolower($txn['transaction_type']) ?>">
                            <i class="bi bi-arrow-<?= $txn['transaction_type']==='CREDIT'?'up':'down' ?>-circle-fill"></i>
                        </div>
                        <div class="txn-info">
                            <div class="txn-name"><?= htmlspecialchars($txn['customer_name']) ?></div>
                            <div class="txn-meta"><?= htmlspecialchars($txn['company_name']) ?> · <?= formatDate($txn['transaction_date']) ?></div>
                        </div>
                        <div class="txn-amount <?= $txn['transaction_type']==='CREDIT'?'amount-credit':'amount-collection' ?>">
                            <?= $txn['transaction_type']==='CREDIT'?'+':'-' ?><?= formatCurrency($txn['amount']) ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php if (!$hasRows): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        No transactions yet.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
