<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '/index.php');
        exit();
    }
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    // Go up to project root
    $parts = explode('/', trim($script, '/'));
    $root = '';
    foreach ($parts as $part) {
        if ($part === 'project') { $root .= '/' . $part; break; }
        $root .= '/' . $part;
    }
    return $protocol . '://' . $host . $root;
}

function requireAdmin() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        setFlash('error', 'You do not have permission to access that page.');
        header('Location: ' . getBaseUrl() . '/dashboard.php');
        exit();
    }
}

function getCurrentUser() {
    return [
        'user_id'   => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
    ];
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatCurrency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d M Y', strtotime($date));
}

/**
 * Days Outstanding = how long an invoice has gone unpaid.
 *
 * Always counts from the Issue Date. While OPEN it keeps counting up
 * to today regardless of partial payments — a partial payment does
 * NOT reset the clock, since the bill is still outstanding until it's
 * fully paid. Once CLOSED or ARCHIVED, it freezes at Issue Date ->
 * closed_at: the total time it took to fully settle.
 */
function daysOutstanding($issueDate, $closedAt, $status, $lastPaymentDate = null) {
    $start = new DateTime(substr($issueDate, 0, 10));
    if ($status === 'OPEN' || !$closedAt) {
        $end = new DateTime(date('Y-m-d'));
    } else {
        $end = new DateTime(substr($closedAt, 0, 10));
    }
    $diff = $start->diff($end);
    return (int)$diff->days;
}
