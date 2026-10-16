<?php

declare(strict_types=1);

namespace Plugin\Messe\src;

use JTL\Shop;

final class MesseDatabase
{
    private static bool $ensured = false;
    private static bool $pageEnsured = false;

    /**
     * Default seed data (mirrors plugins/Messe/Template/js/messe-kalender.js).
     *
     * @return array<int, array{cMonth:string,cTitle:string,cLocation:string,cDateText:string,cStand:string,cWebsiteUrl:string,cDirectionsUrl:string,nSort:int,nActive:int}>
     */
    private static function defaultEvents(): array
    {
        return [
            [
                'cMonth' => 'januar',
                'cTitle' => 'TOURISMA & Caravaning',
                'cLocation' => 'Magdeburg',
                'cDateText' => '9–11.01.2026',
                'cStand' => 'Halle 1 | Stand Nr: 109',
                'cWebsiteUrl' => 'https://www.tc-magdeburg.com/',
                'cDirectionsUrl' => '',
                'nSort' => 10,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'januar',
                'cTitle' => 'Caravan Freizeit Reisen (CFR)',
                'cLocation' => 'Oldenburg',
                'cDateText' => '16–18.01.2026',
                'cStand' => 'Halle 3 | Stand Nr: 3200',
                'cWebsiteUrl' => 'https://www.caravan-freizeit-reisen.de/',
                'cDirectionsUrl' => '',
                'nSort' => 20,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'januar',
                'cTitle' => 'CMT – Die Urlaubsmesse',
                'cLocation' => 'Stuttgart',
                'cDateText' => '17.–25.01.2026',
                'cStand' => 'Halle 10 | Stand Nr: 10B61',
                'cWebsiteUrl' => 'https://www.messe-stuttgart.de/cmt/',
                'cDirectionsUrl' => '',
                'nSort' => 30,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'februar',
                'cTitle' => 'Caravan und Camping Messe',
                'cLocation' => 'Hamburg',
                'cDateText' => '5–8.02.2026',
                'cStand' => 'Halle: B5 | Stand Nr: B5.553',
                'cWebsiteUrl' => 'https://www.messe-stuttgart.de/reisenhamburg/',
                'cDirectionsUrl' => '',
                'nSort' => 40,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'februar',
                'cTitle' => 'ABF',
                'cLocation' => 'Hannover',
                'cDateText' => '11–15.02.2026',
                'cStand' => 'Halle: 25 | Stand Nr: F54',
                'cWebsiteUrl' => 'https://www.abf-hannover.de/de/',
                'cDirectionsUrl' => '',
                'nSort' => 50,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'februar',
                'cTitle' => 'F.re.e 2026',
                'cLocation' => 'München',
                'cDateText' => '18-22.02.2026',
                'cStand' => 'Halle: B5 | Stand Nr: 324',
                'cWebsiteUrl' => 'https://free-muenchen.de/de/messe/',
                'cDirectionsUrl' => '',
                'nSort' => 60,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'februar',
                'cTitle' => 'REISE + CAMPING',
                'cLocation' => 'Essen',
                'cDateText' => '25–01.03.2026',
                'cStand' => 'Halle: 6 | Stand Nr: 6A45',
                'cWebsiteUrl' => 'https://www.reise-camping.de/reise-camping/',
                'cDirectionsUrl' => '',
                'nSort' => 70,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'märz',
                'cTitle' => 'Freizeit Touristik & Garten',
                'cLocation' => 'Nürnberg',
                'cDateText' => '4–8.3.2026',
                'cStand' => 'Halle: 7 | Stand Nr: A43',
                'cWebsiteUrl' => 'https://www.freizeitmesse.de/',
                'cDirectionsUrl' => '',
                'nSort' => 80,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'märz',
                'cTitle' => 'Boot & Angeln / Camping & Caravaning Rostock',
                'cLocation' => 'HanseMesse Rostock',
                'cDateText' => '20–22.3.2026',
                'cStand' => 'Stand Nr: C19',
                'cWebsiteUrl' => 'https://www.expotecgmbh.de/messen-inland/',
                'cDirectionsUrl' => '',
                'nSort' => 90,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'märz',
                'cTitle' => 'Rheinland Pfalz Ausstellung 2026',
                'cLocation' => 'Mainz',
                'cDateText' => '25-29.03.2026',
                'cStand' => 'Halle: C | Stand Nr: C73',
                'cWebsiteUrl' => 'https://www.expotecgmbh.de/messen-inland/rheinland-pfalz-ausstellung/besuchen/',
                'cDirectionsUrl' => '',
                'nSort' => 100,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'august',
                'cTitle' => 'Caravan Salon Düsseldorf',
                'cLocation' => 'Düsseldorf',
                'cDateText' => 'ca. 28.08.–06.09.2026',
                'cStand' => '-',
                'cWebsiteUrl' => 'https://www.caravan-salon.de/',
                'cDirectionsUrl' => '',
                'nSort' => 110,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'oktober',
                'cTitle' => 'Die Messe \"Reisen & Caravan\"',
                'cLocation' => 'Erfurt',
                'cDateText' => '29.10.-01-11-2026',
                'cStand' => '-',
                'cWebsiteUrl' => 'https://www.reisen-caravan.de/',
                'cDirectionsUrl' => '',
                'nSort' => 120,
                'nActive' => 1,
            ],
            [
                'cMonth' => 'november',
                'cTitle' => 'Caravan Bremen 2026',
                'cLocation' => 'Bremen',
                'cDateText' => '06.-08-11-2026',
                'cStand' => '-',
                'cWebsiteUrl' => 'https://www.caravan-bremen.de/de/',
                'cDirectionsUrl' => '',
                'nSort' => 130,
                'nActive' => 1,
            ],
        ];
    }

