<?php
date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if (!function_exists('moduleUrl')) {
    function moduleUrl($path = '') {
        return baseUrl('modules/' . ltrim($path, '/'));
    }
}

if (!function_exists('rootUrl')) {
    function rootUrl($path = '') {
        return baseUrl($path);
    }
}

if (!function_exists('redirect')) {
    function redirect($path) { header("Location: " . baseUrl($path)); exit; }
}

if (!function_exists('redirectRoot')) {
    function redirectRoot($path) { header("Location: " . baseUrl($path)); exit; }
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
if (!function_exists('isStaff')) {
    function isStaff() { return isAdmin() || isProductManager(); }
}
if (!function_exists('requireAdmin')) {
    function requireAdmin() { requireLogin(); if (!isAdmin()) redirectRoot('login.php'); }
}
if (!function_exists('requireProductManager')) {
    function requireProductManager() {
        requireLogin();
        if (!isAdmin() && !isProductManager()) redirectRoot('login.php');
    }
}
if (!function_exists('requireCustomer')) {
    function requireCustomer() { requireLogin(); if (!isCustomer()) redirectRoot('login.php'); }
}
if (!function_exists('isActive')) {
    function isActive($page) { return basename($_PHP_SELF ?? $_SERVER['PHP_SELF']) === $page ? 'active-menu' : ''; }
}
if (!function_exists('e')) {
    function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('generateOTP')) {
    function generateOTP($length = 6) {
        return str_pad((string)random_int(0, (int)pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        if (!$datetime) return '';
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j', $timestamp);
    }
}

if (!function_exists('handleUpload')) {
    function handleUpload($fileInput, $subfolder, $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm','video/quicktime'], $maxBytes = 20971520) {
        if (empty($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] !== UPLOAD_ERR_OK) return null;
        $file = $_FILES[$fileInput];
        if ($file['size'] > $maxBytes) return null;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedTypes)) return null;

        $extMap = [
            'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
            'video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov',
        ];
        $ext = $extMap[$mime] ?? 'bin';

        $destDir = __DIR__ . '/../assets/uploads/' . trim($subfolder, '/') . '/';
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) return null;

        return 'assets/uploads/' . trim($subfolder, '/') . '/' . $filename;
    }
}

if (!function_exists('matchFaq')) {
    function matchFaq($pdo, $query) {
        $q = mb_strtolower(trim($query));
        if ($q === '') return null;

        $rows = $pdo->query("SELECT * FROM ai_faq ORDER BY priority DESC")->fetchAll();
        $best = null;
        $bestScore = 0;

        foreach ($rows as $row) {
            $keywords = array_map('trim', explode(',', mb_strtolower($row['keywords'])));
            $score = 0;
            foreach ($keywords as $kw) {
                if ($kw !== '' && mb_strpos($q, $kw) !== false) {
                    $score += mb_strlen($kw);
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return ($bestScore > 0) ? $best : null;
    }
}

/**
 * Generate an AI reply.
 *
 * Returns an array:
 *   ['text' => '...', 'escalate' => true|false]
 *
 * - 'escalate' = true means the message didn't match FAQ/order context
 *    and the AI is notifying the customer that a human will reply.
 */
if (!function_exists('generateAiReply')) {
    function generateAiReply($pdo, $query, $userId = null) {
        $q = mb_strtolower($query);

        // === 1. Context: "my order / my delivery / where is my package" ===
        if (preg_match('/\b(my|the|our)\s+(order|delivery|package|parcel|shipment)\b/', $q) && $userId) {
            $stmt = $pdo->prepare("SELECT order_number, status, created_at, payment_status, delivery_option FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $order = $stmt->fetch();

            if ($order) {
                $orderNo = $order['order_number'] ?: ('#' . $order['id']);
                $eta = [
                    'standard' => '3-5 business days',
                    'express'  => '1-2 business days',
                    'sameday'  => 'today',
                ][$order['delivery_option'] ?? 'standard'] ?? '3-5 business days';

                $statusMsg = [
                    'pending'    => "Your order is pending — we're preparing to process it.",
                    'processing' => "Your order is being prepared in our warehouse.",
                    'shipped'    => "Your package is on the way! 🚚",
                    'delivered'  => "Your order has been delivered. 🎉",
                    'cancelled'  => "This order was cancelled.",
                    'returned'   => "This order was returned.",
                ][$order['status']] ?? "Status: " . $order['status'];

                return [
                    'text' => "📦 **Order $orderNo**\n\n$statusMsg\n\nExpected delivery: **$eta** from order date.\nPayment: " . ucwords(str_replace('_',' ', $order['payment_status'])) . ".\n\nYou can track it in detail from your dashboard.",
                    'escalate' => false,
                ];
            }
        }

        // === 2. "How many days to deliver" generic question ===
        if (preg_match('/\b(how (long|many days)|when will|delivery time|delivery take|arrive|shipping time|ship)\b/', $q)) {
            return [
                'text' => "🚚 **Delivery Times**\n\n• Standard: **3-5 business days**\n• Express: **1-2 business days**\n• Same-day: **delivered today** (order before 2 PM)\n\nYou can track your order any time from your dashboard → Order History.",
                'escalate' => false,
            ];
        }

        // === 3. FAQ match ===
        $faq = matchFaq($pdo, $query);
        if ($faq) {
            return ['text' => $faq['answer'], 'escalate' => false];
        }

        // === 4. Fallback — escalate to a human ===
        return [
            'text' => "🙋 Got it! Your message has been forwarded to our team (Admin / Product Manager).\n\nThey will reply to you as soon as possible — usually within a few hours during business hours.\n\n⏳ Please wait for their response. You can continue typing here if you have additional details to add.",
            'escalate' => true,
        ];
    }
}