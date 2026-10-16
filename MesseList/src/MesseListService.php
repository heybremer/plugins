<?php

declare(strict_types=1);

namespace Plugin\MesseList;

use JTL\Shop;

final class MesseListService
{
    public const CONFIG_TABLE = 'twomosi_messelist_config';

    public const TOKEN_KEY = 'public_token';

    public const SERIES_KEY = 'selected_series';

    public const GROUPS_KEY = 'series_groups';

    public const TABS_KEY = 'series_tabs';

    public const EXTRA_SERIES_KEY = 'extra_series';

    public const EXTRA_ARTNR_KEY = 'extra_artnr';

    public const MANUAL_KEY = 'manual_products';

    public const QUOTES_TABLE = 'twomosi_messelist_quotes';

    /** @var list<string> */
    public const DS_SERIES = ['DS1', 'DS2', 'DS3', 'DS4', 'DS5', 'DS6', 'DS7', 'DS8', 'DS9'];

    /** @var array<string, list<string>> */
    public const SERIES_ARTNR = [
        'LT1' => ['20175'],
        'LT2' => ['20176'],
        'LT3' => ['20277'],
        'LT4' => ['20278'],
        'LT5' => ['20279'],
    ];

    public static function ensureTables(): void
    {
        try {
            Shop::Container()->getDB()->executeQuery(
                'CREATE TABLE IF NOT EXISTS ' . self::CONFIG_TABLE . ' (
                    cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                    cValue VARCHAR(512) NOT NULL DEFAULT ""
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            Shop::Container()->getDB()->executeQuery(
                'ALTER TABLE ' . self::CONFIG_TABLE . ' MODIFY cValue TEXT NOT NULL'
            );
            Shop::Container()->getDB()->executeQuery(
                'CREATE TABLE IF NOT EXISTS ' . self::QUOTES_TABLE . ' (
                    kID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    cNr VARCHAR(32) NOT NULL,
                    dCreated DATETIME NOT NULL,
                    cVorname VARCHAR(80) NOT NULL DEFAULT "",
                    cNachname VARCHAR(80) NOT NULL DEFAULT "",
                    cTelefon VARCHAR(40) NOT NULL DEFAULT "",
                    cEmail VARCHAR(160) NOT NULL DEFAULT "",
                    cItems MEDIUMTEXT NOT NULL,
                    fTotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                    nMailed TINYINT NOT NULL DEFAULT 0,
                    cHtml MEDIUMTEXT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (\Throwable $e) {
        }
    }

    public static function normalizeSeries(string $s): string
    {
        $s = \strtoupper(\trim($s));
        if (\preg_match('/^[0-9]+$/', $s) === 1) {
            return '';
        }
        if (\preg_match('/^[A-Z]{1,8}[0-9]{1,5}$/', $s) !== 1) {
            return '';
        }

        return $s;
    }

    /**
     * @param list<string> $series
     * @return list<string>
     */
    public static function sanitizeSeries(array $series): array
    {
        $out = [];
        foreach ($series as $s) {
            $n = self::normalizeSeries((string)$s);
            if ($n !== '') {
                $out[] = $n;
            }
        }

        return \array_values(\array_unique($out));
    }

    /**
     * @return list<array{name:string,groups:list<array{name:string,series:list<string>}>}>
     */
    public static function getTabs(): array
    {
        $raw = self::getConfig(self::TABS_KEY);
        $arr = \json_decode($raw, true);
        if (\is_array($arr) && $arr !== []) {
            $tabs = self::normalizeTabs($arr);
            if ($tabs !== []) {
                return $tabs;
            }
        }
        $groups = self::legacyGroups();

        return [['name' => 'Übersicht', 'groups' => $groups]];
    }

    /**
     * @param list<array{name?:string,groups?:list<array{name?:string,series?:list<string>}>}> $tabs
     */
    public static function saveTabs(array $tabs): void
    {
        $clean = self::normalizeTabs($tabs);
        self::setConfig(self::TABS_KEY, \json_encode($clean, \JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    /**
     * @return list<array{name:string,series:list<string>}>
     */
    public static function getGroups(): array
    {
        $out = [];
        foreach (self::getTabs() as $tab) {
            foreach ($tab['groups'] as $g) {
                $out[] = $g;
            }
        }

        return $out !== [] ? $out : self::legacyGroups();
    }

    /**
     * @return list<array{name:string,series:list<string>}>
     */
    private static function legacyGroups(): array
    {
        $raw = self::getConfig(self::GROUPS_KEY);
        $arr = \json_decode($raw, true);
        if (\is_array($arr) && $arr !== []) {
            $groups = self::normalizeGroups($arr);
            if ($groups !== []) {
                return $groups;
            }
        }
        $legacy = \json_decode(self::getConfig(self::SERIES_KEY), true);
        if (\is_array($legacy) && $legacy !== []) {
            $series = self::sanitizeSeries($legacy);
            if ($series !== []) {
                return [['name' => 'Serien', 'series' => $series]];
            }
        }

        return [['name' => 'DS Serie', 'series' => self::DS_SERIES]];
    }

    /**
     * @param list<array{name?:string,series?:list<string>}> $groups
     */
    public static function saveGroups(array $groups): void
    {
        $clean = self::normalizeGroups($groups);
        self::setConfig(self::GROUPS_KEY, \json_encode($clean, \JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    public static function seriesFamilyPrefix(string $s): string
    {
        $s = \strtoupper(\trim($s));
        if (\preg_match('/^([A-Z]{1,8})([0-9]*[1-9])0{2,}$/', $s, $m) === 1) {
            return $m[1] . $m[2];
        }
        if (\preg_match('/^([A-Z]{2,8})$/', $s, $m) === 1) {
            return $m[1];
        }

        return '';
    }

    /**
     * @return list<string>
     */
    public static function seriesFromGroupName(string $name): array
    {
        $name = \strtoupper(\trim($name));
        $out  = [];
        if (\preg_match_all('/\b([A-Z]{1,8}[0-9]{1,5})\b/', $name, $m) > 0) {
            foreach ($m[1] as $code) {
                $n = self::normalizeSeries((string)$code);
                if ($n !== '') {
                    $out[] = $n;
                }
            }
        }
        if (\preg_match('/\b([A-Z]{2,8})\s+SERIE\b/', $name, $m) === 1) {
            $out[] = $m[1];
        }

        return \array_values(\array_unique($out));
    }

    /**
     * @return array{exact:list<string>,families:list<string>}
     */
    public static function getLoadSpec(): array
    {
        $exact    = [];
        $families = [];
        foreach (self::getGroups() as $g) {
            $series = self::sanitizeSeries($g['series'] ?? []);
            if ($series !== []) {
                foreach ($series as $s) {
                    $exact[] = $s;
                }
                $hasChild = false;
                $famCodes = [];
                foreach ($series as $s) {
                    $fam = self::seriesFamilyPrefix($s);
                    if ($fam === '') {
                        $hasChild = true;
                    } else {
                        $famCodes[] = $fam;
                    }
                }
                if (!$hasChild) {
                    foreach ($famCodes as $fam) {
                        $families[] = $fam;
                    }
                }
                continue;
            }
            foreach (self::seriesFromGroupName((string)($g['name'] ?? '')) as $s) {
                $n = self::normalizeSeries($s);
                if ($n !== '') {
                    $exact[] = $n;
                    $fam     = self::seriesFamilyPrefix($n);
                    if ($fam !== '') {
                        $families[] = $fam;
                    }
                } elseif (\preg_match('/^[A-Z]{2,8}$/', $s) === 1) {
                    $families[] = $s;
                }
            }
        }
        $exact    = self::sanitizeSeries($exact);
        $families = \array_values(\array_unique($families));
        if ($exact === [] && $families === []) {
            return ['exact' => self::DS_SERIES, 'families' => []];
        }

        return ['exact' => $exact, 'families' => $families];
    }

    public static function getSelectedSeries(): array
    {
        return self::getLoadSpec()['exact'];
    }

    /**
     * @param list<string> $exact
     * @param list<string> $families
     */
    public static function serieMatchesSelected(string $serie, array $exact, array $families): bool
    {
        $serie = \strtoupper(\trim($serie));
        if ($serie === '') {
            return false;
        }
        if (\in_array($serie, $exact, true)) {
            return true;
        }
        foreach ($families as $fam) {
            $fam = \strtoupper(\trim((string)$fam));
            if ($fam !== '' && \str_starts_with($serie, $fam) && \preg_match('/^[A-Z]{1,8}[0-9]{1,5}$/', $serie) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $groups
     * @return list<array{name:string,series:list<string>}>
     */
    public static function normalizeGroups($groups): array
    {
        if (!\is_array($groups)) {
            return [];
        }
        $out = [];
        foreach ($groups as $g) {
            if (!\is_array($g)) {
                continue;
            }
            $name = \trim((string)($g['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (\function_exists('mb_substr')) {
                $name = \mb_substr($name, 0, 80);
            } else {
                $name = \substr($name, 0, 80);
            }
            $series = self::sanitizeSeries(\is_array($g['series'] ?? null) ? $g['series'] : []);
            $out[]  = ['name' => $name, 'series' => $series];
        }

        return $out;
    }

    /**
     * @param mixed $tabs
     * @return list<array{name:string,groups:list<array{name:string,series:list<string>}>}>
     */
    public static function normalizeTabs($tabs): array
    {
        if (!\is_array($tabs)) {
            return [];
        }
        $out = [];
        foreach ($tabs as $t) {
            if (!\is_array($t)) {
                continue;
            }
            $name = \trim((string)($t['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            if (\function_exists('mb_substr')) {
                $name = \mb_substr($name, 0, 80);
            } else {
                $name = \substr($name, 0, 80);
            }
            $out[] = ['name' => $name, 'groups' => self::normalizeGroups($t['groups'] ?? [])];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function getExtraSeries(): array
    {
        $raw = self::getConfig(self::EXTRA_SERIES_KEY);
        $arr = \json_decode($raw, true);
        if (!\is_array($arr)) {
            $arr = \preg_split('/[,\s;]+/', $raw) ?: [];
        }

        return self::sanitizeSeries(\array_map('strval', $arr));
    }

    /**
     * @param list<string> $series
     */
    public static function saveExtraSeries(array $series): void
    {
        self::setConfig(self::EXTRA_SERIES_KEY, \json_encode(self::sanitizeSeries($series), \JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    /**
     * @return list<string>
     */
    public static function sanitizeArtNr(array $nrs): array
    {
        $out = [];
        foreach ($nrs as $n) {
            $v = \trim((string)$n);
            if (\preg_match('/^[0-9]{3,20}$/', $v) === 1) {
                $out[] = $v;
            }
        }

        return \array_values(\array_unique($out));
    }

    /**
     * @return list<string>
     */
    public static function getExtraArtNr(): array
    {
        $raw = self::getConfig(self::EXTRA_ARTNR_KEY);
        $arr = \json_decode($raw, true);
        if (!\is_array($arr)) {
            $arr = \preg_split('/[,\s;]+/', $raw) ?: [];
        }

        return self::sanitizeArtNr(\array_map('strval', $arr));
    }

    /**
     * @param list<string> $nrs
     */
    public static function saveExtraArtNr(array $nrs): void
    {
        self::setConfig(self::EXTRA_ARTNR_KEY, \json_encode(self::sanitizeArtNr($nrs), \JSON_UNESCAPED_UNICODE) ?: '[]');
    }

    /**
     * @return list<array{cArtNr:string,cName:string,nStock:float}>
     */
    public static function listSelectableProducts(): array
    {
        $out = [];
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT TRIM(a.cArtNr) AS cArtNr,
                        COALESCE(NULLIF(TRIM(a.cName), ''), TRIM(a.cArtNr)) AS cName,
                        COALESCE(a.fLagerbestand, 0) AS nStock
                   FROM tartikel a
                  WHERE IFNULL(a.kVaterArtikel, 0) = 0
                    AND TRIM(a.cArtNr) REGEXP '^[0-9]{3,20}$'
                  ORDER BY CAST(a.cArtNr AS UNSIGNED), a.cArtNr
                  LIMIT 800"
            );
            if (\is_array($rows)) {
                foreach ($rows as $r) {
                    $nr = \trim((string)($r->cArtNr ?? ''));
                    if ($nr === '') {
                        continue;
                    }
                    $out[] = [
                        'cArtNr' => $nr,
                        'cName'  => \trim((string)($r->cName ?? $nr)),
                        'nStock' => (float)($r->nStock ?? 0),
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        return $out;
    }

    public static function listAvailableSeries(): array
    {
        $found = self::querySeriesRows(
            "SELECT DISTINCT UPPER(REPLACE(REPLACE(TRIM(a.cName), ' ', ''), '-', '')) AS cSerie
               FROM tartikel a
              WHERE IFNULL(a.kVaterArtikel, 0) = 0
                AND TRIM(a.cArtNr) REGEXP '^[0-9]{3,20}$'
                AND UPPER(REPLACE(REPLACE(TRIM(a.cName), ' ', ''), '-', '')) REGEXP '^[A-Z]{1,8}[0-9]{1,5}$'
              LIMIT 400"
        );
        $found = \array_merge(
            $found,
            self::querySeriesRows(
                "SELECT DISTINCT UPPER(REPLACE(REPLACE(TRIM(att_m.cWert), ' ', ''), '-', '')) AS cSerie
                   FROM tartikelattribut att_m
                   INNER JOIN tartikel a ON a.kArtikel = att_m.kArtikel
                  WHERE att_m.cName = 'Modell'
                    AND TRIM(a.cArtNr) REGEXP '^[0-9]{3,20}$'
                    AND UPPER(REPLACE(REPLACE(TRIM(att_m.cWert), ' ', ''), '-', '')) REGEXP '^[A-Z]{1,8}[0-9]{1,5}$'
                  LIMIT 400"
            ),
            self::seriesFromExtraArtNr()
        );

        $manualSeries = [];
        foreach (self::getManualProducts() as $m) {
            if ($m['cSerie'] !== '') {
                $manualSeries[] = $m['cSerie'];
            }
        }

        $all = \array_merge(
            self::DS_SERIES,
            \array_keys(self::SERIES_ARTNR),
            self::getSelectedSeries(),
            self::getExtraSeries(),
            $manualSeries,
            $found
        );

        return self::sanitizeSeries($all);
    }

    /**
     * @return list<string>
     */
    private static function seriesFromExtraArtNr(): array
    {
        $nrs = self::getExtraArtNr();
        if ($nrs === []) {
            return [];
        }
        $quoted = [];
        foreach ($nrs as $nr) {
            $quoted[] = "'" . $nr . "'";
        }

        $rows = [];
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT UPPER(REPLACE(REPLACE(TRIM(a.cName), \' \', \'\'), \'-\', \'\')) AS cNameKey,
                        UPPER(REPLACE(REPLACE(TRIM(IFNULL(att_m.cWert, \'\')), \' \', \'\'), \'-\', \'\')) AS cModell
                   FROM tartikel a
                   LEFT JOIN tartikelattribut att_m
                          ON att_m.kArtikel = a.kArtikel AND att_m.cName = \'Modell\'
                  WHERE BINARY TRIM(a.cArtNr) IN (' . \implode(',', $quoted) . ')
                  LIMIT 200'
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        if (\is_array($rows)) {
            foreach ($rows as $r) {
                foreach ([(string)($r->cModell ?? ''), (string)($r->cNameKey ?? '')] as $raw) {
                    $n = self::normalizeSeries($raw);
                    if ($n !== '') {
                        $out[] = $n;
                        continue;
                    }
                    if (\preg_match('/^([A-Z]{1,8}[0-9]{1,5})/', $raw, $m) === 1) {
                        $out[] = $m[1];
                    }
                }
            }
        }

        return self::sanitizeSeries($out);
    }

    /**
     * @return list<string>
     */
    private static function querySeriesRows(string $sql): array
    {
        $found = [];
        try {
            $rows = Shop::Container()->getDB()->getObjects($sql);
            if (\is_array($rows)) {
                foreach ($rows as $r) {
                    $n = self::normalizeSeries((string)($r->cSerie ?? ''));
                    if ($n !== '') {
                        $found[] = $n;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return $found;
    }

    private static function getConfig(string $key): string
    {
        self::ensureTables();
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT cValue FROM ' . self::CONFIG_TABLE . ' WHERE cKey = :k LIMIT 1',
                ['k' => $key]
            );

            return $row !== null ? (string)($row->cValue ?? '') : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function setConfig(string $key, string $value): void
    {
        self::ensureTables();
        try {
            Shop::Container()->getDB()->executeQueryPrepared(
                'INSERT INTO ' . self::CONFIG_TABLE . ' (cKey, cValue) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE cValue = :v2',
                ['k' => $key, 'v' => $value, 'v2' => $value]
            );
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param list<string> $series
     */
    private static function seriesSql(array $series): string
    {
        $parts = [];
        foreach (self::sanitizeSeries($series) as $s) {
            $parts[] = "'" . $s . "'";
        }

        return $parts !== [] ? \implode(',', $parts) : "'__none__'";
    }

    /**
     * @param list<string> $families
     */
    private static function familyRegex(array $families): string
    {
        $safe = [];
        foreach ($families as $f) {
            $f = \strtoupper(\trim((string)$f));
            if (\preg_match('/^[A-Z]{1,8}[0-9]{0,5}$/', $f) === 1) {
                $safe[] = $f;
            }
        }
        if ($safe === []) {
            return '';
        }

        return '^(' . \implode('|', $safe) . ')[0-9]+$';
    }

    public static function readStoredToken(): string
    {
        self::ensureTables();
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT cValue FROM ' . self::CONFIG_TABLE . ' WHERE cKey = :k LIMIT 1',
                ['k' => self::TOKEN_KEY]
            );
            $val = $row !== null ? \trim((string)($row->cValue ?? '')) : '';
            if ($val !== '' && \strlen($val) >= 32) {
                return $val;
            }
        } catch (\Throwable $e) {
        }

        return '';
    }

    public static function getToken(bool $regenerate = false): string
    {
        if (!$regenerate) {
            $existing = self::readStoredToken();
            if ($existing !== '') {
                return $existing;
            }
        }

        $token = \bin2hex(\random_bytes(32));
        try {
            self::ensureTables();
            Shop::Container()->getDB()->executeQueryPrepared(
                'INSERT INTO ' . self::CONFIG_TABLE . ' (cKey, cValue) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE cValue = :v2',
                ['k' => self::TOKEN_KEY, 'v' => $token, 'v2' => $token]
            );
        } catch (\Throwable $e) {
        }

        return $token;
    }

    public static function publicUrl(string $token): string
    {
        $base = \rtrim((string)Shop::getURL(), '/');

        return $base . '/?' . \http_build_query(['ml_list' => '1', 'ml_token' => $token]);
    }

    /**
     * @return array{ml_list:string,token:string,ml_quote:string}
     */
    public static function requestParams(): array
    {
        $qs = [];
        $raw = (string)(\parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_QUERY) ?? '');
        if ($raw !== '') {
            \parse_str($raw, $qs);
        }
        $mlList = \trim((string)($qs['ml_list'] ?? $_GET['ml_list'] ?? $_REQUEST['ml_list'] ?? ''));
        $token  = \trim((string)(
            $qs['ml_token']
            ?? $qs['token']
            ?? $_GET['ml_token']
            ?? $_GET['token']
            ?? $_REQUEST['ml_token']
            ?? $_REQUEST['token']
            ?? ''
        ));
        $mlQuote = \trim((string)($qs['ml_quote'] ?? $_GET['ml_quote'] ?? $_POST['ml_quote'] ?? ''));

        return ['ml_list' => $mlList, 'token' => $token, 'ml_quote' => $mlQuote];
    }

    public static function tokenIsValid(string $token): bool
    {
        $token    = \trim($token);
        $expected = self::readStoredToken();
        if ($expected === '' || $token === '' || \strlen($expected) !== \strlen($token)) {
            return false;
        }

        return \hash_equals($expected, $token);
    }

    public static ?string $lastLoadError = null;

    /**
     * @return list<array{
     *   kArtikel:int,cArtNr:string,cName:string,cSerie:string,nStock:float,
     *   fNet:float,fGross:float,cImg:string,cImgLg:string
     * }>
     */
    public static function loadDsLagerProducts(): array
    {
        self::$lastLoadError = null;
        $db  = Shop::Container()->getDB();
        $lid = 1;
        try {
            $lid = (int)Shop::getLanguageID();
            if ($lid <= 0) {
                $lid = 1;
            }
        } catch (\Throwable $e) {
        }

        $spec      = self::getLoadSpec();
        $selected  = $spec['exact'];
        $families  = $spec['families'];
        $seriesSql = self::seriesSql($selected);
        $normName  = "UPPER(REPLACE(REPLACE(REPLACE(TRIM(%s), ' ', ''), '-', ''), '_', ''))";
        $aName     = \sprintf($normName, 'a.cName');
        $tsName    = \sprintf($normName, "IFNULL(ts.cName, '')");
        $famRegex  = self::familyRegex($families);
        $famSql    = $famRegex !== ''
            ? " OR {$aName} REGEXP '{$famRegex}' OR {$tsName} REGEXP '{$famRegex}'"
            : '';
        $famCase   = $famRegex !== ''
            ? " WHEN {$aName} REGEXP '{$famRegex}' THEN {$aName} WHEN {$tsName} REGEXP '{$famRegex}' THEN {$tsName}"
            : '';
        $params    = ['lid' => $lid];

        $sql = "
            SELECT
                a.kArtikel,
                COALESCE(a.cArtNr, '') AS cArtNr,
                COALESCE(NULLIF(ts.cName, ''), NULLIF(a.cName, ''), '') AS cName,
                COALESCE(a.cSeo, '') AS cSeo,
                CASE
                    WHEN {$aName} IN ({$seriesSql}) THEN {$aName}
                    WHEN {$tsName} IN ({$seriesSql}) THEN {$tsName}
                    {$famCase}
                    ELSE ''
                END AS cSerie,
                COALESCE(a.fLagerbestand, 0) AS nStock,
                COALESCE(a.fStandardpreisNetto, 0) AS fNetFallback,
                COALESCE(a.fMwSt, 0) AS fMwSt,
                COALESCE(pic.nNr, 1) AS nNr
            FROM tartikel a
            LEFT JOIN tartikelattribut att_m
                   ON att_m.kArtikel = a.kArtikel
                  AND att_m.cName = 'Modell'
            LEFT JOIN tartikelsprache ts ON ts.kArtikel = a.kArtikel AND ts.kSprache IN (1, :lid)
            LEFT JOIN (
                SELECT p1.kArtikel, MIN(p1.nNr) AS nNr
                  FROM tartikelpict p1
                 GROUP BY p1.kArtikel
            ) pic ON pic.kArtikel = a.kArtikel
            WHERE IFNULL(a.kVaterArtikel, 0) = 0
              AND (
                    {$aName} IN ({$seriesSql})
                 OR {$tsName} IN ({$seriesSql})
                 {$famSql}
              )
              AND a.cArtNr REGEXP '^[0-9]{3,6}$'
            ORDER BY a.cArtNr ASC, a.kArtikel ASC
            LIMIT 600
        ";

        $rows = self::runProductQuery($db, $sql, $params);
        $haveSeries = [];
        foreach ($rows as $r) {
            $s = self::normalizeSeries((string)($r->cSerie ?? ''));
            if ($s !== '') {
                $haveSeries[$s] = true;
            }
        }
        $missing = [];
        foreach ($selected as $s) {
            if (!isset($haveSeries[$s])) {
                $missing[] = $s;
            }
        }
        $extraArt = self::getExtraArtNr();
        $artNrs = [];
        foreach ($selected as $s) {
            foreach (self::SERIES_ARTNR[$s] ?? [] as $nr) {
                $artNrs[] = "'" . $nr . "'";
            }
        }
        foreach ($extraArt as $nr) {
            $artNrs[] = "'" . $nr . "'";
        }
        $artNrs = \array_values(\array_unique($artNrs));
        if ($missing !== [] || $artNrs !== []) {
            $likes = [];
            foreach ($missing as $s) {
                $likes[] = "UPPER(REPLACE(REPLACE(TRIM(a.cName), ' ', ''), '-', '')) = '" . $s . "'";
            }
            if ($artNrs !== []) {
                $likes[] = 'BINARY TRIM(a.cArtNr) IN (' . \implode(',', $artNrs) . ')';
            }
            if ($likes === []) {
                $likes[] = '0=1';
            }
            $sqlMiss = "
                SELECT
                    a.kArtikel,
                    COALESCE(a.cArtNr, '') AS cArtNr,
                    COALESCE(NULLIF(ts.cName, ''), NULLIF(a.cName, ''), '') AS cName,
                    COALESCE(a.cSeo, '') AS cSeo,
                    '' AS cSerie,
                    COALESCE(a.fLagerbestand, 0) AS nStock,
                    COALESCE(a.fStandardpreisNetto, 0) AS fNetFallback,
                    COALESCE(a.fMwSt, 0) AS fMwSt,
                    COALESCE(pic.nNr, 1) AS nNr
                FROM tartikel a
                LEFT JOIN tartikelsprache ts ON ts.kArtikel = a.kArtikel AND ts.kSprache IN (1, :lid)
                LEFT JOIN (
                    SELECT p1.kArtikel, MIN(p1.nNr) AS nNr
                      FROM tartikelpict p1
                     GROUP BY p1.kArtikel
                ) pic ON pic.kArtikel = a.kArtikel
                WHERE (" . \implode(' OR ', $likes) . ")
                LIMIT 400
            ";
            $seen = [];
            foreach ($rows as $r) {
                $seen[(int)($r->kArtikel ?? 0)] = true;
            }
            foreach (self::runProductQuery($db, $sqlMiss, $params) as $r) {
                $id = (int)($r->kArtikel ?? 0);
                if ($id > 0 && !isset($seen[$id])) {
                    $rows[] = $r;
                }
            }
        }

        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $out     = [];
        foreach ($rows as $r) {
            $kArtikel = (int)($r->kArtikel ?? 0);
            if ($kArtikel <= 0) {
                continue;
            }
            $mwst = (float)($r->fMwSt ?? 0.0);
            $net  = self::loadNettoPrice($kArtikel, (float)($r->fNetFallback ?? 0.0));
            $name = \trim((string)($r->cName ?? ''));
            $art  = \trim((string)($r->cArtNr ?? ''));
            $serie = self::normalizeSeries((string)($r->cSerie ?? ''));
            $nameKey = \strtoupper(\str_replace([' ', '-', '_'], '', $name));
            $isExtraArt = \in_array($art, $extraArt, true);
            $isMapped   = false;
            foreach (self::SERIES_ARTNR as $ser => $nrs) {
                if (\in_array($art, $nrs, true)) {
                    $serie    = $ser;
                    $isMapped = true;
                    break;
                }
            }
            $isLagerName = \preg_match('/^[0-9]{3,6}$/', $art) === 1
                && (
                    self::serieMatchesSelected($serie, $selected, $families)
                    || self::serieMatchesSelected($nameKey, $selected, $families)
                );
            if ($isLagerName && $serie === '') {
                $serie = $nameKey;
            }
            if ($isExtraArt && !$isLagerName && !$isMapped) {
                $serie = self::normalizeSeries($nameKey) !== '' ? $nameKey : $art;
            }
            if (!$isExtraArt && !$isMapped && !$isLagerName) {
                continue;
            }
            if ($serie === '' || (!self::serieMatchesSelected($serie, $selected, $families) && !$isExtraArt)) {
                continue;
            }
            $imgs = self::productImages(
                $shopUrl,
                $kArtikel,
                $name !== '' ? $name : $art,
                \trim((string)($r->cSeo ?? '')),
                $art,
                (int)($r->nNr ?? 1)
            );
            $out[] = [
                'kArtikel' => $kArtikel,
                'cArtNr'   => $art,
                'cName'    => $name !== '' ? $name : $art,
                'cSerie'   => $serie,
                'nStock'   => (float)($r->nStock ?? 0),
                'fNet'     => \round($net, 2),
                'fGross'   => \round($net * (1.0 + \max(0.0, $mwst) / 100.0), 2),
                'cImg'     => $imgs['md'],
                'cImgLg'   => $imgs['lg'],
            ];
        }

        foreach (self::manualProductsAsList() as $manual) {
            $out[] = $manual;
        }

        return $out;
    }

    /**
     * @return list<array{
     *   id:string,cSerie:string,cArtNr:string,cName:string,nStock:float,fGross:float,cImg:string,nTab:int,nGroup:int
     * }>
     */
    public static function getManualProducts(): array
    {
        $raw = self::getConfig(self::MANUAL_KEY);
        $arr = \json_decode($raw, true);
        if (!\is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $clean = self::sanitizeManualRow($row);
            if ($clean !== null) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    public static function saveManualProducts(array $rows): void
    {
        $clean = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $item = self::sanitizeManualRow($row);
            if ($item !== null) {
                $clean[] = $item;
            }
        }
        self::setConfig(self::MANUAL_KEY, \json_encode($clean, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '[]');
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:string,cSerie:string,cArtNr:string,cName:string,nStock:float,fGross:float,cImg:string,nTab:int,nGroup:int}|null
     */
    public static function sanitizeManualRow(array $row): ?array
    {
        $art  = \trim((string)($row['cArtNr'] ?? $row['artnr'] ?? ''));
        $name = \trim((string)($row['cName'] ?? $row['name'] ?? ''));
        $serieRaw = \strtoupper(\trim((string)($row['cSerie'] ?? $row['serie'] ?? '')));
        $serie = self::normalizeSeries($serieRaw);
        if ($serie === '' && \preg_match('/^[A-Z0-9]{1,16}$/', $serieRaw) === 1) {
            $serie = $serieRaw;
        }
        if ($art === '' && $name === '' && $serie === '') {
            return null;
        }
        $id = \trim((string)($row['id'] ?? ''));
        if ($id === '' || \preg_match('/^[a-zA-Z0-9_-]{1,40}$/', $id) !== 1) {
            $id = 'm' . \bin2hex(\random_bytes(4));
        }
        $img = \trim((string)($row['cImg'] ?? $row['img'] ?? ''));
        if ($img !== '' && !\preg_match('#^https?://#i', $img) && !\str_starts_with($img, '/')) {
            $img = '';
        }
        $stock = (float)\str_replace(',', '.', (string)($row['nStock'] ?? $row['stock'] ?? 0));
        $gross = (float)\str_replace(',', '.', (string)($row['fGross'] ?? $row['gross'] ?? 0));
        $place = \trim((string)($row['place'] ?? ''));
        $nTab = -1;
        $nGroup = -1;
        if (\preg_match('/^(\d+):(\d+)$/', $place, $m) === 1) {
            $nTab   = (int)$m[1];
            $nGroup = (int)$m[2];
        } else {
            $nTab   = (int)($row['nTab'] ?? $row['tab'] ?? -1);
            $nGroup = (int)($row['nGroup'] ?? $row['group'] ?? -1);
        }

        return [
            'id'     => $id,
            'cSerie' => $serie,
            'cArtNr' => $art !== '' ? $art : $id,
            'cName'  => $name !== ''
                ? (\function_exists('mb_substr') ? \mb_substr($name, 0, 80) : \substr($name, 0, 80))
                : ($serie !== '' ? $serie : $art),
            'nStock' => \max(0.0, $stock),
            'fGross' => \max(0.0, $gross),
            'cImg'   => $img,
            'nTab'   => $nTab,
            'nGroup' => $nGroup,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function manualProductsAsList(): array
    {
        $out = [];
        $i   = 0;
        foreach (self::getManualProducts() as $m) {
            $i++;
            $img = (string)$m['cImg'];
            $out[] = [
                'kArtikel' => -1000 - $i,
                'cArtNr'   => $m['cArtNr'],
                'cName'    => $m['cName'],
                'cSerie'   => $m['cSerie'] !== '' ? $m['cSerie'] : $m['cArtNr'],
                'nStock'   => $m['nStock'],
                'fNet'     => \round($m['fGross'] / 1.19, 2),
                'fGross'   => \round($m['fGross'], 2),
                'cImg'     => $img,
                'cImgLg'   => $img,
                'cManual'  => true,
                'nTab'     => (int)$m['nTab'],
                'nGroup'   => (int)$m['nGroup'],
            ];
        }

        return $out;
    }

    public static function uploadDir(): string
    {
        return \dirname(__DIR__) . '/uploads';
    }

    public static function uploadPublicBase(): string
    {
        return \rtrim((string)Shop::getURL(), '/') . '/plugins/MesseList/uploads';
    }

    /**
     * @param array<string,mixed> $file
     */
    public static function storeManualImage(array $file): string
    {
        $tmp = (string)($file['tmp_name'] ?? '');
        $err = (int)($file['error'] ?? \UPLOAD_ERR_NO_FILE);
        $size = (int)($file['size'] ?? 0);
        if ($err !== \UPLOAD_ERR_OK || $tmp === '' || !\is_uploaded_file($tmp) || $size <= 0 || $size > 4_000_000) {
            return '';
        }
        $info = @\getimagesize($tmp);
        if (!\is_array($info) || !isset($info[2])) {
            return '';
        }
        $ext = match ((int)$info[2]) {
            \IMAGETYPE_JPEG => 'jpg',
            \IMAGETYPE_PNG  => 'png',
            \IMAGETYPE_WEBP => 'webp',
            \IMAGETYPE_GIF  => 'gif',
            default         => '',
        };
        if ($ext === '') {
            return '';
        }
        $dir = self::uploadDir();
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0755, true);
        }
        $name = 'ml_' . \bin2hex(\random_bytes(8)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!@\move_uploaded_file($tmp, $dest)) {
            return '';
        }

        return self::uploadPublicBase() . '/' . $name;
    }

    /**
     * @param list<array{id:string,cSerie:string,cArtNr:string,cName:string,nStock:float,fGross:float,cImg:string}> $current
     * @param array<string,mixed> $posted
     * @param array<string,mixed>|null $files
     * @return list<array{id:string,cSerie:string,cArtNr:string,cName:string,nStock:float,fGross:float,cImg:string}>
     */
    public static function applyManualPost(array $current, array $posted, ?array $files): array
    {
        $byId = [];
        foreach ($current as $row) {
            $byId[$row['id']] = $row;
        }
        $out = [];
        foreach ($posted as $id => $row) {
            if (!\is_array($row)) {
                continue;
            }
            $id = (string)$id;
            $row['id'] = $id;
            if (isset($byId[$id]['cImg']) && \trim((string)($row['img'] ?? $row['cImg'] ?? '')) === '') {
                $row['cImg'] = $byId[$id]['cImg'];
            }
            $file = null;
            if (\is_array($files) && isset($files['name']) && \is_array($files['name']) && isset($files['name'][$id])) {
                $file = [
                    'name'     => $files['name'][$id] ?? '',
                    'type'     => $files['type'][$id] ?? '',
                    'tmp_name' => $files['tmp_name'][$id] ?? '',
                    'error'    => $files['error'][$id] ?? \UPLOAD_ERR_NO_FILE,
                    'size'     => $files['size'][$id] ?? 0,
                ];
            }
            if (\is_array($file)) {
                $url = self::storeManualImage($file);
                if ($url !== '') {
                    $row['cImg'] = $url;
                }
            }
            $clean = self::sanitizeManualRow($row);
            if ($clean !== null) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /**
     * @param list<array{id:string,cSerie:string,cArtNr:string,cName:string,nStock:float,fGross:float,cImg:string}> $current
     * @param array<string,mixed> $post
     * @param array<string,mixed>|null $file
     * @return list<array{id:string,cSerie:string,cArtNr:string,cName:string,nStock:float,fGross:float,cImg:string}>
     */
    public static function addManualFromFields(array $current, array $post, ?array $file): array
    {
        $row = [
            'id'     => 'm' . \bin2hex(\random_bytes(4)),
            'serie'  => $post['ml_new_serie'] ?? '',
            'artnr'  => $post['ml_new_artnr'] ?? '',
            'name'   => $post['ml_new_name'] ?? '',
            'stock'  => $post['ml_new_stock'] ?? 0,
            'gross'  => $post['ml_new_gross'] ?? 0,
            'place'  => $post['ml_new_place'] ?? '',
            'cImg'   => '',
        ];
        if (\is_array($file)) {
            $url = self::storeManualImage($file);
            if ($url !== '') {
                $row['cImg'] = $url;
            }
        }
        $clean = self::sanitizeManualRow($row);
        if ($clean !== null) {
            $current[] = $clean;
        }

        return $current;
    }

    /**
     * @param list<array{id:string,cSerie:string,cArtNr:string,cName:string,nStock:float,fGross:float,cImg:string}> $current
     * @return list<array{id:string,cSerie:string,cArtNr:string,cName:string,nStock:float,fGross:float,cImg:string}>
     */
    public static function removeManualProduct(array $current, string $id): array
    {
        $out = [];
        foreach ($current as $row) {
            if ($row['id'] === $id) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    public static function trgDir(): string
    {
        return \dirname(__DIR__) . '/TRG';
    }

    public static function trgPublicBase(): string
    {
        return \rtrim((string)Shop::getURL(), '/') . '/plugins/MesseList/TRG';
    }

    /**
     * @return array{nTab:int,nGroup:int}
     */
    public static function findTrSeriePlace(): array
    {
        foreach (self::getTabs() as $ti => $tab) {
            foreach (($tab['groups'] ?? []) as $gi => $grp) {
                $name = \strtoupper(\preg_replace('/\s+/', ' ', \trim((string)($grp['name'] ?? ''))) ?? '');
                if ($name === 'TR SERIE' || \preg_match('/\bTR\s*SERIE\b/', $name) === 1) {
                    return ['nTab' => (int)$ti, 'nGroup' => (int)$gi];
                }
            }
        }
        $tabs = self::getTabs();
        if ($tabs === []) {
            $tabs = [['name' => 'Übersicht', 'groups' => [['name' => 'TR SERIE', 'series' => []]]]];
            self::saveTabs($tabs);

            return ['nTab' => 0, 'nGroup' => 0];
        }
        $tabs[0]['groups'][] = ['name' => 'TR SERIE', 'series' => []];
        self::saveTabs($tabs);

        return ['nTab' => 0, 'nGroup' => \count($tabs[0]['groups']) - 1];
    }

    public static function ensureTrgManualProducts(): int
    {
        $dir = self::trgDir();
        if (!\is_dir($dir)) {
            return 0;
        }
        $files = \glob($dir . '/*.png') ?: [];
        $nums  = [];
        foreach ($files as $file) {
            $base = \basename((string)$file, '.png');
            if (\preg_match('/^\d+$/', $base) === 1) {
                $nums[] = (int)$base;
            }
        }
        \sort($nums, \SORT_NUMERIC);
        if ($nums === []) {
            return 0;
        }
        $place  = self::findTrSeriePlace();
        $baseUrl = self::trgPublicBase();
        $current = self::getManualProducts();
        $byId    = [];
        foreach ($current as $i => $row) {
            $byId[$row['id']] = $i;
        }
        $changed = 0;
        foreach ($nums as $n) {
            $id  = 'trg_' . $n;
            $code = 'TR' . $n;
            $clean = self::sanitizeManualRow([
                'id'     => $id,
                'cSerie' => $code,
                'cArtNr' => $code,
                'cName'  => $code,
                'nStock' => 100,
                'fGross' => 39,
                'cImg'   => $baseUrl . '/' . $n . '.png',
                'nTab'   => $place['nTab'],
                'nGroup' => $place['nGroup'],
            ]);
            if ($clean === null) {
                continue;
            }
            if (isset($byId[$id])) {
                $old = $current[$byId[$id]];
                if (
                    $old['cSerie'] === $clean['cSerie']
                    && $old['cArtNr'] === $clean['cArtNr']
                    && $old['cName'] === $clean['cName']
                    && (int)$old['nTab'] === (int)$clean['nTab']
                    && (int)$old['nGroup'] === (int)$clean['nGroup']
                    && (string)$old['cImg'] === (string)$clean['cImg']
                ) {
                    continue;
                }
                $current[$byId[$id]] = $clean;
            } else {
                $current[] = $clean;
            }
            $changed++;
        }
        if ($changed > 0) {
            self::saveManualProducts($current);
        }

        return $changed;
    }

    private static function loadNettoPrice(int $kArtikel, float $fallback): float
    {
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT pd.fVKNetto AS fVKNetto
                   FROM tpreis p
                   INNER JOIN tpreisdetail pd ON pd.kPreis = p.kPreis
                  WHERE p.kArtikel = :k
                    AND p.kKundengruppe = 1
                    AND (p.kKunde IS NULL OR p.kKunde = 0)
                    AND pd.nAnzahlAb = 0
                  LIMIT 1',
                ['k' => $kArtikel]
            );
            $net = $row !== null ? (float)($row->fVKNetto ?? 0.0) : 0.0;
            if ($net > 0) {
                return $net;
            }
        } catch (\Throwable $e) {
        }

        return $fallback;
    }

    /**
     * @return array{kKategorie:int,lft:int,rght:int}|null
     */
    private static function findLagerCategory(): ?array
    {
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                "SELECT k.kKategorie, k.lft, k.rght
                   FROM tkategorie k
                   LEFT JOIN tkategoriesprache ks
                          ON ks.kKategorie = k.kKategorie AND ks.kSprache = 1
                  WHERE TRIM(k.cName) = 'Lager'
                     OR TRIM(IFNULL(ks.cName, '')) = 'Lager'
                     OR TRIM(k.cSeo) IN ('Lager', 'lager')
                  ORDER BY CASE WHEN IFNULL(k.kOberKategorie, 0) = 0 THEN 0 ELSE 1 END,
                           k.kKategorie ASC
                  LIMIT 1"
            );
            if ($row === null) {
                return null;
            }
            $id = (int)($row->kKategorie ?? 0);
            $lft = (int)($row->lft ?? 0);
            $rght = (int)($row->rght ?? 0);
            if ($id <= 0 || $lft <= 0 || $rght <= $lft) {
                return null;
            }

            return ['kKategorie' => $id, 'lft' => $lft, 'rght' => $rght];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return list<object>
     */
    private static function runProductQuery(object $db, string $sql, array $params): array
    {
        try {
            $found = $db->getObjects($sql, $params);
            if (\is_array($found)) {
                return $found;
            }
            if (method_exists($db, 'getErrorMessage')) {
                $err = \trim((string)$db->getErrorMessage());
                if ($err !== '') {
                    self::$lastLoadError = $err;
                }
            }
        } catch (\Throwable $e) {
            self::$lastLoadError = $e->getMessage();
        }

        return [];
    }

    /**
     * @return array{md:string,lg:string}
     */
    private static function productImages(
        string $shopUrl,
        int $kArtikel,
        string $name,
        string $seo,
        string $artNr,
        int $nNr
    ): array {
        $nNr = \max(1, $nNr);
        $obj                 = new \stdClass();
        $obj->kArtikel       = $kArtikel;
        $obj->cName          = $name;
        $obj->cSeo           = $seo;
        $obj->originalSeo    = $seo;
        $obj->cArtNr         = $artNr;
        $obj->cBarcode       = '';
        $obj->customImgName  = '';

        $md = '';
        $lg = '';
        try {
            if (\class_exists(\JTL\Media\Image\Product::class) && \class_exists(\JTL\Media\Image::class)) {
                $sizeMd = \defined(\JTL\Media\Image::class . '::SIZE_MD')
                    ? \JTL\Media\Image::SIZE_MD
                    : \JTL\Media\Image::SIZE_LG;
                $relMd = \JTL\Media\Image\Product::getThumb(
                    \JTL\Media\Image::TYPE_PRODUCT,
                    $kArtikel,
                    $obj,
                    $sizeMd,
                    $nNr
                );
                $relLg = \JTL\Media\Image\Product::getThumb(
                    \JTL\Media\Image::TYPE_PRODUCT,
                    $kArtikel,
                    $obj,
                    \JTL\Media\Image::SIZE_LG,
                    $nNr
                );
                if (\is_string($relMd) && \trim($relMd) !== '') {
                    $md = $shopUrl . '/' . \ltrim(\str_replace('\\', '/', $relMd), '/');
                }
                if (\is_string($relLg) && \trim($relLg) !== '') {
                    $lg = $shopUrl . '/' . \ltrim(\str_replace('\\', '/', $relLg), '/');
                }
            }
        } catch (\Throwable $e) {
        }

        if ($md === '') {
            $clean = \trim($name);
            try {
                if (\class_exists(\JTL\Media\Image\Product::class)) {
                    $fromJtl = \JTL\Media\Image\Product::getCustomName($obj);
                    if (\is_string($fromJtl) && \trim($fromJtl) !== '') {
                        $clean = \trim($fromJtl);
                    }
                }
            } catch (\Throwable $e) {
            }
            if ($clean === '') {
                $clean = (string)$kArtikel;
            }
            $clean  = \str_replace(['/', '\\', ' '], ['-', '-', '-'], $clean);
            $suffix = $nNr > 1 ? ('~' . $nNr) : '';
            $md     = $shopUrl . '/media/image/product/' . $kArtikel . '/md/' . $clean . $suffix . '.jpg';
            $lg     = $shopUrl . '/media/image/product/' . $kArtikel . '/lg/' . $clean . $suffix . '.jpg';
        }
        $md = self::rewriteImageSize($md, 'md');
        $lg = self::rewriteImageSize($lg !== '' ? $lg : $md, 'lg');

        return ['md' => $md, 'lg' => $lg];
    }

    private static function rewriteImageSize(string $url, string $size): string
    {
        if ($url === '' || !\in_array($size, ['md', 'lg'], true)) {
            return $url;
        }
        $out = \preg_replace('#/(xs|sm|md|lg|xl)/#', '/' . $size . '/', $url, 1);

        return \is_string($out) ? $out : $url;
    }

    public static function shopName(): string
    {
        try {
            if (\defined('CONF_GLOBAL')) {
                $name = (string)(Shop::getSettingValue(\CONF_GLOBAL, 'global_shopname') ?: '');
                if ($name !== '') {
                    return $name;
                }
            }
        } catch (\Throwable $e) {
        }

        return 'Bremer Sitzbezüge';
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function getQuotes(int $limit = 200): array
    {
        self::ensureTables();
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT kID, cNr, dCreated, cVorname, cNachname, cTelefon, cEmail, cItems, fTotal, nMailed
                   FROM ' . self::QUOTES_TABLE . '
                  ORDER BY kID DESC
                  LIMIT ' . \max(1, $limit)
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach (\is_array($rows) ? $rows : [] as $row) {
            $q = self::hydrateQuote($row);
            if ($q !== null) {
                $out[] = $q;
            }
        }

        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function getQuote(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        self::ensureTables();
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT * FROM ' . self::QUOTES_TABLE . ' WHERE kID = :k LIMIT 1',
                ['k' => $id]
            );
        } catch (\Throwable $e) {
            return null;
        }

        return $row !== null ? self::hydrateQuote($row) : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function hydrateQuote(object $row): ?array
    {
        $items = \json_decode((string)($row->cItems ?? '[]'), true);
        if (!\is_array($items)) {
            $items = [];
        }

        return [
            'id'       => (int)($row->kID ?? 0),
            'nr'       => (string)($row->cNr ?? ''),
            'created'  => (string)($row->dCreated ?? ''),
            'vorname'  => (string)($row->cVorname ?? ''),
            'nachname' => (string)($row->cNachname ?? ''),
            'telefon'  => (string)($row->cTelefon ?? ''),
            'email'    => (string)($row->cEmail ?? ''),
            'items'    => $items,
            'total'    => (float)($row->fTotal ?? 0),
            'mailed'   => (int)($row->nMailed ?? 0) === 1,
            'html'     => (string)($row->cHtml ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{ok:bool,error:string,id:int,nr:string,mailed:bool}
     */
    public static function createAndSendQuote(array $input): array
    {
        $fail = static fn(string $e): array => ['ok' => false, 'error' => $e, 'id' => 0, 'nr' => '', 'mailed' => false];
        $vor  = \trim((string)($input['vorname'] ?? ''));
        $nach = \trim((string)($input['nachname'] ?? ''));
        $tel  = \trim((string)($input['telefon'] ?? ''));
        $mail = \trim((string)($input['email'] ?? ''));
        if ($vor === '' || $nach === '' || $tel === '' || $mail === '') {
            return $fail('Bitte Vorname, Nachname, Telefon und E-Mail ausfüllen.');
        }
        if (!\filter_var($mail, \FILTER_VALIDATE_EMAIL)) {
            return $fail('Bitte eine gültige E-Mail-Adresse angeben.');
        }
        $items = $input['items'] ?? [];
        if (!\is_array($items) || $items === []) {
            return $fail('Die Gesamtliste ist leer.');
        }
        $clean = [];
        $total = 0.0;
        foreach ($items as $it) {
            if (!\is_array($it)) {
                continue;
            }
            $qty  = (float)\str_replace(',', '.', (string)($it['qty'] ?? 0));
            $disc = (float)\str_replace(',', '.', (string)($it['disc'] ?? 0));
            $gross = (float)\str_replace(',', '.', (string)($it['gross'] ?? $it['fGross'] ?? 0));
            if ($qty <= 0 || $qty > 9999 || $gross < 0) {
                continue;
            }
            $disc = \min(100.0, \max(0.0, $disc));
            $line = \round($qty * $gross * (1 - $disc / 100), 2);
            $total += $line;
            $clean[] = [
                'artnr'  => \mb_substr(\trim((string)($it['artnr'] ?? $it['cArtNr'] ?? '')), 0, 40),
                'serie'  => \mb_substr(\trim((string)($it['serie'] ?? $it['cSerie'] ?? '')), 0, 20),
                'name'   => \mb_substr(\trim((string)($it['name'] ?? $it['cName'] ?? '')), 0, 80),
                'qty'    => $qty,
                'disc'   => $disc,
                'gross'  => \round($gross, 2),
                'line'   => $line,
            ];
            if (\count($clean) >= 200) {
                break;
            }
        }
        if ($clean === []) {
            return $fail('Keine gültigen Artikel in der Gesamtliste.');
        }
        $nr   = self::nextQuoteNr();
        $html = self::renderQuoteHtml([
            'nr'       => $nr,
            'created'  => \date('Y-m-d H:i:s'),
            'vorname'  => $vor,
            'nachname' => $nach,
            'telefon'  => $tel,
            'email'    => $mail,
            'items'    => $clean,
            'total'    => \round($total, 2),
        ]);
        $sent = self::sendQuoteMail($mail, \trim($vor . ' ' . $nach), $nr, $html);
        self::ensureTables();
        $id = 0;
        try {
            $obj            = new \stdClass();
            $obj->cNr       = $nr;
            $obj->dCreated  = \date('Y-m-d H:i:s');
            $obj->cVorname  = \mb_substr($vor, 0, 80);
            $obj->cNachname = \mb_substr($nach, 0, 80);
            $obj->cTelefon  = \mb_substr($tel, 0, 40);
            $obj->cEmail    = \mb_substr($mail, 0, 160);
            $obj->cItems    = \json_encode($clean, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '[]';
            $obj->fTotal    = \round($total, 2);
            $obj->nMailed   = $sent['ok'] ? 1 : 0;
            $obj->cHtml     = $html;
            $id = (int)Shop::Container()->getDB()->insert(self::QUOTES_TABLE, $obj);
        } catch (\Throwable $e) {
            return $fail('Angebot konnte nicht gespeichert werden.');
        }
        if ($id <= 0) {
            return $fail('Angebot konnte nicht gespeichert werden.');
        }

        return [
            'ok'     => true,
            'error'  => $sent['ok'] ? '' : ($sent['error'] !== '' ? $sent['error'] : 'E-Mail konnte nicht gesendet werden.'),
            'id'     => $id,
            'nr'     => $nr,
            'mailed' => $sent['ok'],
        ];
    }

    private static function nextQuoteNr(): string
    {
        $prefix = 'ANG-' . \date('Ymd') . '-';
        $n      = 1;
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT cNr FROM ' . self::QUOTES_TABLE . ' WHERE cNr LIKE :p ORDER BY kID DESC LIMIT 1',
                ['p' => $prefix . '%']
            );
            if ($row !== null && \preg_match('/-(\d+)$/', (string)($row->cNr ?? ''), $m) === 1) {
                $n = (int)$m[1] + 1;
            }
        } catch (\Throwable $e) {
        }

        return $prefix . \str_pad((string)$n, 3, '0', \STR_PAD_LEFT);
    }

    /**
     * @param array<string,mixed> $q
     */
    public static function renderQuoteHtml(array $q): string
    {
        $h = static function (string $s): string {
            return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
        };
        $euro = static function (float $n): string {
            return \number_format($n, 2, ',', '.') . ' €';
        };
        $shop = $h(self::shopName());
        $nr   = $h((string)($q['nr'] ?? ''));
        $when = (string)($q['created'] ?? \date('Y-m-d H:i:s'));
        $dt   = \date('d.m.Y H:i', \strtotime($when) ?: \time());
        $name = $h(\trim((string)($q['vorname'] ?? '') . ' ' . (string)($q['nachname'] ?? '')));
        $phone = $h((string)($q['telefon'] ?? ''));
        $email = $h((string)($q['email'] ?? ''));
        $itemCount = 0;
        $rows = '';
        foreach (($q['items'] ?? []) as $it) {
            if (!\is_array($it)) {
                continue;
            }
            $itemCount++;
            $rows .= '<tr><td class="pos">' . $itemCount . '</td>'
                . '<td><strong>' . $h((string)($it['artnr'] ?? '')) . '</strong>'
                . '<small>' . $h((string)($it['serie'] ?? '')) . '</small></td>'
                . '<td class="product">' . $h((string)($it['name'] ?? '')) . '</td>'
                . '<td class="num">' . $h(\number_format((float)($it['qty'] ?? 0), 0, ',', '.')) . '</td>'
                . '<td class="num">' . $euro((float)($it['gross'] ?? 0)) . '</td>'
                . '<td class="num">' . $h(\number_format((float)($it['disc'] ?? 0), 1, ',', '.')) . ' %</td>'
                . '<td class="num">' . $euro((float)($it['line'] ?? 0)) . '</td>'
                . '</tr>';
        }

        return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Angebot ' . $nr . '</title>'
            . '<style>'
            . ':root{--ink:#14251f;--green:#176c4e;--mint:#e6f2eb;--sand:#f5f1e8;--line:#dce4df;--muted:#68766f}'
            . '*{box-sizing:border-box}'
            . '@page{size:A4;margin:0}'
            . 'body{margin:0;background:#dfe5e1;font-family:Georgia,"Times New Roman",serif;color:var(--ink);font-size:12px}'
            . '.toolbar{position:fixed;z-index:3;right:24px;top:20px}'
            . '.print{padding:10px 16px;border:0;border-radius:7px;background:var(--green);color:#fff;font:700 13px Arial,sans-serif;cursor:pointer;box-shadow:0 8px 24px rgba(20,37,31,.22)}'
            . '.sheet{position:relative;width:210mm;min-height:297mm;margin:18px auto;padding:15mm 15mm 14mm;background:#fff;box-shadow:0 18px 60px rgba(20,37,31,.18);overflow:hidden}'
            . '.sheet:before{content:"";position:absolute;left:0;top:0;bottom:0;width:5mm;background:var(--green)}'
            . '.top{display:flex;justify-content:space-between;align-items:flex-start;padding:0 0 11mm;border-bottom:1px solid var(--ink)}'
            . '.identity{display:flex;align-items:center;gap:12px}'
            . '.brand-logo{display:block;width:158px;max-height:72px;object-fit:contain;object-position:left center}'
            . '.brand{font-size:21px;font-weight:bold;line-height:1.05;letter-spacing:-.02em}'
            . '.brand span{display:block;margin-top:6px;color:var(--green);font:700 9px Arial,sans-serif;letter-spacing:.18em;text-transform:uppercase}'
            . '.meta{text-align:right;color:var(--muted);font:11px Arial,sans-serif;line-height:1.6}'
            . '.meta strong{display:block;color:var(--ink);font:bold 16px Georgia,serif;letter-spacing:.02em}'
            . '.hero{display:grid;grid-template-columns:1.15fr .85fr;gap:16mm;padding:11mm 0 9mm}'
            . '.eyebrow{margin:0 0 4px;color:var(--green);font:700 9px Arial,sans-serif;letter-spacing:.2em;text-transform:uppercase}'
            . 'h1{margin:0 0 8px;font-size:30px;font-weight:normal;line-height:1}'
            . '.intro{margin:0;color:var(--muted);font-size:12px;line-height:1.55}'
            . '.customer{padding:13px 15px;background:var(--sand);border-top:3px solid var(--green)}'
            . '.customer-label{display:block;margin-bottom:8px;color:var(--green);font:700 8px Arial,sans-serif;letter-spacing:.16em;text-transform:uppercase}'
            . '.customer strong{display:block;margin-bottom:7px;font-size:15px}'
            . '.customer div{color:#4e5d56;font:11px Arial,sans-serif;line-height:1.65}'
            . 'table{width:100%;border-collapse:collapse;font:11px Arial,sans-serif}'
            . 'thead{display:table-header-group}'
            . 'tr{page-break-inside:avoid}'
            . 'th{text-align:left;padding:8px 6px;background:var(--ink);color:#fff;font-size:8px;letter-spacing:.1em;text-transform:uppercase}'
            . 'td{padding:9px 6px;border-bottom:1px solid var(--line);vertical-align:top}'
            . 'tbody tr:nth-child(even){background:#f8faf8}'
            . 'td strong{display:block;font-size:11px}'
            . 'td small{display:block;margin-top:3px;color:var(--green);font-size:9px;font-weight:bold}'
            . '.pos{width:24px;color:var(--muted)}'
            . '.product{max-width:55mm}'
            . '.num{text-align:right;white-space:nowrap}'
            . '.summary{display:flex;justify-content:flex-end;margin-top:8mm}'
            . '.total-box{min-width:72mm;padding:11px 14px;background:var(--mint);border-left:4px solid var(--green)}'
            . '.total-box div{display:flex;justify-content:space-between;align-items:baseline;gap:20px}'
            . '.total-box label{color:var(--muted);font:700 9px Arial,sans-serif;letter-spacing:.12em;text-transform:uppercase}'
            . '.total-box strong{color:var(--green);font-size:22px}'
            . '.conditions{display:grid;grid-template-columns:1fr 1fr;gap:7mm 9mm;margin-top:13mm;padding-top:7mm;border-top:1px solid var(--line)}'
            . '.conditions h2{margin:0 0 5px;font:700 9px Arial,sans-serif;letter-spacing:.12em;text-transform:uppercase}'
            . '.conditions p{margin:0;color:var(--muted);font-size:10px;line-height:1.55}'
            . '.legal{grid-column:1/-1;padding:9px 11px;background:var(--sand);border-left:3px solid var(--green)}'
            . '.legal p{font-size:9px;line-height:1.5}'
            . '.foot{position:absolute;left:15mm;right:15mm;bottom:10mm;display:flex;justify-content:space-between;padding-top:6px;border-top:1px solid var(--line);color:var(--muted);font:9px Arial,sans-serif}'
            . '@media(max-width:800px){.sheet{width:100%;min-height:100vh;margin:0;padding:24px 22px 70px}.hero{grid-template-columns:1fr;gap:20px}.toolbar{right:12px;top:12px}.product{max-width:140px}}'
            . '@media print{body{background:#fff}.toolbar{display:none}.sheet{margin:0;box-shadow:none}.foot{position:fixed}}'
            . '</style></head><body><div class="toolbar"><button class="print" onclick="window.print()">Drucken / PDF</button></div><div class="sheet">'
            . '<div class="top"><div class="identity"><div class="brand"><img class="brand-logo" src="https://bremer-sitzbezuege.de/plugins/MesseList/templates/images/quote-logo.jpg" alt="' . $shop . '"><span>Messe · Angebot</span></div></div>'
            . '<div class="meta"><strong>' . $nr . '</strong>Erstellt am ' . $h($dt) . '<br>Preise inkl. MwSt.</div></div>'
            . '<div class="hero"><div><p class="eyebrow">Persönliches Angebot</p><h1>Ihre Auswahl.</h1>'
            . '<p class="intro">Vielen Dank für Ihr Interesse. Nachfolgend finden Sie Ihre individuell auf der Messe zusammengestellte Produktauswahl.</p></div>'
            . '<div class="customer"><span class="customer-label">Angebot für</span><strong>' . $name . '</strong>'
            . '<div>' . $phone . '<br>' . $email . '</div></div></div>'
            . '<table><thead><tr><th>Pos.</th><th>Artikel</th><th>Produkt</th><th class="num">Menge</th>'
            . '<th class="num">Brutto</th><th class="num">Rabatt</th><th class="num">Summe</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<div class="summary"><div class="total-box"><div><label>Angebotssumme</label><strong>' . $euro((float)($q['total'] ?? 0)) . '</strong></div></div></div>'
            . '<div class="conditions"><div><h2>Ihr Vorteil</h2><p>Die ausgewiesenen Messekonditionen und Rabatte sind bereits in der Angebotssumme berücksichtigt.</p></div>'
            . '<div><h2>Hinweis</h2><p>Dieses Dokument ist ein unverbindliches Messeangebot. Irrtümer und Zwischenverkauf vorbehalten.</p></div>'
            . '<div class="legal"><h2>Gesetzliche Anbieterkennung</h2><p><strong>Bremer-Sitzbezüge GmbH</strong>, vertreten durch den Geschäftsführer Nusret Sari<br>'
            . 'Justus-von-Liebig-Str. 4-6 · 28832 Achim · Deutschland<br>'
            . 'Telefon: +49 4202 9556290 · E-Mail: info@bremer-sitzbezuege.de<br>'
            . 'USt-IdNr.: DE 300776288 · Amtsgericht Walsrode · Handelsregisternummer HRB 208787</p></div></div>'
            . '<div class="foot"><span>' . $shop . ' · Messeangebot</span><span>' . $nr . '</span></div>'
            . '</div></body></html>';
    }

    /**
     * Erstellt ein eigenständiges A4-PDF ohne externe Bibliothek.
     *
     * @param array<string,mixed> $q
     */
    public static function renderQuotePdf(array $q): string
    {
        $encode = static function (string $value): string {
            $encoded = \function_exists('iconv')
                ? @\iconv('UTF-8', 'Windows-1252//TRANSLIT', $value)
                : $value;
            $encoded = \is_string($encoded) ? $encoded : $value;

            return \str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
        };
        $cut = static function (string $value, int $max): string {
            if (\function_exists('mb_strlen') && \mb_strlen($value) > $max) {
                return \mb_substr($value, 0, $max - 1) . '…';
            }

            return \strlen($value) > $max ? \substr($value, 0, $max - 3) . '...' : $value;
        };
        $txt = static function (
            float $x,
            float $y,
            float $size,
            string $value,
            bool $bold = false
        ) use ($encode): string {
            return "BT /" . ($bold ? 'F2' : 'F1') . " {$size} Tf {$x} {$y} Td (" . $encode($value) . ") Tj ET\n";
        };
        $right = static function (
            float $rightX,
            float $y,
            float $size,
            string $value,
            bool $bold = false
        ) use ($txt): string {
            $width = \strlen($value) * $size * 0.49;

            return $txt(\max(20.0, $rightX - $width), $y, $size, $value, $bold);
        };
        $euro = static function (float $value): string {
            return \number_format($value, 2, ',', '.') . ' EUR';
        };

        $logoData = '';
        $logoWidth = 0;
        $logoHeight = 0;
        $logoPath = \dirname(__DIR__) . '/templates/images/quote-logo.jpg';
        if (\is_file($logoPath)) {
            $logoInfo = @\getimagesize($logoPath);
            $logoRaw  = @\file_get_contents($logoPath);
            if (
                \is_array($logoInfo)
                && ($logoInfo['mime'] ?? '') === 'image/jpeg'
                && \is_string($logoRaw)
                && $logoRaw !== ''
            ) {
                $logoData   = $logoRaw;
                $logoWidth  = (int)$logoInfo[0];
                $logoHeight = (int)$logoInfo[1];
            }
        }
        $items  = \array_values(\array_filter($q['items'] ?? [], 'is_array'));
        $chunks = \array_chunk($items, 16);
        if ($chunks === []) {
            $chunks = [[]];
        }
        $nr      = (string)($q['nr'] ?? '');
        $created = (string)($q['created'] ?? \date('Y-m-d H:i:s'));
        $date    = \date('d.m.Y H:i', \strtotime($created) ?: \time());
        $name    = \trim((string)($q['vorname'] ?? '') . ' ' . (string)($q['nachname'] ?? ''));
        $pages   = [];
        $position = 0;

        foreach ($chunks as $pageNo => $chunk) {
            $stream = "q\n0.09 0.145 0.122 rg\n0 0 14 842 re f\n";
            $stream .= "0.09 0.145 0.122 rg\n42 756 511 1 re f\n";
            if ($logoData !== '') {
                $stream .= "q\n128 0 0 58 42 774 cm\n/Logo Do\nQ\n";
            } else {
                $stream .= $txt(42, 796, 19, self::shopName(), true);
            }
            $stream .= "0.09 0.424 0.306 rg\n42 766 90 2 re f\n";
            $stream .= $txt(42, 765, 8, 'MESSE · ANGEBOT', true);
            $stream .= $right(553, 796, 14, $nr, true);
            $stream .= $right(553, 780, 8, 'Erstellt am ' . $date);
            $stream .= $txt(42, 722, 25, 'Ihr persoenliches Angebot.', true);
            $stream .= $txt(42, 703, 9, 'Sorgfaeltig fuer Sie zusammengestellt · transparent, uebersichtlich und druckbereit.');

            $stream .= "0.902 0.949 0.922 rg\n350 676 203 58 re f\n";
            $stream .= "0.09 0.424 0.306 rg\n350 730 203 4 re f\n";
            $stream .= $txt(362, 716, 7, 'ANGEBOT FUER', true);
            $stream .= "0.09 0.145 0.122 rg\n";
            $stream .= $txt(362, 699, 11, $cut($name, 34), true);
            $stream .= $txt(362, 686, 8, $cut((string)($q['telefon'] ?? ''), 38));
            $stream .= $txt(362, 675, 8, $cut((string)($q['email'] ?? ''), 38));

            $stream .= "0.09 0.145 0.122 rg\n42 640 511 25 re f\n1 1 1 rg\n";
            $stream .= $txt(48, 649, 7, 'POS.', true);
            $stream .= $txt(76, 649, 7, 'ARTIKEL', true);
            $stream .= $txt(164, 649, 7, 'PRODUKT', true);
            $stream .= $right(390, 649, 7, 'MENGE', true);
            $stream .= $right(452, 649, 7, 'BRUTTO', true);
            $stream .= $right(500, 649, 7, 'RABATT', true);
            $stream .= $right(548, 649, 7, 'SUMME', true);
            $y = 619;
            foreach ($chunk as $item) {
                $position++;
                $stream .= ($position % 2 === 0)
                    ? "0.973 0.98 0.973 rg\n42 " . ($y - 7) . " 511 25 re f\n"
                    : '';
                $stream .= "0.09 0.145 0.122 rg\n";
                $stream .= $txt(49, $y, 8, (string)$position);
                $stream .= $txt(76, $y + 4, 8, $cut((string)($item['artnr'] ?? ''), 16), true);
                $stream .= $txt(76, $y - 6, 7, $cut((string)($item['serie'] ?? ''), 16));
                $stream .= $txt(164, $y, 8, $cut((string)($item['name'] ?? ''), 34));
                $stream .= $right(390, $y, 8, \number_format((float)($item['qty'] ?? 0), 0, ',', '.'));
                $stream .= $right(452, $y, 8, $euro((float)($item['gross'] ?? 0)));
                $stream .= $right(500, $y, 8, \number_format((float)($item['disc'] ?? 0), 1, ',', '.') . ' %');
                $stream .= $right(548, $y, 8, $euro((float)($item['line'] ?? 0)), true);
                $stream .= "0.863 0.894 0.875 RG\n42 " . ($y - 9) . " m 553 " . ($y - 9) . " l S\n";
                $y -= 27;
            }

            if ($pageNo === \count($chunks) - 1) {
                $total = $euro((float)($q['total'] ?? 0));
                $boxY  = \max(72, $y - 45);
                $stream .= "0.902 0.949 0.922 rg\n350 {$boxY} 203 43 re f\n";
                $stream .= "0.09 0.424 0.306 rg\n350 {$boxY} 4 43 re f\n";
                $stream .= "0.09 0.145 0.122 rg\n";
                $stream .= $txt(365, $boxY + 17, 8, 'ANGEBOTSSUMME', true);
                $stream .= $right(541, $boxY + 14, 16, $total, true);
                $stream .= "0.09 0.145 0.122 rg\n";
                $stream .= $txt(42, 117, 7, 'GESETZLICHE ANBIETERKENNUNG', true);
                $stream .= $txt(42, 105, 6.5, 'Bremer-Sitzbezuege GmbH · Geschaeftsfuehrer Nusret Sari');
                $stream .= $txt(42, 95, 6.5, 'Justus-von-Liebig-Str. 4-6 · 28832 Achim · Deutschland · Telefon +49 4202 9556290');
                $stream .= $txt(42, 85, 6.5, 'info@bremer-sitzbezuege.de · USt-IdNr. DE 300776288 · Amtsgericht Walsrode · HRB 208787');
            }
            $stream .= "0.4 0.46 0.43 rg\n";
            $stream .= $txt(42, 34, 7, self::shopName() . ' · Unverbindliches Messeangebot · Preise inkl. MwSt.');
            $stream .= $right(553, 34, 7, 'Seite ' . ($pageNo + 1) . ' / ' . \count($chunks));
            $stream .= "Q\n";
            $pages[] = $stream;
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $logoObject = 0;
        $objectNo = 5;
        if ($logoData !== '') {
            $logoObject = $objectNo++;
            $objects[$logoObject] = '<< /Type /XObject /Subtype /Image /Width ' . $logoWidth
                . ' /Height ' . $logoHeight
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
                . \strlen($logoData) . " >>\nstream\n" . $logoData . "\nendstream";
        }
        $kids = [];
        foreach ($pages as $stream) {
            $pageObject    = $objectNo++;
            $contentObject = $objectNo++;
            $kids[] = $pageObject . ' 0 R';
            $xObject = $logoObject > 0 ? ' /XObject << /Logo ' . $logoObject . ' 0 R >>' : '';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xObject
                . ' >> /Contents ' . $contentObject . ' 0 R >>';
            $objects[$contentObject] = '<< /Length ' . \strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . \implode(' ', $kids) . '] /Count ' . \count($kids) . ' >>';
        \ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = \strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = \strlen($pdf);
        $count = \count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= \sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /**
     * @return array{ok:bool,error:string}
     */
    public static function sendQuoteMail(string $email, string $name, string $nr, string $html): array
    {
        $subject = 'Ihr Messe-Angebot ' . $nr;
        $text    = 'Ihr Angebot ' . $nr . " liegt als HTML-Dokument in dieser E-Mail.\n";
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $fromMail = 'noreply@' . (\parse_url($shopUrl, \PHP_URL_HOST) ?: 'localhost');
        try {
            if (\defined('CONF_EMAILS')) {
                $settings = Shop::getSettings([\CONF_EMAILS]);
                $configured = \trim((string)($settings['emails']['email_master_absender'] ?? ''));
                if (\filter_var($configured, \FILTER_VALIDATE_EMAIL)) {
                    $fromMail = $configured;
                }
            }
        } catch (\Throwable $e) {
        }
        $jtlError = '';
        try {
            if (\class_exists(\JTL\Mail\Mail\Mail::class)) {
                $mail = new \JTL\Mail\Mail\Mail();
                if (\method_exists($mail, 'setToMail')) {
                    $mail->setToMail($email);
                }
                if (\method_exists($mail, 'setToName')) {
                    $mail->setToName($name);
                }
                if (\method_exists($mail, 'setSubject')) {
                    $mail->setSubject($subject);
                }
                if (\method_exists($mail, 'setFromMail')) {
                    $mail->setFromMail($fromMail);
                }
                if (\method_exists($mail, 'setFromName')) {
                    $mail->setFromName(self::shopName());
                }
                if (\method_exists($mail, 'setLanguage') && \class_exists(\JTL\Language\LanguageHelper::class)) {
                    $mail->setLanguage(\JTL\Language\LanguageHelper::getDefaultLanguage());
                }
                if (\method_exists($mail, 'setBodyHTML')) {
                    $mail->setBodyHTML($html);
                }
                if (\method_exists($mail, 'setBodyText')) {
                    $mail->setBodyText($text);
                }
                $mailer = null;
                if (\method_exists(Shop::Container(), 'getMailer')) {
                    $mailer = Shop::Container()->getMailer();
                } elseif (\class_exists(\JTL\Mail\Mailer::class)) {
                    $mailer = Shop::Container()->get(\JTL\Mail\Mailer::class);
                }
                if ($mailer !== null && \method_exists($mailer, 'send')) {
                    $mailer->send($mail);

                    return ['ok' => true, 'error' => ''];
                }
            }
        } catch (\Throwable $e) {
            $jtlError = $e->getMessage();
        }
        $from    = self::shopName() . ' <' . $fromMail . '>';
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . $from;
        $ok      = @\mail($email, '=?UTF-8?B?' . \base64_encode($subject) . '?=', $html, $headers);

        return [
            'ok'    => (bool)$ok,
            'error' => $ok ? '' : ($jtlError !== '' ? $jtlError : 'mail() fehlgeschlagen'),
        ];
    }
}
