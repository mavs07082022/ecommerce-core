<?php
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * baseUrl() — returns path relative to the APP ROOT.
 * Strips subfolders: modules, api, ai, auth, admin.
 */
if (!function_exists('baseUrl')) {
    function baseUrl($path = '') {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $dir = rtrim($dir, '/');
        $dir = preg_replace('#/(modules|api|ai|auth|admin)$#', '', $dir);
        if ($dir === '' || $dir === '.') $dir = '';
        $dir = rtrim($dir, '/') . '/';
        return $dir . ltrim($path, '/');
    }
}

if (!function_exists('rootUrl')) {
    function rootUrl($path = '') {
        return baseUrl($path);
    }
}

if (!function_exists('redirect')) {
    function redirect($path) {
        header("Location: " . baseUrl($path));
        exit;
    }
}

if (!function_exists('redirectRoot')) {
    function redirectRoot($path) {
        header("Location: " . baseUrl($path));
        exit;
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isset($_SESSION['user_id'])) redirectRoot('login.php');
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
}
if (!function_exists('isProductManager')) {
    function isProductManager() { return isset($_SESSION['role']) && $_SESSION['role'] === 'product_manager'; }
}
if (!function_exists('isCustomer')) {
    function isCustomer() { return isset($_SESSION['role']) && $_SESSION['role'] === 'customer'; }
}
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        requireLogin();
        if (!isAdmin()) redirectRoot('login.php');
    }
}
if (!function_exists('requireProductManager')) {
    function requireProductManager() {
        requireLogin();
        if (!isAdmin() && !isProductManager()) redirectRoot('login.php');
    }
}
if (!function_exists('requireCustomer')) {
    function requireCustomer() {
        requireLogin();
        if (!isCustomer()) redirectRoot('login.php');
    }
}
if (!function_exists('isActive')) {
    function isActive($page) {
        return basename($_SERVER['PHP_SELF']) === $page ? 'active-menu' : '';
    }
}
if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generate a random numeric OTP of the given length.
 * Uses cryptographically secure random_int().
 */
if (!function_exists('generateOTP')) {
    function generateOTP($length = 6) {
        return str_pad((string)random_int(0, (int)pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}