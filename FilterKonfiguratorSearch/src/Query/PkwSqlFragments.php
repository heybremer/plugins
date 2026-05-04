<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Query;

/**
 * PKW-spezifische SQL-Fragmente.
 *
 * Stellt wiederverwendbare SQL-Schnipsel bereit, die in mehreren
 * Repository-Methoden benötigt werden.  Kein DB-Zugriff, keine
 * Seiteneffekte – reine String-Generierung.
 */
final class PkwSqlFragments
{
    /**
     * NOT-EXISTS-Fragment: schließt Artikel aus, die das Merkmal
     * „Wohnmobil-Aufbauart" tragen (PKW-Segment-Filter).
     */
    public static function articleNotAufbauart(): string
    {
        return " AND NOT EXISTS (
            SELECT 1 FROM tartikelmerkmal am_pkwx
            INNER JOIN tmerkmalwert mw_pkwx ON mw_pkwx.kMerkmalWert = am_pkwx.kMerkmalWert
            INNER JOIN tmerkmal m_pkwx ON m_pkwx.kMerkmal = mw_pkwx.kMerkmal
            INNER JOIN tmerkmalsprache ms_pkwx ON ms_pkwx.kMerkmal = m_pkwx.kMerkmal
               AND ms_pkwx.kSprache = :langID
               AND ms_pkwx.cName IN ('Wohnmobil-Aufbauart','Wohnmobil Aufbauart')
            WHERE am_pkwx.kArtikel = a.kArtikel
        )";
    }

    /**
     * WHERE-Bedingung için Merkmalnamen „Baujahr" in allen
     * vorkommenden Schreibweisen (Bindestrich, Leerzeichen, EN-Dash).
     *
     * @param string $aliasMs Tabellenalias von tmerkmalsprache
     */
    public static function merkmalNameBaujahr(string $aliasMs = 'ms_bj'): string
    {
        return '(' . $aliasMs . ".cName = 'Baujahr'
            OR " . $aliasMs . ".cName = 'Bau-Jahr'
            OR " . $aliasMs . ".cName = 'Bau Jahr'
            OR REPLACE(REPLACE(TRIM(" . $aliasMs . ".cName), ' ', '-'), '–', '-') = 'Bau-Jahr')";
    }

    /**
     * PKW Konfigüratörü için Produkttyp ve Kategori filtresi.
     * Sadece 'Fahrer & Beifahrer Set Schonbezüge' olan ve 19 nolu kategori altındaki ürünleri getirir.
     */
    public static function pkwProdukttypFilter(string $langParamName = 'langID'): string
    {
        return self::categoryTreeFilter(19) . 
               " INNER JOIN tartikelmerkmal am_ptyp ON am_ptyp.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_ptyp ON mw_ptyp.kMerkmalWert = am_ptyp.kMerkmalWert
                 INNER JOIN tmerkmal m_ptyp ON m_ptyp.kMerkmal = mw_ptyp.kMerkmal
                 INNER JOIN tmerkmalsprache ms_ptyp ON ms_ptyp.kMerkmal = m_ptyp.kMerkmal
                    AND ms_ptyp.kSprache = :{$langParamName} AND ms_ptyp.cName = 'Produkttyp'
                 INNER JOIN tmerkmalwertsprache tmws_ptyp ON tmws_ptyp.kMerkmalWert = mw_ptyp.kMerkmalWert
                    AND tmws_ptyp.kSprache = :{$langParamName} AND tmws_ptyp.cWert = 'Fahrer & Beifahrer Set Schonbezüge'";
    }

    /**
     * Wohnmobil Konfigüratörü için Produkttyp ve Kategori filtresi.
     * Sadece 'Wohnmobil Schonbezüge Fahrer und Beifahrer Set' olan ve 24 nolu kategori altındaki ürünleri getirir.
     */
    public static function wmProdukttypFilter(string $langParamName = 'langID'): string
    {
        return self::categoryTreeFilter(24) .
               " INNER JOIN tartikelmerkmal am_ptyp ON am_ptyp.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_ptyp ON mw_ptyp.kMerkmalWert = am_ptyp.kMerkmalWert
                 INNER JOIN tmerkmal m_ptyp ON m_ptyp.kMerkmal = mw_ptyp.kMerkmal
                 INNER JOIN tmerkmalsprache ms_ptyp ON ms_ptyp.kMerkmal = m_ptyp.kMerkmal
                    AND ms_ptyp.kSprache = :{$langParamName} AND ms_ptyp.cName = 'Produkttyp'
                 INNER JOIN tmerkmalwertsprache tmws_ptyp ON tmws_ptyp.kMerkmalWert = mw_ptyp.kMerkmalWert
                    AND tmws_ptyp.kSprache = :{$langParamName} AND tmws_ptyp.cWert = 'Wohnmobil Schonbezüge Fahrer und Beifahrer Set'";
    }

    /**
     * Ürünleri belirli bir kategori ağacıyla (lft/rght üzerinden) sınırlar.
     * Ayrıca Kategori 22 (Komplette Sets) altındakileri otomatik dışlar.
     */
    public static function categoryTreeFilter(int $rootId): string
    {
        return " INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
                 INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
                 INNER JOIN tkategorie p_tree ON p_tree.kKategorie = {$rootId}
                    AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
                 -- Exclude Komplette Sets (Cat 22)
                 AND NOT EXISTS (
                     SELECT 1 FROM tkategorie p_excl 
                     WHERE p_excl.kKategorie = 22 
                       AND c_tree.lft BETWEEN p_excl.lft AND p_excl.rght
                 ) ";
    }
}
