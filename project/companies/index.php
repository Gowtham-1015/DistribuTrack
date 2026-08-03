<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireLogin();

$pageTitle = 'Companies';
$db   = Database::getInstance();
$conn = $db->getConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['company_name'] ?? '');
        if ($name) {
            $stmt = $db->prepare("INSERT INTO companies (company_name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            setFlash('success', "Company '$name' added successfully.");
        } else {
            setFlash('error', 'Company name is required.');
        }
    }

    if ($action === 'edit') {
        $id   = (int)$_POST['company_id'];
        $name = trim($_POST['company_name'] ?? '');
        if ($id && $name) {
            $stmt = $db->prepare("UPDATE companies SET company_name=? WHERE company_id=?");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            setFlash('success', 'Company updated successfully.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['company_id'];
        // Check if used in transactions
        $checkStmt = $db->prepare("SELECT COUNT(*) as c FROM transactions WHERE company_id=?");
        $checkStmt->bind_param('i', $id);
        $checkStmt->execute();
        $check = $checkStmt->get_result()->fetch_assoc()['c'];
        if ($check > 0) {
            setFlash('error', 'Cannot delete: company has associated transactions.');
        } else {
            $stmt = $db->prepare("DELETE FROM companies WHERE company_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            setFlash('success', 'Company deleted successfully.');
        }
    }

    header('Location: index.php');
    exit();
}

$companies = $conn->query("
    SELECT c.*, COUNT(DISTINCT t.transaction_id) as txn_count,
    COALESCE(SUM(CASE WHEN t.transaction_type='CREDIT' THEN t.amount ELSE -t.amount END),0) as balance
    FROM companies c
    LEFT JOIN transactions t ON c.company_id = t.company_id
    GROUP BY c.company_id
    ORDER BY c.company_name
");

include '../includes/header.php';
?>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>
        <div class="page-body">

            <div class="page-header">
                <div>
                    <div class="page-header-title"><i class="bi bi-building"></i> Companies</div>
                    <div class="page-header-sub">Manage distribution companies</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="exportToPDF('companiesTable','Companies','companies.pdf')"><i class="bi bi-filetype-pdf"></i> PDF</button>
                    <button class="btn btn-outline-secondary" onclick="exportToExcel('companiesTable','Companies','companies.xlsx')"><i class="bi bi-filetype-xlsx"></i> Excel</button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-lg"></i> Add Company
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dt-table" id="companiesTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Company Name</th>
                                    <th>Transactions</th>
                                    <th>Outstanding Balance</th>
                                    <th class="no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; while ($c = $companies->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:32px;height:32px;background:var(--blue-dim);color:var(--blue);border-radius:6px;display:flex;align-items:center;justify-content:center;">
                                                <i class="bi bi-building"></i>
                                            </div>
                                            <a href="view.php?id=<?= $c['company_id'] ?>" style="color:var(--text-primary);text-decoration:none;">
                                                <strong><?= htmlspecialchars($c['company_name']) ?></strong>
                                            </a>
                                        </div>
                                    </td>
                                    <td><?= $c['txn_count'] ?></td>
                                    <td class="<?= $c['balance'] > 0 ? 'amount-credit' : 'amount-collection' ?>">
                                        <?= formatCurrency($c['balance']) ?>
                                    </td>
                                    <td class="no-export">
                                        <a href="view.php?id=<?= $c['company_id'] ?>" class="btn btn-outline-secondary btn-sm btn-icon" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-secondary btn-sm btn-icon"
                                            onclick="openEditModal('editModal',{company_id:'<?= $c['company_id'] ?>',company_name:'<?= htmlspecialchars(addslashes($c['company_name'])) ?>'})"
                                            title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="company_id" value="<?= $c['company_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-building me-2"></i>Add Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" placeholder="Enter company name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="company_id" id="edit_company_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" id="edit_company_name" class="form-control" required>
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
function openEditModal(modalId, data) {
    document.getElementById('edit_company_id').value = data.company_id;
    document.getElementById('edit_company_name').value = data.company_name;
    new bootstrap.Modal(document.getElementById(modalId)).show();
}
</script>

<?php include '../includes/footer.php'; ?>
