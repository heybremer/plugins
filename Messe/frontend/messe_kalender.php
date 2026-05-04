<?php

declare(strict_types=1);

use JTL\Shop;
use Plugin\Messe\src\MesseDatabase;

require_once __DIR__ . '/../src/MesseDatabase.php';

MesseDatabase::ensureAll();

global $smarty;
if (!isset($smarty)) {
    return;
}

try {
    $db = Shop::Container()->getDB();
    $sr = $db->getSingleObject('SELECT * FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
    $headline = $sr !== null ? (string)($sr->cHeadline ?? 'Messe Kalender 2026') : 'Messe Kalender 2026';
    $subtitle = $sr !== null ? (string)($sr->cSubtitle ?? '') : '';

    $rows = $db->getObjects(
        'SELECT cMonth, cTitle, cLocation, cDateText, cStand, cWebsiteUrl, cDirectionsUrl
           FROM twomosi_messe_event
          WHERE nActive = 1
       ORDER BY nSort ASC, kID ASC'
    ) ?: [];
    $events = [];
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $events[] = [
                'month' => (string)($r->cMonth ?? ''),
                'title' => (string)($r->cTitle ?? ''),
                'location' => (string)($r->cLocation ?? ''),
                'date' => (string)($r->cDateText ?? ''),
                'stand' => (string)($r->cStand ?? ''),
                'website' => (string)($r->cWebsiteUrl ?? ''),
                'directions' => (string)($r->cDirectionsUrl ?? ''),
            ];
        }
    }

    $payload = ['events' => $events];
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if (!is_string($json)) {
        $json = '{}';
    }

    $smarty->assign('mkHeadline', $headline);
    $smarty->assign('mkSubtitle', $subtitle);
    $smarty->assign('mkDataJson', $json);
} catch (Throwable $e) {
    // fail silently
}

