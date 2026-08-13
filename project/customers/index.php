<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireLogin();

$pageTitle = 'Customers';
$db   = Database::getInstance();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name    = trim($_POST['customer_name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if ($name) {
            $stmt = $db->prepare("INSERT INTO customers (customer_name, phone, address) VALUES (?,?,?)");
            $stmt->bind_param('sss', $name, $phone, $address);
            $stmt->execute();
            setFlash('success', "Customer '$name' added successfully.");
        }
    }

    if ($action === 'edit') {
        $id      = (int)$_POST['customer_id'];
        $name    = trim($_POST['customer_name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if ($id && $name) {
            $stmt = $db->prepare("UPDATE customers SET customer_name=?, phone=?, address=? WHERE customer_id=?");
            $stmt->bind_param('sssi', $name, $phone, $address, $id);
            $stmt->execute();
            setFlash('success', 'Customer updated successfully.');
        }
    }

    if ($action === 'delete') {
        $id    = (int)$_POST['customer_id'];
        $checkStmt = $db->prepare("SELECT COUNT(*) as c FROM transactions WHERE customer_id=?");
        $checkStmt->bind_param('i', $id);
        $checkStmt->execute();
        $check = $checkStmt->get_result()->fetch_assoc()['c'];
        if ($check > 0) {
            setFlash('error', 'Cannot delete: customer has associated transactions.');
        } else {
            $stmt = $db->prepare("DELETE FROM customers WHERE customer_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            setFlash('success', 'Customer deleted successfully.');
        }
    }

    header('Location: index.php');
    exit();
}

$customers = $conn->query("
    SELECT cu.*,
    COALESCE(SUM(CASE WHEN t.transaction_type='CREDIT' THEN t.amount ELSE -t.amount END),0) as balance,
    COUNT(DISTINCT t.transaction_id) as txn_count
    FROM customers cu
    LEFT JOIN transactions t ON cu.customer_id = t.customer_id
    GROUP BY cu.customer_id
    ORDER BY cu.customer_name
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
                    <div class="page-header-title"><i class="bi bi-people-fill"></i> Customers</div>
                    <div class="page-header-sub">Manage your customer accounts</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="exportToPDF('customersTable','Customers','customers.pdf')"><i class="bi bi-filetype-pdf"></i> PDF</button>
                    <button class="btn btn-outline-secondary" onclick="exportToExcel('customersTable','Customers','customers.xlsx')"><i class="bi bi-filetype-xlsx"></i> Excel</button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-person-plus-fill"></i> Add Customer
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dt-table" id="customersTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Customer Name</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Transactions</th>
                                    <th>Balance</th>
                                    <th class="no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; while ($c = $customers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <a href="view.php?id=<?= $c['customer_id'] ?>" style="color:var(--accent);text-decoration:none;font-weight:600;">
                                            <?= htmlspecialchars($c['customer_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
                                    <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($c['address'] ?: '—') ?></td>
                                    <td><?= $c['txn_count'] ?></td>
                                    <td class="<?= $c['balance'] > 0 ? 'amount-credit' : 'amount-collection' ?> fw-display">
                                    <?= formatCurrency($c['balance']) ?>
                                    </td>
                                    <td class="no-export">
                                        <a href="view.php?id=<?= $c['customer_id'] ?>" class="btn btn-outline-secondary btn-sm btn-icon" title="View Ledger">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-secondary btn-sm btn-icon"
                                            onclick='openEditModal(<?= json_encode(["customer_id"=>$c["customer_id"],"customer_name"=>$c["customer_name"],"phone"=>$c["phone"],"address"=>$c["address"]]) ?>)'
                                            title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm btn-icon"><i class="bi bi-trash"></i></button>
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
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"></textarea>
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
                <input type="hidden" name="customer_id" id="e_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" id="e_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="e_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="e_address" class="form-control" rows="3"></textarea>
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
function openEditModal(data) {
    document.getElementById('e_id').value      = data.customer_id;
    document.getElementById('e_name').value    = data.customer_name;
    document.getElementById('e_phone').value   = data.phone || '';
    document.getElementById('e_address').value = data.address || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php include '../includes/footer.php'; ?>
