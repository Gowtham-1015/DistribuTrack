<?php
require_once '../config/auth.php';
require_once '../config/database.php';
requireLogin();

$pageTitle = 'Settings';
$db   = Database::getInstance();
$conn = $db->getConnection();
$me   = getCurrentUser();

$stmt = $db->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->bind_param('i', $me['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if ($fullName) {
            $stmt = $db->prepare("UPDATE users SET full_name=?, email=? WHERE user_id=?");
            $stmt->bind_param('ssi', $fullName, $email, $me['user_id']);
            $stmt->execute();
            $_SESSION['full_name'] = $fullName;
            setFlash('success', 'Profile updated successfully.');
        } else {
            setFlash('error', 'Full name is required.');
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New password and confirmation do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE user_id=?");
            $stmt->bind_param('si', $hash, $me['user_id']);
            $stmt->execute();
            setFlash('success', 'Password changed successfully.');
        }
    }

    header('Location: index.php');
    exit();
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
                    <div class="page-header-title"><i class="bi bi-gear"></i> Settings</div>
                    <div class="page-header-sub">Manage your profile and account security</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="card-title"><i class="bi bi-person-circle me-2"></i>Profile</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Profile</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="card-title"><i class="bi bi-shield-lock me-2"></i>Change Password</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="6">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check"></i> Change Password</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title"><i class="bi bi-info-circle me-2"></i>About</h6>
                        </div>
                        <div class="card-body">
                            <p style="color:var(--text-secondary);margin-bottom:8px;">DistribuTrack — Distribution Credit & Collection Management</p>
                            <p style="color:var(--text-muted);font-size:12.5px;">Data is stored locally in an SQLite database at <code>database/distribu_track.db</code>.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
