<?php

declare(strict_types=1);

namespace Plugin\GoogleReview;

use JTL\Shop;

final class GoogleReviewDatabase
{
    private static bool $ensured = false;

    public static function ensureAll(): void
    {
        if (self::$ensured) {
            return;
        }

        try {
            $db = Shop::Container()->getDB();

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_google_review_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    cBusinessName VARCHAR(255) NOT NULL DEFAULT 'Ihr Unternehmen',
                    fRating DECIMAL(3,2) NOT NULL DEFAULT 4.50,
                    nTotalReviews INT NOT NULL DEFAULT 500,
                    cWriteUrl VARCHAR(500) NOT NULL DEFAULT '',
                    cAllUrl VARCHAR(500) NOT NULL DEFAULT '',
                    cHeading VARCHAR(255) NOT NULL DEFAULT 'Ihr Unternehmen',
                    cSubheading VARCHAR(255) NOT NULL DEFAULT 'Basierend auf 500 Bewertungen',
                    cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_cardproduct',
                    cInjectSelector VARCHAR(255) NOT NULL DEFAULT '',
                    dUpdated DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $colMode = $db->getSingleObject("SHOW COLUMNS FROM twomosi_google_review_settings LIKE 'cInjectMode'");
            if ($colMode === null) {
                $db->executeQuery("ALTER TABLE twomosi_google_review_settings ADD COLUMN cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_cardproduct' AFTER cSubheading");
            }
            $colSel = $db->getSingleObject("SHOW COLUMNS FROM twomosi_google_review_settings LIKE 'cInjectSelector'");
            if ($colSel === null) {
                $db->executeQuery("ALTER TABLE twomosi_google_review_settings ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT '' AFTER cInjectMode");
            }

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_google_review_items (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    cAuthor VARCHAR(255) NOT NULL DEFAULT '',
                    cTimeText VARCHAR(120) NOT NULL DEFAULT '',
                    nStars TINYINT NOT NULL DEFAULT 5,
                    cText TEXT NOT NULL,
                    cAvatarUrl VARCHAR(500) NOT NULL DEFAULT '',
                    nSort INT NOT NULL DEFAULT 100,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    dCreated DATETIME NOT NULL,
                    dUpdated DATETIME NOT NULL,
                    KEY idx_active_sort (nActive, nSort, kID)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $exists = $db->getSingleObject("SELECT kID FROM twomosi_google_review_settings LIMIT 1");
            if ($exists === null) {
                $row = new \stdClass();
                $row->cBusinessName = 'Ihr Unternehmen';
                $row->fRating       = 4.50;
                $row->nTotalReviews = 500;
                $row->cWriteUrl     = '';
                $row->cAllUrl       = '';
                $row->cHeading      = 'Ihr Unternehmen';
                $row->cSubheading   = 'Basierend auf 500 Bewertungen';
                $row->cInjectMode   = 'after_cardproduct';
                $row->cInjectSelector = '';
                $row->dUpdated      = date('Y-m-d H:i:s');
                $db->insert('twomosi_google_review_settings', $row);
            }

            self::$ensured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
