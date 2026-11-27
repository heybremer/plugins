<?php

declare(strict_types=1);

namespace Plugin\MailTemplate;

use JTL\Shop;

/**
 * Einstellungen (Key/Value) in twomosi_mt_config.
 */
final class Config
{
    /** @var array<string, array{label:string,desc:string,accent:string}> */
    public const DESIGNS = [
        'classic' => [
            'label'  => 'Classic',
            'desc'   => 'Klar und serioes: weisse Karte, Tabellenlayout, dunkelblauer Akzent.',
            'accent' => '#1f3a5f',
        ],
        'modern'  => [
            'label'  => 'Modern',
            'desc'   => 'Farbiger Kopfbereich, Bestell-Fortschritt, Karten und Button.',
            'accent' => '#4f46e5',
        ],
        'minimal' => [
            'label'  => 'Minimal',
            'desc'   => 'Reduziert: viel Weissraum, feine Linien, Typografie im Fokus.',
            'accent' => '#111111',
        ],
    ];

    /** @var array<string, string> */
    private const DEFAULTS = [
        'enabled'       => '1',
        'apply_paid'    => '1',
        'apply_shipped' => '1',
        'apply_partshipped' => '1',
        'apply_cancelled' => '1',
        'promo_title'   => '',
        'promo_text'    => '',
        'promo_btn'     => '',
        'promo_url'     => '',
        'show_images'   => '1',
        'design'        => 'modern',
        'accent'        => '',
        'tagline'       => '',
        'note'          => '',
        'support_mail'  => '',
        'support_phone' => '',
        'logo_url'      => '',
    ];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function ensure(): void
    {
        Shop::Container()->getDB()->executeQuery(
            'CREATE TABLE IF NOT EXISTS twomosi_mt_config (
                cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                cValue TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = self::DEFAULTS;
        try {
            $db = Shop::Container()->getDB();
            try {
                $rows = $db->getObjects('SELECT cKey, cValue FROM twomosi_mt_config');
            } catch (\Throwable $e) {
                self::ensure();
                $rows = [];
            }
            foreach ($rows as $row) {
                if (\array_key_exists((string)$row->cKey, $out)) {
                    $out[(string)$row->cKey] = (string)$row->cValue;
                }
            }
        } catch (\Throwable $e) {
            // Defaults
        }
        if (!isset(self::DESIGNS[$out['design']])) {
            $out['design'] = self::DEFAULTS['design'];
        }
        self::$cache = $out;

        return $out;
    }

    /**
     * @param array<string, mixed> $in Rohdaten (z. B. $_POST)
     */
    public static function save(array $in): void
    {
        $design = (string)($in['design'] ?? '');
        $accent = \trim((string)($in['accent'] ?? ''));
        $logo   = \trim((string)($in['logo_url'] ?? ''));
        $mail   = \trim((string)($in['support_mail'] ?? ''));
        $clean  = [
            'enabled'       => !empty($in['enabled']) ? '1' : '0',
            'apply_paid'    => !empty($in['apply_paid']) ? '1' : '0',
            'apply_shipped' => !empty($in['apply_shipped']) ? '1' : '0',
            'apply_partshipped' => !empty($in['apply_partshipped']) ? '1' : '0',
            'apply_cancelled' => !empty($in['apply_cancelled']) ? '1' : '0',
            'promo_title'   => \mb_substr(\trim((string)($in['promo_title'] ?? '')), 0, 100),
            'promo_text'    => \mb_substr(\trim((string)($in['promo_text'] ?? '')), 0, 300),
            'promo_btn'     => \mb_substr(\trim((string)($in['promo_btn'] ?? '')), 0, 40),
            'promo_url'     => \preg_match('~^https://~i', \trim((string)($in['promo_url'] ?? ''))) ? \mb_substr(\trim((string)$in['promo_url']), 0, 500) : '',
            'show_images'   => !empty($in['show_images']) ? '1' : '0',
            'design'        => isset(self::DESIGNS[$design]) ? $design : self::DEFAULTS['design'],
            'accent'        => \preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? \strtolower($accent) : '',
            'tagline'       => \mb_substr(\trim((string)($in['tagline'] ?? '')), 0, 120),
            'note'          => \mb_substr(\trim((string)($in['note'] ?? '')), 0, 600),
            'support_mail'  => \filter_var($mail, \FILTER_VALIDATE_EMAIL) ? $mail : '',
            'support_phone' => \mb_substr(\trim((string)($in['support_phone'] ?? '')), 0, 40),
            'logo_url'      => \preg_match('~^https://~i', $logo) ? \mb_substr($logo, 0, 500) : '',
        ];
        self::ensure();
        $db = Shop::Container()->getDB();
        foreach ($clean as $k => $v) {
            $db->executeQueryPrepared(
                'INSERT INTO twomosi_mt_config (cKey, cValue) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)',
                ['k' => $k, 'v' => $v]
            );
        }
        self::$cache = null;
    }
}
