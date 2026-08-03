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
 * Generates the next sequential invoice number, e.g. INV-0009.
 */
function generateInvoiceNumber($db) {
    $conn = $db->getConnection();
    $row = $conn->query("
        SELECT invoice_number FROM invoices
        ORDER BY invoice_id DESC LIMIT 1
    ")->fetch_assoc();

    $next = 1;
    if ($row && preg_match('/(\d+)$/', $row['invoice_number'], $m)) {
        $next = (int)$m[1] + 1;
    }
    return 'INV-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

/**
 * True when an invoice is still open and its due date has passed.
 */
function isInvoiceOverdue($dueDate, $status) {
    return $status === 'OPEN' && strtotime($dueDate) < strtotime(date('Y-m-d'));
}
