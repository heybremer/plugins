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

            // Idempotent migrations (columns may be missing on older installs)
            $cols = [
                'cPageSlug' => "ALTER TABLE twomosi_messe_settings ADD COLUMN cPageSlug VARCHAR(128) NOT NULL DEFAULT 'messe-kalender' AFTER nActive",
                'cHeadline' => "ALTER TABLE twomosi_messe_settings ADD COLUMN cHeadline VARCHAR(255) NOT NULL DEFAULT 'Messe Kalender 2026' AFTER cPageSlug",
                'cSubtitle' => "ALTER TABLE twomosi_messe_settings ADD COLUMN cSubtitle VARCHAR(500) NOT NULL DEFAULT '' AFTER cHeadline",
            ];
            foreach ($cols as $col => $sql) {
                $exists = $db->getSingleObject("SHOW COLUMNS FROM twomosi_messe_settings LIKE '{$col}'");
                if ($exists === null) {
                    $db->executeQuery($sql);
                }
            }

            $row = $db->getSingleObject('SELECT kID FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                $s = new \stdClass();
                $s->nActive = 1;
                $s->cPageSlug = 'messe-kalender';
                $s->cHeadline = 'Messe Kalender 2026';
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
            $headline = $s !== null ? \trim((string)($s->cHeadline ?? 'Messe Kalender 2026')) : 'Messe Kalender 2026';
            if ($headline === '') {
                $headline = 'Messe Kalender 2026';
            }
            $active = $s !== null ? (int)($s->nActive ?? 1) : 1;

            // If a link already exists for this plugin, keep it.
            $existing = $db->getSingleObject(
                'SELECT tlink.kLink
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
}

