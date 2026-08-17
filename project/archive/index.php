<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireLogin();

$pageTitle = 'Archive';
$db   = Database::getInstance();
$conn = $db->getConnection();

$res = $conn->query("
    SELECT i.*, c.customer_name, co.company_name, (i.amount - i.amount_paid) as balance
    FROM invoices i
    JOIN customers c  ON i.customer_id = c.customer_id
    JOIN companies co ON i.company_id  = co.company_id
    WHERE i.status='ARCHIVED'
    ORDER BY i.archived_at DESC
");
$archivedRows = [];
while ($row = $res->fetch_assoc()) $archivedRows[] = $row;

include '../includes/header.php';
?>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>
        <div class="page-body">

            <div class="page-header">
                <div>
                    <div class="page-header-title"><i class="bi bi-archive"></i> Archive</div>
                    <div class="page-header-sub">Fully settled invoices, permanently preserved</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="../transactions/index.php?type=CREDIT" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Distribution
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Archived Invoices</h6>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToPDF('archiveTable','Archive','archive.pdf')"><i class="bi bi-filetype-pdf"></i></button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportToExcel('archiveTable','Archive','archive.xlsx')"><i class="bi bi-filetype-xlsx"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dt-table" id="archiveTable">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Company</th>
                                    <th>Amount</th>
                                    <th>Paid</th>
                                    <th>Days to Settle</th>
                                    <th>Note</th>
                                    <th>Archived On</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($archivedRows as $inv): $days = daysOutstanding($inv['invoice_date'], $inv['closed_at'], $inv['status'], $inv['last_payment_date']); ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                                    <td><a href="../customers/view.php?id=<?= $inv['customer_id'] ?>" style="color:var(--accent);text-decoration:none;"><?= htmlspecialchars($inv['customer_name']) ?></a></td>
                                    <td><?= htmlspecialchars($inv['company_name']) ?></td>
                                    <td class="amount-credit">+<?= formatCurrency($inv['amount']) ?></td>
                                    <td class="amount-collection"><?= formatCurrency($inv['amount_paid']) ?></td>
                                    <td><?= $days ?> Day<?= $days == 1 ? '' : 's' ?></td>
                                    <td style="font-size:12.5px;color:var(--text-muted);max-width:200px;"><?= htmlspecialchars($inv['note'] ?: '-') ?></td>
                                    <td><?= $inv['archived_at'] ? formatDate($inv['archived_at']) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
