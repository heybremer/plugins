<?php
declare(strict_types=1);

use JTL\Shop;
use Plugin\Bloggie\BloggieDatabase;

if (!function_exists('bloggieSlugify')) {
    function bloggieSlugify(string $text): string
    {
        $s = mb_strtolower(trim($text));
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s) ?? '';
        $s = trim($s, '-');
        return $s !== '' ? mb_substr($s, 0, 160) : 'blog-post';
    }
}

if (!function_exists('bloggieGenerateOnePost')) {
    /**
     * @param string|null $cronToken If not null, must match settings (HTTP/cron). Null = internal (admin already authenticated).
     * @return array{ok:bool, message?:string, title?:string, product?:string}
     */
    function bloggieGenerateOnePost(?string $cronToken = null): array
    {
        try {
            require_once dirname(__DIR__) . '/src/BloggieDatabase.php';
            BloggieDatabase::ensureAll();

            $db = Shop::Container()->getDB();
            $s = $db->getSingleObject('SELECT * FROM twomosi_bloggie_settings ORDER BY kID ASC LIMIT 1');
            if ($s === null) {
                return ['ok' => false, 'message' => 'Settings not found'];
            }
            if ($cronToken !== null) {
                if (!hash_equals((string)($s->cCronToken ?? ''), $cronToken)) {
                    return ['ok' => false, 'message' => 'Invalid token'];
                }
            }
            $apiKey = trim((string)($s->cApiKey ?? ''));
            if ($apiKey === '') {
                return ['ok' => false, 'message' => 'API key missing'];
            }

            if (!function_exists('bloggieFetchArticleRows')) {
                require_once __DIR__ . '/product_lib.php';
            }
            $brandPool = (string)($s->cBrandPool ?? '');
            $rows = bloggieFetchArticleRows();
            if (!is_array($rows) || $rows === []) {
                $n = function_exists('bloggieCountArticles') ? bloggieCountArticles() : 0;
                return ['ok' => false, 'message' => 'No in-stock product found (tartikel count=' . $n . ')'];
            }
            $p = bloggieSelectArticleRowForBlog($rows, $brandPool);
            if ($p === null) {
                return ['ok' => false, 'message' => 'Could not pick a product row'];
            }
            $kArtikel = (int)($p->kArtikel ?? 0);
            $name = trim((string)($p->cName ?? ''));
            if ($name === '') {
                $name = trim((string)($p->cArtName ?? 'Produkt'));
            }
            $seoTitle = trim((string)($p->cMetaTitle ?? ''));
            if ($seoTitle === '') {
                $seoTitle = $name;
            }
            $meta = trim((string)($p->cMetaDescription ?? ''));
            $desc = trim(strip_tags((string)($p->cKurzBeschreibung ?? '')));
            if ($desc === '') {
                $desc = trim(strip_tags((string)($p->cBeschreibung ?? '')));
            }
            if ($meta === '') {
                $meta = mb_substr($desc, 0, 250);
            }
            $productUrl = rtrim((string)Shop::getURL(), '/') . '/index.php?a=' . $kArtikel;

            $topicPool = (string)($s->cTopicPool ?? '');
            $pickFromPool = static function (string $pool, int $seed): string {
                $lines = preg_split('/\r\n|\r|\n/', $pool) ?: [];
                $lines = array_values(array_filter(array_map('trim', $lines), static fn($v) => $v !== ''));
                if ($lines === []) {
                    return '';
                }
                $idx = $seed % count($lines);
                return (string)$lines[$idx];
            };
            $seed = ((int)date('z') + (int)date('W') * 13 + (int)$kArtikel) & 0x7fffffff;
            $brandFocus = function_exists('bloggieBrandPoolLineForDay') ? bloggieBrandPoolLineForDay($brandPool) : '';
            $topicFocus = $pickFromPool($topicPool, $seed + 7);

            $system = 'You are an expert SEO content writer. Return strictly valid JSON with keys: title, excerpt, bodyHtml.';
            $user = trim((string)($s->cPromptPrefix ?? 'Write a high quality SEO blog post for this product page.')) . "\n\n"
                . ($brandFocus !== '' ? ("Brand focus: " . $brandFocus . "\n") : '')
                . ($topicFocus !== '' ? ("Topic focus: " . $topicFocus . "\n") : '')
                . 'Language: ' . (string)($s->cLanguage ?? 'de') . "\n"
                . 'Product Name: ' . $name . "\n"
                . 'SEO Title: ' . $seoTitle . "\n"
                . 'Meta Description: ' . $meta . "\n"
                . 'Product Description: ' . $desc . "\n"
                . 'Product URL: ' . $productUrl . "\n\n"
                . "Rules:\n- bodyHtml must contain h2 and p tags and one bullet list.\n- mention product URL once in an anchor tag.\n";

            // Persist last prompts for dashboard visibility
            try {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_bloggie_settings
                        SET cLastSystemPrompt = :sys,
                            cLastUserPrompt = :usr,
                            dLastPrompt = NOW(),
                            dUpdated = NOW()
                      WHERE kID = :kID",
                    [
                        'kID' => (int)($s->kID ?? 0),
                        'sys' => $system,
                        'usr' => $user,
                    ]
                );
            } catch (Throwable $e) {
                // ignore
            }

            $payload = [
                'model' => (string)($s->cModel ?? 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.7,
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
                CURLOPT_TIMEOUT => 35,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if (!is_string($resp) || $resp === '' || $code < 200 || $code >= 300) {
                return ['ok' => false, 'message' => 'OpenAI error: ' . $code . ' ' . $err];
            }
            $decoded = json_decode($resp, true);
            $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
            $json = json_decode($content, true);
            if (!is_array($json)) {
                return ['ok' => false, 'message' => 'Model JSON parse failed'];
            }
            $title = trim((string)($json['title'] ?? ''));
            $excerpt = trim((string)($json['excerpt'] ?? ''));
            $bodyHtml = trim((string)($json['bodyHtml'] ?? ''));
            if ($title === '' || $bodyHtml === '') {
                return ['ok' => false, 'message' => 'Missing title/bodyHtml'];
            }

            $slug = bloggieSlugify($title) . '-' . date('Ymd');
            $newsSlugBase = bloggieSlugify($title) . '-' . date('Ymd') . '-' . (int)$kArtikel;
            $productUrl = rtrim((string)$productUrl, '/');
            // Ensure product link exists at the end (admin/news detail then product link at the bottom).
            if ($productUrl !== '' && \strpos($bodyHtml, $productUrl) === false) {
                $bodyHtml .= '<p><a href="' . $productUrl . '">Zum Produkt</a></p>';
            }
            $bloggiePostID = (int)$db->insert('twomosi_bloggie_posts', (object)[
                'kArtikel' => $kArtikel,
                'kNews' => 0,
                'cProductName' => $name,
                'cProductUrl' => $productUrl,
                'cSeoTitle' => $seoTitle,
                'cMetaDescription' => $meta,
                'cBlogTitle' => $title,
                'cSlug' => $slug,
                'cExcerpt' => $excerpt,
                'cBodyHtml' => $bodyHtml,
                'nPublished' => 1,
                'dPublish' => 'now()',
                'dCreated' => 'now()',
            ]);

            // Also write into JTL "news" tables so the admin/news page can manage it.
            $kNews = bloggieInsertGeneratedPostIntoJtlNews($db, [
                'kArtikel'        => $kArtikel,
                'name'            => $name,
                'url'             => $productUrl,
                'seoTitle'       => $seoTitle,
                'metaDescription' => $meta,
            ], [
                'title'   => $title,
                'excerpt' => $excerpt,
                'bodyHtml' => $bodyHtml,
                'newsSlugBase' => $newsSlugBase,
            ]);
            if ($bloggiePostID > 0 && $kNews > 0) {
                $db->update('twomosi_bloggie_posts', 'kID', $bloggiePostID, (object)['kNews' => (int)$kNews]);
            }

            $db->executeQueryPrepared(
                'UPDATE twomosi_bloggie_settings SET dLastRunDate = :d, dUpdated = NOW() WHERE kID = :kID',
                ['d' => date('Y-m-d'), 'kID' => (int)$s->kID]
            );

            return ['ok' => true, 'message' => 'Blog generated', 'title' => $title, 'product' => $name];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('bloggieInsertGeneratedPostIntoJtlNews')) {
    /**
     * Writes the generated blog into JTL's news system tables:
     * - tnews, tnewssprache, tnewskategorienews
     * - (ensures a "Bloggie" news category exists)
     * - tseo (required for URLs)
     *
     * @param object $db DbInterface
     * @param array{url:string,seoTitle:string,metaDescription:string,kArtikel:int,name:string} $product
     * @param array{title:string,excerpt:string,bodyHtml:string,newsSlugBase:string} $generated
     */
    function bloggieInsertGeneratedPostIntoJtlNews($db, array $product, array $generated): int
    {
        $categoryName = 'Bloggie';
        $categoryDesc = 'Autogenerated blog posts';
        $categorySeoBase = 'bloggie-category';
        $newsSeoKey = 'kNews';
        $newsCategoryKey = 'kNewsKategorie';
        $flags = \ENT_COMPAT | \ENT_HTML401;
        $charset = \defined('\JTL_CHARSET') ? \JTL_CHARSET : 'UTF-8';

        $category = $db->getSingleObject(
            "SELECT tnewskategoriesprache.kNewsKategorie AS id
             FROM tnewskategoriesprache
             WHERE tnewskategoriesprache.name = :nm
             LIMIT 1",
            ['nm' => $categoryName]
        );

        $tsprache = $db->getObjects(
            'SELECT kSprache, cISO
             FROM tsprache
             WHERE active = 1
             ORDER BY kSprache'
        );

        $languages = [];
        foreach ($tsprache as $lang) {
            $languages[] = [
                'languageID' => (int)($lang->kSprache ?? 0),
                'languageCode' => (string)($lang->cISO ?? ''),
            ];
        }
        if ($category === null && $languages === []) {
            // No languages -> nothing we can write.
            return 0;
        }

        $categoryIDForNews = 0;
        if ($category === null) {
            // Minimal category tree. Frontend overview joins only on kNewsKategorie + nAktiv, so nested-set values can be simple.
            $categoryIDForNews = (int)$db->insert('tnewskategorie', (object)[
                'kParent' => 0,
                'nSort' => 0,
                'nAktiv' => 1,
                'dLetzteAktualisierung' => 'now()',
                'cPreviewImage' => '',
                'lft' => 1,
                'rght' => 2,
                'lvl' => 0,
            ]);

            if ($categoryIDForNews <= 0) {
                return 0;
            }

            foreach ($languages as $lang) {
                $lid = (int)$lang['languageID'];
                $iso = (string)$lang['languageCode'];
                $db->insert('tnewskategoriesprache', (object)[
                    'kNewsKategorie' => $categoryIDForNews,
                    'languageID' => $lid,
                    'languageCode' => $iso,
                    'name' => \htmlspecialchars($categoryName, $flags, $charset),
                    'description' => \htmlspecialchars($categoryDesc, $flags, $charset),
                    'metaTitle' => \htmlspecialchars($categoryName, $flags, $charset),
                    'metaDescription' => \htmlspecialchars($categoryDesc, $flags, $charset),
                ]);
            }
        } else {
            $categoryIDForNews = (int)($category->id ?? 0);
            if ($categoryIDForNews > 0) {
                // Ensure the category is active.
                $db->update(
                    'tnewskategorie',
                    'kNewsKategorie',
                    $categoryIDForNews,
                    (object)['nAktiv' => 1]
                );
            }
        }

        if (($categoryIDForNews ?? 0) <= 0) {
            return 0;
        }

        $newsID = (int)$db->insert('tnews', (object)[
            'cKundengruppe' => ';-1;',
            'nAktiv' => 1,
            'dErstellt' => 'now()',
            'dGueltigVon' => 'now()',
            'cPreviewImage' => '',
        ]);
        if ($newsID <= 0) {
            return 0;
        }

        foreach ($languages as $lang) {
            $lid = (int)$lang['languageID'];
            $iso = (string)$lang['languageCode'];

            // Ensure category URL generation (Category->getURL uses seo table).
            $categorySeo = $categorySeoBase . '-' . $categoryIDForNews . '-' . $iso;
            $existsSeo = $db->getSingleObject(
                "SELECT 1 FROM tseo WHERE cKey = :ck AND kKey = :kk AND kSprache = :ks AND cSeo = :cs LIMIT 1",
                ['ck' => $newsCategoryKey, 'kk' => $categoryIDForNews, 'ks' => $lid, 'cs' => $categorySeo]
            );
            if ($existsSeo === null) {
                $db->insert('tseo', (object)[
                    'cSeo' => $categorySeo,
                    'cKey' => $newsCategoryKey,
                    'kKey' => $categoryIDForNews,
                    'kSprache' => $lid,
                ]);
            }

            // Ensure category translations exist for each active language.
            $existsCatLoc = $db->getSingleObject(
                'SELECT 1
                 FROM tnewskategoriesprache
                 WHERE kNewsKategorie = :kid
                   AND languageID = :lid
                 LIMIT 1',
                ['kid' => $categoryIDForNews, 'lid' => $lid]
            );
            if ($existsCatLoc === null) {
                $db->insert('tnewskategoriesprache', (object)[
                    'kNewsKategorie' => $categoryIDForNews,
                    'languageID' => $lid,
                    'languageCode' => $iso,
                    'name' => \htmlspecialchars($categoryName, $flags, $charset),
                    'description' => \htmlspecialchars($categoryDesc, $flags, $charset),
                    'metaTitle' => \htmlspecialchars($categoryName, $flags, $charset),
                    'metaDescription' => \htmlspecialchars($categoryDesc, $flags, $charset),
                ]);
            }

            $db->insert('tnewssprache', (object)[
                'kNews' => $newsID,
                'languageID' => $lid,
                'languageCode' => $iso,
                'title' => \htmlspecialchars((string)$generated['title'], $flags, $charset),
                'content' => $generated['bodyHtml'],
                'preview' => $generated['excerpt'],
                'metaTitle' => \htmlspecialchars((string)$product['seoTitle'], $flags, $charset),
                'metaKeywords' => '',
                'metaDescription' => \htmlspecialchars((string)$product['metaDescription'], $flags, $charset),
            ]);

            // URLs for admin/news preview + frontend details.
            $newsSeo = (string)($generated['newsSlugBase'] ?? '');
            if ($newsSeo !== '') {
                // Include the auto-incremented newsID to avoid collisions when generating the same article again.
                // Important: do NOT prefix with "news/" here, otherwise URLs become /news/... (user doesn't want that).
                $newsSeo = $newsSeo . '-' . (int)$newsID . '-' . $iso;
                $db->insert('tseo', (object)[
                    'cSeo' => $newsSeo,
                    'cKey' => $newsSeoKey,
                    'kKey' => $newsID,
                    'kSprache' => $lid,
                ]);
            }
        }

        // Link the item to the category so the news overview can find it.
        $db->insert('tnewskategorienews', (object)[
            'kNewsKategorie' => $categoryIDForNews,
            'kNews' => $newsID,
        ]);

        return $newsID;
    }
}
