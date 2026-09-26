<?php
// test_cohere.php — DELETE THIS FILE AFTER TESTING
require_once __DIR__ . '/ai/cohere_service.php';

echo "<h2>Cohere API Test</h2>";

$key = cohereApiKey();
echo "Key detected: " . (cohereHasKey() ? "✅ YES" : "❌ NO") . "<br>";
echo "Key preview: " . substr($key, 0, 12) . "..." . substr($key, -6) . "<br>";
echo "Key length: " . strlen($key) . " chars<br><br>";

echo "<b>Sending test request to Cohere v2/chat...</b><br>";
$r = callCohereChat([
    ['role' => 'user', 'content' => 'Say "hello" and nothing else.']
], 'command-r-08-2024');

echo "<pre>";
print_r($r);
echo "</pre>";

if (!isset($r['error'])) {
    $text = extractCohereText($r);
    echo "<b>✅ Success!</b><br>";
    echo "Response text: " . htmlspecialchars($text) . "<br>";
} else {
    echo "<b>❌ Failed.</b><br>";
    echo "Error: " . htmlspecialchars($r['error']) . "<br>";
    echo "Message: " . htmlspecialchars($r['message'] ?? '(none)') . "<br>";
}
echo "<br><br><b>⚠️ Delete this file after testing.</b>";