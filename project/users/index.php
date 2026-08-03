<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireAdmin();

$pageTitle = 'Users';
$db   = Database::getInstance();
$conn = $db->getConnection();
$me   = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username  = trim($_POST['username'] ?? '');
        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';
        $password  = $_POST['password'] ?? '';

        if ($username && $fullName && $password) {
            $checkStmt = $db->prepare("SELECT COUNT(*) as c FROM users WHERE username=?");
            $checkStmt->bind_param('s', $username);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->fetch_assoc()['c'];

            if ($exists) {
                setFlash('error', 'That username is already taken.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?,?,?,?,?)");
                $stmt->bind_param('sssss', $username, $hash, $fullName, $email, $role);
                $stmt->execute();
                setFlash('success', "User '$username' added successfully.");
            }
        } else {
            setFlash('error', 'Username, full name and password are required.');
        }
    }

    if ($action === 'edit') {
        $id       = (int)$_POST['user_id'];
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';
        $password = $_POST['password'] ?? '';

        if ($id && $fullName) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET full_name=?, email=?, role=?, password=? WHERE user_id=?");
                $stmt->bind_param('ssssi', $fullName, $email, $role, $hash, $id);
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name=?, email=?, role=? WHERE user_id=?");
                $stmt->bind_param('sssi', $fullName, $email, $role, $id);
            }
            $stmt->execute();
            setFlash('success', 'User updated successfully.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['user_id'];
        if ($id === (int)$me['user_id']) {
            setFlash('error', 'You cannot delete your own account.');
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            setFlash('success', 'User deleted successfully.');
        }
    }

    header('Location: index.php');
    exit();
}

$users = $conn->query("SELECT * FROM users ORDER BY full_name");

include '../includes/header.php';
?>
<div class="app-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>
        <div class="page-body">

            <div class="page-header">
                <div>
                    <div class="page-header-title"><i class="bi bi-person-badge"></i> Users</div>
                    <div class="page-header-sub">Manage who can access DistribuTrack</div>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-person-plus-fill"></i> Add User
                </button>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dt-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th class="no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; while ($u = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:32px;height:32px;background:var(--accent-dim);color:#8a6c00;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:700;">
                                                <?= strtoupper(substr($u['full_name'],0,1)) ?>
                                            </div>
                                            <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                                    <td>
                                        <span class="badge-<?= $u['role']==='admin' ? 'credit' : 'collection' ?>"><?= ucfirst($u['role']) ?></span>
                                    </td>
                                    <td class="no-export">
                                        <button class="btn btn-outline-secondary btn-sm btn-icon"
                                            onclick='openEditModal(<?= json_encode(["user_id"=>$u["user_id"],"full_name"=>$u["full_name"],"email"=>$u["email"],"role"=>$u["role"]]) ?>)'
                                            title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ((int)$u['user_id'] !== (int)$me['user_id']): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm btn-icon"><i class="bi bi-trash"></i></button>
                                        </form>
                                        <?php endif; ?>
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
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
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
                <input type="hidden" name="user_id" id="e_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" id="e_full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="e_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" id="e_role" class="form-select" required>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
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
    document.getElementById('e_id').value        = data.user_id;
    document.getElementById('e_full_name').value  = data.full_name;
    document.getElementById('e_email').value      = data.email || '';
    document.getElementById('e_role').value       = data.role;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php include '../includes/footer.php'; ?>