    public static function ensureAll(): void
    {
        if (self::$ensured) {
            return;
        }

        try {
            $db = Shop::Container()->getDB();

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_messe_event (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    cMonth VARCHAR(32) NOT NULL DEFAULT '',
                    cTitle VARCHAR(255) NOT NULL DEFAULT '',
                    cLocation VARCHAR(255) NOT NULL DEFAULT '',
                    cDateText VARCHAR(64) NOT NULL DEFAULT '',
                    cStand VARCHAR(128) NOT NULL DEFAULT '',
                    cWebsiteUrl VARCHAR(1000) NOT NULL DEFAULT '',
                    cDirectionsUrl VARCHAR(1000) NOT NULL DEFAULT '',
                    nSort INT NOT NULL DEFAULT 100,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    dCreated DATETIME NOT NULL,
                    dUpdated DATETIME NOT NULL,
                    KEY idx_active_sort (nActive, nSort),
                    KEY idx_month (cMonth)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_messe_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cPageSlug VARCHAR(128) NOT NULL DEFAULT 'messe-kalender',
                    cHeadline VARCHAR(255) NOT NULL DEFAULT 'Messe Kalender 2026',
                    cSubtitle VARCHAR(500) NOT NULL DEFAULT 'Die Messen, an denen wir als Bremer-Sitzbezüge GmbH teilnehmen werden',
                    dUpdated DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $yearCol = $db->getSingleObject("SHOW COLUMNS FROM twomosi_messe_event LIKE 'nJahr'");
            if ($yearCol === null) {
                $db->executeQuery(
                    'ALTER TABLE twomosi_messe_event ADD COLUMN nJahr INT NOT NULL DEFAULT 0 AFTER cDateText'
                );
            }
            $needYear = $db->getObjects(
                'SELECT kID, cDateText FROM twomosi_messe_event WHERE nJahr = 0 OR nJahr IS NULL'
            ) ?: [];
            foreach ($needYear as $yr) {
                $parsed = self::yearFromDateText((string)($yr->cDateText ?? ''));
                if ($parsed > 0) {
                    $db->executeQueryPrepared(
                        'UPDATE twomosi_messe_event SET nJahr = :y WHERE kID = :id',
                        ['y' => $parsed, 'id' => (int)($yr->kID ?? 0)]
                    );
                }
            }

            foreach ([
                'dStart' => 'ALTER TABLE twomosi_messe_event ADD COLUMN dStart DATE NULL',
                'dEnde' => 'ALTER TABLE twomosi_messe_event ADD COLUMN dEnde DATE NULL',
            ] as $column => $sql) {
                $exists = $db->getSingleObject("SHOW COLUMNS FROM twomosi_messe_event LIKE '" . $column . "'");
                if ($exists === null) {
                    $db->executeQuery($sql);
                }
            }
            try {
                $missingDates = $db->getObjects(
                    'SELECT kID, cDateText FROM twomosi_messe_event WHERE dStart IS NULL OR dEnde IS NULL'
                ) ?: [];
                foreach ($missingDates as $row) {
                    $start = self::parseEventStartDate((string)($row->cDateText ?? ''));
                    $end = self::parseEventEndDate((string)($row->cDateText ?? ''));
                    if ($start === null || $end === null) {
                        continue;
                    }
                    $db->executeQueryPrepared(
                        'UPDATE twomosi_messe_event SET dStart = :s, dEnde = :e WHERE kID = :id',
                        [
                            's' => $start->format('Y-m-d'),
                            'e' => $end->format('Y-m-d'),
                            'id' => (int)($row->kID ?? 0),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                // Dates can be filled later from the dashboard.
            }

            // Idempotent migrations (columns may be missing on older installs)
            $cols = [
                'cPageSlug' => "ALTER TABLE twomosi_messe_settings ADD COLUMN cPageSlug VARCHAR(128) NOT NULL DEFAULT 'messe-kalender' AFTER nActive",
                'cHeadline' => "ALTER TABLE twomosi_messe_settings ADD COLUMN cHeadline VARCHAR(255) NOT NULL DEFAULT 'Messe Kalender 2026' AFTER cPageSlug",
                'cSubtitle' => "ALTER TABLE twomosi_messe_settings ADD COLUMN cSubtitle VARCHAR(500) NOT NULL DEFAULT '' AFTER cHeadline",
                'nHomepageActive' => "ALTER TABLE twomosi_messe_settings ADD COLUMN nHomepageActive TINYINT(1) NOT NULL DEFAULT 0 AFTER cSubtitle",
                'cHomepageInjectSelector' => "ALTER TABLE twomosi_messe_settings ADD COLUMN cHomepageInjectSelector VARCHAR(255) NOT NULL DEFAULT '' AFTER nHomepageActive",
                'cHomepageInjectMode' => "ALTER TABLE twomosi_messe_settings ADD COLUMN cHomepageInjectMode VARCHAR(16) NOT NULL DEFAULT 'after' AFTER cHomepageInjectSelector",
                'nHomepageLimit' => "ALTER TABLE twomosi_messe_settings ADD COLUMN nHomepageLimit INT NOT NULL DEFAULT 6 AFTER cHomepageInjectMode",
            ];
            foreach ($cols as $col => $sql) {
                $exists = $db->getSingleObject("SHOW COLUMNS FROM twomosi_messe_settings LIKE '{$col}'");
                if ($exists === null) {
                    $db->executeQuery($sql);
                }
            }

            $db->executeQuery(
                "UPDATE twomosi_messe_settings
                    SET cHeadline = 'Messe Kalender 2027'
                  WHERE cHeadline = 'Messe Kalender 2026'"
            );

            // Startseiten-Widget verursachte DOM-Konflikte mit anderen Homepage-Injektoren
            // (kategorien/logos verschwanden, da derselbe Selector wie CardProduct/MarkeFiltre/
            // Top10List verwendet wurde, aber ohne Teilnahme am HomepageInjector-Ordering).
            // Einmalige, dauerhafte Korrektur über Migrations-Marker-Spalte.
            $fixCol = $db->getSingleObject("SHOW COLUMNS FROM twomosi_messe_settings LIKE 'nHomepageFixApplied'");
            if ($fixCol === null) {
                $db->executeQuery(
                    'ALTER TABLE twomosi_messe_settings ADD COLUMN nHomepageFixApplied TINYINT(1) NOT NULL DEFAULT 0'
                );
                $db->executeQuery(
                    'UPDATE twomosi_messe_settings SET nHomepageActive = 0, nHomepageFixApplied = 1'
                );
            }

            // Eigentliche Ursache (unnötige tlink-Schreibvorgänge auf jedem Request) wurde
            // behoben -> Startseiten-Widget einmalig wieder sicher aktivieren.
            $reCol = $db->getSingleObject("SHOW COLUMNS FROM twomosi_messe_settings LIKE 'nHomepageReenabled'");
            if ($reCol === null) {
                $db->executeQuery(
                    'ALTER TABLE twomosi_messe_settings ADD COLUMN nHomepageReenabled TINYINT(1) NOT NULL DEFAULT 0'
                );
                $db->executeQuery(
                    'UPDATE twomosi_messe_settings SET nHomepageActive = 1, nHomepageReenabled = 1'
                );
            }

            $row = $db->getSingleObject('SELECT kID FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                $s = new \stdClass();
                $s->nActive = 1;
                $s->cPageSlug = 'messe-kalender';
                $s->cHeadline = 'Messe Kalender 2027';
                $s->cSubtitle = 'Die Messen, an denen wir als Bremer-Sitzbezüge GmbH teilnehmen werden';
                $s->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_messe_settings', $s);
            }

            // Seed defaults once (only if table empty)
            $cnt = $db->getSingleObject('SELECT COUNT(*) AS c FROM twomosi_messe_event');
            if ($cnt !== null && (int)($cnt->c ?? 0) === 0) {
                $now = date('Y-m-d H:i:s');
                foreach (self::defaultEvents() as $ev) {
                    $o = new \stdClass();
                    $o->cMonth = (string)$ev['cMonth'];
                    $o->cTitle = (string)$ev['cTitle'];
                    $o->cLocation = (string)$ev['cLocation'];
                    $o->cDateText = (string)$ev['cDateText'];
                    $o->nJahr = self::yearFromDateText((string)$ev['cDateText']);
                    $o->cStand = (string)$ev['cStand'];
                    $o->cWebsiteUrl = (string)$ev['cWebsiteUrl'];
                    $o->cDirectionsUrl = (string)($ev['cDirectionsUrl'] ?? '');
                    $o->nSort = (int)$ev['nSort'];
                    $o->nActive = (int)$ev['nActive'];
                    $o->dCreated = $now;
                    $o->dUpdated = $now;
                    $db->insert('twomosi_messe_event', $o);
                }
            }

            self::$ensured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Ensures a real JTL Link page exists for the calendar (so header/footer work).
     */
    public static function ensurePluginPage(int $pluginID): void
    {
        if (self::$pageEnsured) {
            return;
        }
        self::$pageEnsured = true;

        if ($pluginID <= 0) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();

            $s = $db->getSingleObject('SELECT cPageSlug, cHeadline, nActive FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
            $slug = $s !== null ? \trim((string)($s->cPageSlug ?? 'messe-kalender')) : 'messe-kalender';
            if ($slug === '') {
                $slug = 'messe-kalender';
            }
            $headline = $s !== null ? \trim((string)($s->cHeadline ?? 'Messe Kalender')) : 'Messe Kalender';
            if ($headline === '') {
                $headline = 'Messe Kalender';
            }
            $active = $s !== null ? (int)($s->nActive ?? 1) : 1;

            // If a link already exists for this plugin, keep it.
            $existing = $db->getSingleObject(
                'SELECT tlink.kLink, pld.cTemplate, pld.cFullscreenTemplate
                   FROM tlink
                   JOIN tpluginlinkdatei pld ON pld.kLink = tlink.kLink
                  WHERE tlink.kPlugin = :pid
                    AND tlink.nLinkart = :lt
                    AND pld.cDatei = :f
                  LIMIT 1',
                [
                    'pid' => $pluginID,
                    'lt' => \LINKTYP_PLUGIN,
                    // PageController prefixes getFrontendPath() automatically
                    'f' => 'messe_kalender.php',
                ]
            );
            $kLink = $existing !== null ? (int)($existing->kLink ?? 0) : 0;

            // Already correctly configured -> stop here, no further writes needed on
            // every single request (avoids unnecessary tlink/cache churn on every pageview).
            if (
                $kLink > 0
                && (string)($existing->cTemplate ?? '') === 'messe_kalender.tpl'
                && (string)($existing->cFullscreenTemplate ?? '') === '_DBNULL_'
            ) {
                return;
            }

            // If mapping not found, try resolve LinkID by SEO (kLink) for current slug.
            if ($kLink <= 0) {
                $seo = $db->getSingleObject(
                    "SELECT kKey
                       FROM tseo
                      WHERE cKey = 'kLink'
                        AND cSeo = :seo
                      ORDER BY kSprache ASC
                      LIMIT 1",
                    ['seo' => $slug]
                );
                $kLink = $seo !== null ? (int)($seo->kKey ?? 0) : 0;
            }

            // Link group: hidden (fallback 1)
            $hiddenLg = $db->select('tlinkgruppe', 'cTemplatename', 'hidden');
            $lgID = (int)($hiddenLg->kLinkgruppe ?? 1);
            if ($lgID <= 0) {
                $lgID = 1;
            }

            // If not found, create via LinkAdmin (handles translations + tseo)
            if ($kLink <= 0) {
                $linkAdmin = new \JTL\Link\Admin\LinkAdmin($db, Shop::Container()->getCache());
                $data = [
                    'kLink' => 0,
                    'kPlugin' => $pluginID,
                    'cName' => $headline,
                    'nLinkart' => \LINKTYP_PLUGIN,
                    'nSort' => 0,
                    'bSSL' => 0,
                    'bIsActive' => $active === 1 ? 1 : 0,
                    'bIsFluid' => 1,
                    'cSichtbarNachLogin' => 'N',
                    'cNoFollow' => 'N',
                    'cIdentifier' => 'womosi_messe_kalender',
                    'linkTarget' => '_self',
                    'cKundengruppen' => '_DBNULL_',
                    'kLinkgruppe' => $lgID,
                ];
                foreach (\JTL\Language\LanguageHelper::getAllLanguages(0, true) as $language) {
                    $iso = $language->getIso();
                    $data['cName_' . $iso] = $headline;
                    $data['cTitle_' . $iso] = $headline;
                    $data['cSeo_' . $iso] = $slug;
                    $data['cContent_' . $iso] = '';
                }
                $linkObj = $linkAdmin->createOrUpdateLink($data);
                $kLink = (int)$linkObj->getID();
            }

            if ($kLink <= 0) {
                return;
            }

            // Ensure tpluginlinkdatei mapping exists
            $pld = $db->getSingleObject('SELECT kLink FROM tpluginlinkdatei WHERE kLink = :k LIMIT 1', ['k' => $kLink]);
            if ($pld === null) {
                $row = new \stdClass();
                $row->kPlugin = $pluginID;
                $row->kLink = $kLink;
                $row->cDatei = 'messe_kalender.php';
                $row->cTemplate = 'messe_kalender.tpl';
                // JTL expects _DBNULL_ for "no fullscreen template"
                $row->cFullscreenTemplate = '_DBNULL_';
                $db->insert('tpluginlinkdatei', $row);
            } else {
                // Keep row consistent (in case of partial/older installs)
                $upd = new \stdClass();
                $upd->kPlugin = $pluginID;
                $upd->cDatei = 'messe_kalender.php';
                $upd->cTemplate = 'messe_kalender.tpl';
                $upd->cFullscreenTemplate = '_DBNULL_';
                $db->update('tpluginlinkdatei', 'kLink', $kLink, $upd);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{active:int,slug:string,headline:string,subtitle:string,homepageActive:int,homepageInjectSelector:string,homepageInjectMode:string,homepageLimit:int}
     */
    public static function fetchSettings(): array
    {
        $defaults = [
            'active' => 1,
            'slug' => 'messe-kalender',
            'headline' => 'Messe Kalender',
            'subtitle' => 'Die Messen, an denen wir als Bremer-Sitzbezüge GmbH teilnehmen werden',
            'homepageActive' => 0,
            'homepageInjectSelector' => '',
            'homepageInjectMode' => 'after',
            'homepageLimit' => 6,
        ];
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT * FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                return $defaults;
            }
            $slug = \trim((string)($row->cPageSlug ?? 'messe-kalender'));
            if ($slug === '') {
                $slug = $defaults['slug'];
            }
            $mode = \trim((string)($row->cHomepageInjectMode ?? 'after'));
            if (!\in_array($mode, ['after', 'before', 'prepend', 'append'], true)) {
                $mode = 'after';
            }
            $limit = (int)($row->nHomepageLimit ?? 6);
            if ($limit < 1) {
                $limit = 6;
            }
            if ($limit > 24) {
                $limit = 24;
            }

            return [
                'active' => (int)($row->nActive ?? 1),
                'slug' => $slug,
                'headline' => (string)($row->cHeadline ?? $defaults['headline']),
                'subtitle' => (string)($row->cSubtitle ?? $defaults['subtitle']),
                'homepageActive' => (int)($row->nHomepageActive ?? 1),
                'homepageInjectSelector' => (string)($row->cHomepageInjectSelector ?? ''),
                'homepageInjectMode' => $mode,
                'homepageLimit' => $limit,
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }

    public static function calendarPageUrl(array $settings): string
    {
        $slug = \ltrim((string)($settings['slug'] ?? 'messe-kalender'), '/');
        if ($slug === '') {
            $slug = 'messe-kalender';
        }
        try {
            $shopUrl = \rtrim((string)Shop::getURL(), '/');
            $shopBasePath = (string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? '');
            $shopBasePath = \rtrim($shopBasePath, '/');

            return $shopBasePath . '/' . $slug;
        } catch (\Throwable $e) {
            return '/' . $slug;
        }
    }

    /**
     * @return array<int, array{month:string,title:string,location:string,date:string,stand:string,website:string,directions:string}>
     */
    public static function fetchUpcomingEvents(int $limit = 6): array
    {
        if ($limit < 1) {
            $limit = 6;
        }
        if ($limit > 24) {
            $limit = 24;
        }

        $today = new \DateTimeImmutable('today');
        $scored = [];

        foreach (self::fetchActiveEvents() as $ev) {
            $start = self::parseEventStartDate((string)($ev['date'] ?? ''));
            $end = self::parseEventEndDate((string)($ev['date'] ?? ''));
            if ($end === null && $start === null) {
                continue;
            }
            $endCmp = $end ?? $start;
            if ($endCmp < $today) {
                continue;
            }
            $scored[] = [
                'ev' => $ev,
                'sort' => ($start ?? $endCmp)->getTimestamp(),
            ];
        }

        \usort($scored, static function (array $a, array $b): int {
            return $a['sort'] <=> $b['sort'];
        });

        $out = [];
        foreach (\array_slice($scored, 0, $limit) as $row) {
            $out[] = $row['ev'];
        }

        return $out;
    }

    public static function eventStartIso(string $dateText): string
    {
        $date = self::parseEventStartDate($dateText);

        return $date !== null ? $date->format('Y-m-d') : '';
    }

    public static function eventEndIso(string $dateText): string
    {
        $date = self::parseEventEndDate($dateText);

        return $date !== null ? $date->format('Y-m-d') : '';
    }

    private static function parseEventEndDate(string $dateText): ?\DateTimeImmutable
    {
        $matches = self::extractDateMatches($dateText);
        if ($matches === []) {
            return null;
        }
        $last = $matches[\count($matches) - 1];

        return self::dateFromParts((int)$last['day'], (int)$last['month'], (int)$last['year']);
    }

    private static function parseEventStartDate(string $dateText): ?\DateTimeImmutable
    {
        $s = \trim($dateText);
        if ($s === '') {
            return null;
        }

        $end = self::parseEventEndDate($s);
        if ($end === null) {
            return null;
        }

        // Bereiche wie "9–11.01.2026" oder "17.–25.01.2026"
        if (\preg_match('/^(\d{1,2})\s*[.\-–]+\s*(\d{1,2})[.\-–]+(\d{1,2})[.\-–]+(\d{4})/u', $s, $m)) {
            $start = self::dateFromParts((int)$m[1], (int)$m[3], (int)$m[4]);
            if ($start !== null) {
                return $start;
            }
        }

        return $end;
    }

    /**
     * @return array<int, array{day:int,month:int,year:int}>
     */
    private static function extractDateMatches(string $dateText): array
    {
        $s = \trim($dateText);
        if ($s === '') {
            return [];
        }
        if (!\preg_match_all('/(\d{1,2})\D+(\d{1,2})\D+(\d{4})/u', $s, $matches, \PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        foreach ($matches as $m) {
            $out[] = [
                'day' => (int)$m[1],
                'month' => (int)$m[2],
                'year' => (int)$m[3],
            ];
        }

        return $out;
    }

    private static function dateFromParts(int $day, int $month, int $year): ?\DateTimeImmutable
    {
        if ($day < 1 || $day > 31 || $month < 1 || $month > 12 || $year < 1970) {
            return null;
        }
        try {
            return new \DateTimeImmutable(\sprintf('%04d-%02d-%02d', $year, $month, $day));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<int, array{month:string,title:string,location:string,date:string,stand:string,website:string,directions:string,year:int}>
     */
    public static function fetchActiveEvents(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $rows = $db->getObjects(
                'SELECT cMonth, cTitle, cLocation, cDateText, nJahr, cStand, cWebsiteUrl, cDirectionsUrl
                   FROM twomosi_messe_event
                  WHERE nActive = 1
               ORDER BY nJahr ASC, nSort ASC, kID ASC'
            );
            if (!\is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                $date = (string)($r->cDateText ?? '');
                $year = (int)($r->nJahr ?? 0);
                if ($year < 1970) {
                    $year = self::yearFromDateText($date);
                }
                $out[] = [
                    'month' => (string)($r->cMonth ?? ''),
                    'title' => (string)($r->cTitle ?? ''),
                    'location' => (string)($r->cLocation ?? ''),
                    'date' => $date,
                    'stand' => (string)($r->cStand ?? ''),
                    'website' => (string)($r->cWebsiteUrl ?? ''),
                    'directions' => (string)($r->cDirectionsUrl ?? ''),
                    'year' => $year,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function yearFromDateText(string $dateText): int
    {
        if (\preg_match('/(20\d{2})/', $dateText, $m)) {
            return (int)$m[1];
        }
        return 0;
    }
}

