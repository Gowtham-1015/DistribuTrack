<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
$currentType = $_GET['type'] ?? '';

function isActive($pages, $dirs = [], $extra = true) {
    global $currentPage, $currentDir;
    if (!$extra) return false;
    if (in_array($currentPage, (array)$pages)) return true;
    if (!empty($dirs) && in_array($currentDir, (array)$dirs)) return true;
    return false;
}
$user = getCurrentUser();
$isAdmin = ($user['role'] === 'admin');
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-box-seam-fill"></i></div>
        <div class="brand-text">
            <span class="brand-name">DistribuTrack</span>
            <span class="brand-sub">Collection Manager</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">MAIN</div>
        <a href="/dashboard.php" class="nav-item <?= isActive('dashboard.php') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <div class="nav-section-label">MANAGEMENT</div>
        <a href="/customers/index.php" class="nav-item <?= isActive(['index.php','view.php'], ['customers']) ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Customers</span>
        </a>
        <a href="/companies/index.php" class="nav-item <?= isActive(['index.php','view.php'], ['companies']) ? 'active' : '' ?>">
            <i class="bi bi-building"></i>
            <span>Companies</span>
        </a>
        <a href="/transactions/index.php?type=CREDIT" class="nav-item <?= isActive('index.php', ['transactions'], $currentType === 'CREDIT') ? 'active' : '' ?>">
            <i class="bi bi-arrow-up-circle"></i>
            <span>Distribution</span>
        </a>
        <a href="/transactions/index.php?type=COLLECTION" class="nav-item <?= isActive('index.php', ['transactions'], $currentType === 'COLLECTION') ? 'active' : '' ?>">
            <i class="bi bi-arrow-down-circle"></i>
            <span>Collections</span>
        </a>

        <?php if ($isAdmin): ?>
        <div class="nav-section-label">ADMIN</div>
        <a href="/users/index.php" class="nav-item <?= isActive('index.php', ['users']) ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i>
            <span>Users</span>
        </a>
        <a href="/settings/index.php" class="nav-item <?= isActive('index.php', ['settings']) ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>
        <?php else: ?>
        <div class="nav-section-label">ACCOUNT</div>
        <a href="/settings/index.php" class="nav-item <?= isActive('index.php', ['settings']) ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
        <div class="user-details">
            <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
            <span class="user-role"><?= ucfirst($user['role']) ?></span>
        </div>
        <a href="/logout.php" class="logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
