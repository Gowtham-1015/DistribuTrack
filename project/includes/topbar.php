<?php $flash = getFlash(); ?>
<div class="topbar">
    <button class="topbar-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
    <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
    <div class="topbar-actions">
        <span class="topbar-date"><i class="bi bi-calendar3"></i> <?= date('d M Y') ?></span>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert-wrapper">
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
