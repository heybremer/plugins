<?php

declare(strict_types=1);

namespace Plugin\Bloggie;

use JTL\Shop;

/**
 * Single place for twomosi_bloggie_* DDL and migrations (lazy ensureAll).
 */
final class BloggieDatabase
{
    private static bool $ensured = false;

    private const SCHEMA_APCU_KEY = 'womosi_bloggie_schema_v2';

    public static function ensureAll(): void
    {
        if (self::$ensured) {
            return;
        }

        if (\function_exists('apcu_fetch')) {
            try {
                if (@\apcu_fetch(self::SCHEMA_APCU_KEY) === true) {
                    self::$ensured = true;

                    return;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        try {
            $db = Shop::Container()->getDB();

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_bloggie_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    nAutoDaily TINYINT(1) NOT NULL DEFAULT 1,
                    cApiKey TEXT NULL,
                    cModel VARCHAR(80) NOT NULL DEFAULT 'gpt-4o-mini',
                    cLanguage VARCHAR(20) NOT NULL DEFAULT 'de',
                    cSectionTitle VARCHAR(150) NOT NULL DEFAULT 'Blog',
                    cPromptPrefix TEXT NULL,
                    cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator',
                    cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after',
                    cCronToken VARCHAR(80) NOT NULL DEFAULT '',
                    cBrandPool TEXT NULL,
                    cTopicPool TEXT NULL,
                    cLastSystemPrompt LONGTEXT NULL,
                    cLastUserPrompt LONGTEXT NULL,
                    dLastPrompt DATETIME NULL,
                    dLastRunDate DATE NULL,
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $cols = $db->getObjects(
                "SELECT COLUMN_NAME AS n
                   FROM information_schema.columns
                  WHERE table_schema = DATABASE()
                    AND table_name = 'twomosi_bloggie_settings'"
            );
            $have = [];
            if (\is_array($cols)) {
                foreach ($cols as $c) {
                    $have[(string)($c->n ?? '')] = true;
                }
            }
            if (!isset($have['cInjectSelector'])) {
                $db->executeQuery("ALTER TABLE twomosi_bloggie_settings ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator'");
            }
            if (!isset($have['cInjectMode'])) {
                $db->executeQuery("ALTER TABLE twomosi_bloggie_settings ADD COLUMN cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after'");
            }
            if (!isset($have['cCronToken'])) {
                $db->executeQuery("ALTER TABLE twomosi_bloggie_settings ADD COLUMN cCronToken VARCHAR(80) NOT NULL DEFAULT ''");
            }
            if (!isset($have['cBrandPool'])) {
                $db->executeQuery('ALTER TABLE twomosi_bloggie_settings ADD COLUMN cBrandPool TEXT NULL');
            }
            if (!isset($have['cTopicPool'])) {
                $db->executeQuery('ALTER TABLE twomosi_bloggie_settings ADD COLUMN cTopicPool TEXT NULL');
            }
            if (!isset($have['cLastSystemPrompt'])) {
                $db->executeQuery('ALTER TABLE twomosi_bloggie_settings ADD COLUMN cLastSystemPrompt LONGTEXT NULL');
            }
            if (!isset($have['cLastUserPrompt'])) {
                $db->executeQuery('ALTER TABLE twomosi_bloggie_settings ADD COLUMN cLastUserPrompt LONGTEXT NULL');
            }
            if (!isset($have['dLastPrompt'])) {
                $db->executeQuery('ALTER TABLE twomosi_bloggie_settings ADD COLUMN dLastPrompt DATETIME NULL');
            }
            if (!isset($have['dLastRunDate'])) {
                $db->executeQuery('ALTER TABLE twomosi_bloggie_settings ADD COLUMN dLastRunDate DATE NULL');
            }

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_bloggie_posts (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kArtikel INT NOT NULL DEFAULT 0,
                    kNews INT NOT NULL DEFAULT 0,
                    cProductName VARCHAR(255) NOT NULL DEFAULT '',
                    cProductUrl VARCHAR(500) NOT NULL DEFAULT '',
                    cSeoTitle VARCHAR(255) NOT NULL DEFAULT '',
                    cMetaDescription VARCHAR(500) NOT NULL DEFAULT '',
                    cBlogTitle VARCHAR(255) NOT NULL DEFAULT '',
                    cSlug VARCHAR(255) NOT NULL DEFAULT '',
                    cExcerpt TEXT NULL,
                    cBodyHtml LONGTEXT NULL,
                    nPublished TINYINT(1) NOT NULL DEFAULT 1,
                    dPublish DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    dCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_pub (nPublished, dPublish)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $colKNews = $db->getSingleObject("SHOW COLUMNS FROM twomosi_bloggie_posts LIKE 'kNews'");
            if ($colKNews === null) {
                $db->executeQuery('ALTER TABLE twomosi_bloggie_posts ADD COLUMN kNews INT NOT NULL DEFAULT 0');
            }

            $exists = $db->getSingleObject('SELECT kID FROM twomosi_bloggie_settings LIMIT 1');
            if ($exists === null) {
                $row = new \stdClass();
                $row->nActive = 1;
                $row->nAutoDaily = 1;
                $row->cApiKey = '';
                $row->cModel = 'gpt-4o-mini';
                $row->cLanguage = 'de';
                $row->cSectionTitle = 'Blog';
                $row->cPromptPrefix = 'Write a high quality SEO blog post for this product page.';
                $row->cInjectSelector = 'div.index-filter-konfigurator';
                $row->cInjectMode = 'after';
                $row->cCronToken = \bin2hex(\random_bytes(16));
                $row->dLastRunDate = null;
                $row->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_bloggie_settings', $row);
            }

            self::$ensured = true;

            if (\function_exists('apcu_store')) {
                try {
                    @\apcu_store(self::SCHEMA_APCU_KEY, true, 604800);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
