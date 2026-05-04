<?php
declare(strict_types=1);

use JTL\Shop;
use Plugin\Bloggie\BloggieDatabase;

require_once __DIR__ . '/../../../includes/globalinclude.php';
require_once __DIR__ . '/../src/BloggieDatabase.php';
require_once __DIR__ . '/../includes/product_lib.php';

BloggieDatabase::ensureAll();

header('Content-Type: application/json; charset=utf-8');

try {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Missing token'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = Shop::Container()->getDB();
    $s = $db->getSingleObject("SELECT * FROM twomosi_bloggie_settings ORDER BY kID ASC LIMIT 1");
    if ($s === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Settings not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!hash_equals((string)($s->cCronToken ?? ''), $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid token'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = [
        'ok' => false,
        'checks' => [
            'db' => ['ok' => true, 'message' => 'DB ok'],
            'apiKey' => ['ok' => false, 'message' => 'API key missing'],
            'product' => ['ok' => false, 'message' => 'No product'],
            'openai' => ['ok' => false, 'message' => 'Not tested'],
        ],
    ];

    $apiKey = trim((string)($s->cApiKey ?? ''));
    if ($apiKey !== '') {
        $result['checks']['apiKey'] = ['ok' => true, 'message' => 'API key configured'];
    }

    $tCount = bloggieCountArticles();
    $product = bloggieFetchOneArticleForTest();
    if ($product !== null) {
        $name = trim((string)($product->cName ?? ''));
        if ($name === '') {
            $name = trim((string)($product->cArtName ?? ''));
        }
        $result['checks']['product'] = [
            'ok' => true,
            'message' => 'Product found',
            'kArtikel' => (int)($product->kArtikel ?? 0),
            'name' => $name,
            'metaTitle' => (string)($product->cMetaTitle ?? ''),
            'metaDescription' => (string)($product->cMetaDescription ?? ''),
            'tartikelCount' => $tCount,
        ];
    } else {
        $result['checks']['product']['message'] = 'No product (tartikel count=' . $tCount . ')';
    }

    if (!empty($result['checks']['apiKey']['ok'])) {
        $payload = [
            'model' => (string)($s->cModel ?? 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'Return strict JSON with key status only.'],
                ['role' => 'user', 'content' => 'Return {"status":"ok"}'],
            ],
            'temperature' => 0.0,
            'response_format' => ['type' => 'json_object'],
        ];
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if (!is_string($resp) || $resp === '' || $code < 200 || $code >= 300) {
            $result['checks']['openai'] = ['ok' => false, 'message' => 'OpenAI fail', 'code' => $code, 'error' => $err];
        } else {
            $decoded = json_decode($resp, true);
            $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
            $json = json_decode($content, true);
            if (is_array($json) && (($json['status'] ?? '') === 'ok')) {
                $result['checks']['openai'] = ['ok' => true, 'message' => 'OpenAI ok'];
            } else {
                $result['checks']['openai'] = ['ok' => false, 'message' => 'OpenAI parse failed'];
            }
        }
    }

    $result['ok'] = !empty($result['checks']['db']['ok'])
        && !empty($result['checks']['apiKey']['ok'])
        && !empty($result['checks']['product']['ok'])
        && !empty($result['checks']['openai']['ok']);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

