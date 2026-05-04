<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/globalinclude.php';
require_once __DIR__ . '/../includes/generate_lib.php';

header('Content-Type: application/json; charset=utf-8');

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = bloggieGenerateOnePost($token);
if (empty($result['ok'])) {
    $msg = (string)($result['message'] ?? 'Error');
    if (strpos($msg, 'Invalid token') !== false) {
        http_response_code(403);
    } elseif (strpos($msg, 'API key') !== false || strpos($msg, 'Missing') !== false) {
        http_response_code(400);
    } elseif (strpos($msg, 'No product') !== false) {
        http_response_code(404);
    } elseif (strpos($msg, 'OpenAI') !== false || strpos($msg, 'parse') !== false) {
        http_response_code(502);
    } else {
        http_response_code(500);
    }
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
