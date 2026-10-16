<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch;

use JTL\Shop;

/**
 * Plugin tablolarını yalnızca API / selection / admin yollarında oluşturur;
 * her shop isteğinde bootstrap üzerinde DDL çalıştırmaz.
 */
final class PluginDatabase
{
    private static bool $coreEnsured = false;

    /**
     * Leichtgewichtige IP-basierte Rate-Limitierung über APCu.
     *
     * Fail-open: Ohne APCu wird NICHT gedrosselt (Verhalten wie zuvor),
     * damit reine DB-Setups nicht stillschweigend Schreibvorgänge verlieren.
     *
     * @param string $bucket         Logischer Bereich, z. B. 'sel' oder 'log'.
     * @param string $ip             Client-IP.
     * @param int    $max            Maximale Treffer pro Zeitfenster.
     * @param int    $windowSeconds  Länge des Zeitfensters in Sekunden.
     *
     * @return bool true => gedrosselt (Aufrufer soll den Schreibvorgang überspringen).
     */
    public static function throttle(string $bucket, string $ip, int $max, int $windowSeconds): bool
    {
        if ($ip === '' || $max <= 0 || !\function_exists('apcu_fetch')) {
            return false;
        }
        $key = 'fk_thr_' . $bucket . '_' . \md5($ip);
        try {
            $count = (int)@\apcu_fetch($key);
            if ($count >= $max) {
                return true;
            }
            @\apcu_store($key, $count + 1, \max(1, $windowSeconds));
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    public static function ensureCoreTables(): void
    {
        if (self::$coreEnsured) {
            return;
        }

        try {
            $db = Shop::Container()->getDB();

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_step4_cache (
                        cKey CHAR(32) NOT NULL,
                        dUpdated DATETIME NOT NULL,
                        cPayload MEDIUMTEXT NOT NULL,
                        PRIMARY KEY (cKey),
                        KEY idx_dUpdated (dUpdated)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_search_log (
                        kLogID INT AUTO_INCREMENT PRIMARY KEY,
                        cIP VARCHAR(45) NOT NULL,
                        dCreated DATETIME NOT NULL,
                        cMode VARCHAR(32) NOT NULL DEFAULT '',
                        cField VARCHAR(64) NOT NULL DEFAULT '',
                        cParams MEDIUMTEXT NOT NULL,
                        nDurationMs INT NOT NULL DEFAULT 0,
                        cUserAgent VARCHAR(255) NOT NULL DEFAULT ''
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_selection (
                        kID INT AUTO_INCREMENT PRIMARY KEY,
                        cIP VARCHAR(45) NOT NULL,
                        dCreated DATETIME NOT NULL,
                        cTyp VARCHAR(255) NOT NULL DEFAULT '',
                        cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                        cBasis VARCHAR(255) NOT NULL DEFAULT '',
                        cModell VARCHAR(500) NOT NULL DEFAULT '',
                        cBaujahr VARCHAR(16) NOT NULL DEFAULT '',
                        cSitz VARCHAR(255) NOT NULL DEFAULT '',
                        cSerie VARCHAR(255) NOT NULL DEFAULT '',
                        cUserAgent VARCHAR(255) NOT NULL DEFAULT ''
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_hersteller (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cTyp VARCHAR(255) NOT NULL DEFAULT '',
                    cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                    UNIQUE KEY uq_opt_her (kSprache, cTyp, cHersteller),
                    KEY idx_opt_her (kSprache, cTyp),
                    KEY idx_opt_her2 (kSprache, cHersteller)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_basis (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cTyp VARCHAR(255) NOT NULL DEFAULT '',
                    cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                    cBasis VARCHAR(255) NOT NULL DEFAULT '',
                    UNIQUE KEY uq_opt_basis (kSprache, cTyp, cHersteller, cBasis),
                    KEY idx_opt_basis (kSprache, cTyp, cHersteller),
                    KEY idx_opt_basis2 (kSprache, cBasis)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_modell (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cTyp VARCHAR(255) NOT NULL DEFAULT '',
                    cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                    cBasis VARCHAR(255) NOT NULL DEFAULT '',
                    cModell VARCHAR(255) NOT NULL DEFAULT '',
                    UNIQUE KEY uq_opt_modell (kSprache, cTyp, cHersteller, cBasis, cModell),
                    KEY idx_opt_modell (kSprache, cTyp, cHersteller, cBasis),
                    KEY idx_opt_modell2 (kSprache, cModell)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_meta (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    dRefreshed DATETIME NOT NULL,
                    kSprache INT NOT NULL DEFAULT 0,
                    cNote VARCHAR(255) NOT NULL DEFAULT ''
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            // PKW snapshot tables
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_pkw_hersteller (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                    UNIQUE KEY uq_pkw_her (kSprache, cHersteller),
                    KEY idx_pkw_her (kSprache)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_pkw_modell (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                    cModell VARCHAR(255) NOT NULL DEFAULT '',
                    UNIQUE KEY uq_pkw_mod (kSprache, cHersteller, cModell),
                    KEY idx_pkw_mod (kSprache, cHersteller)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_pkw_baujahr (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                    cModell VARCHAR(255) NOT NULL DEFAULT '',
                    cBaujahr VARCHAR(16) NOT NULL DEFAULT '',
                    UNIQUE KEY uq_pkw_bj (kSprache, cHersteller, cModell, cBaujahr),
                    KEY idx_pkw_bj (kSprache, cHersteller, cModell)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_pkw_meta (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    dRefreshed DATETIME NOT NULL,
                    UNIQUE KEY uq_pkw_meta (kSprache)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_pkw_serie (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                    cModell VARCHAR(255) NOT NULL DEFAULT '',
                    cBaujahr VARCHAR(16) NOT NULL DEFAULT '',
                    cSerie VARCHAR(255) NOT NULL DEFAULT '',
                    cSerieLabel VARCHAR(255) NOT NULL DEFAULT '',
                    UNIQUE KEY uq_pkw_serie (kSprache, cHersteller, cModell, cBaujahr, cSerie),
                    KEY idx_pkw_serie (kSprache, cHersteller, cModell, cBaujahr)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_serie_meta (
                    cSerieKey VARCHAR(64) NOT NULL,
                    cLabel VARCHAR(255) NOT NULL DEFAULT '',
                    cColor VARCHAR(32) NOT NULL DEFAULT '',
                    cImg VARCHAR(2000) NOT NULL DEFAULT '',
                    nHide TINYINT(1) NOT NULL DEFAULT 0,
                    dUpdated DATETIME NOT NULL,
                    PRIMARY KEY (cSerieKey)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            self::$coreEnsured = true;
        } catch (\Throwable $e) {
            // DB yoksa veya yetki yoksa sessiz; endpoint kendi hatasını döner.
        }
    }
}
