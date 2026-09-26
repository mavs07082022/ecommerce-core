<?php
require_once __DIR__ . '/includes/auth.php';

// Detect which login to send them back to based on role
$wasStaff = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'product_manager'], true);

session_destroy();

if ($wasStaff) {
    header("Location: " . baseUrl('admin/login.php'));
} else {
    header("Location: " . baseUrl('login.php'));
}
exit;