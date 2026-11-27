<?php

declare(strict_types=1);

namespace Plugin\MailTemplate;

use JTL\Firma;
use JTL\Shop;

/**
 * Vorschau mit Beispielbestellung (nur Admin-Dashboard).
 */
final class Demo
{
    public static function render(string $design, string $lang, string $kind = 'confirmation'): string
    {
        $mailer = Shop::Container()->getMailer();
        $smarty = $mailer->getRenderer()->getSmarty();
        $o = static fn(array $a): \stdClass => (object)$a;

        $img = self::sampleImage();
        $kunde = $o([

            'cVorname' => 'Max', 'cNachname' => 'Mustermann', 'cFirma' => '', 'cStrasse' => 'Musterstraße',
            'cHausnummer' => '12', 'cAdressZusatz' => '', 'cPLZ' => '28195', 'cOrt' => 'Bremen',
            'cBundesland' => '', 'angezeigtesLand' => 'Deutschland', 'cMail' => 'max@example.com', 'cUSTID' => '',
        ]);
        $pos = [
            $o([
                'nPosTyp' => 1, 'cName' => 'Autositzbezüge Fahrer & Beifahrer Set für VW T6 Multivan',
                'cArtNr' => 'VW-T6-FB-11', 'nAnzahl' => 1, 'cLieferstatus' => '',
                'WarenkorbPosEigenschaftArr' => [
                    $o(['cEigenschaftName' => 'Farbe', 'cEigenschaftWertName' => 'Anthrazit']),
                    $o(['cEigenschaftName' => 'Serie', 'cEigenschaftWertName' => 'C-Serie']),
                ],
                'Artikel' => $o(['cVorschaubildURL' => $img]),
                'cEinzelpreisLocalized' => [0 => '129,90 €', 1 => '109,16 €'],
                'cGesamtpreisLocalized' => [0 => '129,90 €', 1 => '109,16 €'],
            ]),
            $o([
                'nPosTyp' => 1, 'cName' => 'Armlehnenbezug Fiat Ducato', 'cArtNr' => 'FD-ARM-02', 'nAnzahl' => 2,
                'cLieferstatus' => '', 'WarenkorbPosEigenschaftArr' => [], 'Artikel' => $o(['cVorschaubildURL' => $img]),
                'cEinzelpreisLocalized' => [0 => '19,95 €', 1 => '16,76 €'],
                'cGesamtpreisLocalized' => [0 => '39,90 €', 1 => '33,53 €'],
            ]),
            $o([
                'nPosTyp' => 2, 'cName' => 'Versand (DHL)', 'cHinweis' => '', 'nAnzahl' => 1,
                'cGesamtpreisLocalized' => [0 => '4,90 €', 1 => '4,12 €'],
            ]),
        ];
        $bestellung = $o([
            'cBestellNr' => 'BS-2026-10458', 'dErstelldatum_de' => '27.11.2026', 'Positionen' => $pos,
            'Steuerpositionen' => [$o(['cName' => 'inkl. 19% USt.', 'cPreisLocalized' => '28,65 €'])],
            'GuthabenNutzen' => 0, 'GutscheinLocalized' => '',
            'WarensummeLocalized' => [0 => '174,70 €', 1 => '146,81 €'],
            'cZahlungsartName' => 'Überweisung',
            'Zahlungsart' => $o(['cModulId' => 'za_ueberweisung_jtl']),
            'Lieferadresse' => $o([
                'kLieferadresse' => 7, 'cFirma' => '', 'cVorname' => 'Erika', 'cNachname' => 'Mustermann',
                'cStrasse' => 'Beispielweg', 'cHausnummer' => '3', 'cAdressZusatz' => '', 'cPLZ' => '20095',
                'cOrt' => 'Hamburg', 'cBundesland' => '', 'angezeigtesLand' => 'Deutschland',
            ]),
            'oLieferschein_arr' => \in_array($kind, ['shipped', 'partshipped'], true) ? [self::delivery($pos[0])] : [],
            'cEstimatedDelivery' => '3-5 Werktage',
            'cEstimatedDeliveryEx' => '02.12.2026 - 04.12.2026',
        ]);

        $smarty->assign('Kunde', $kunde)
            ->assign('Bestellung', $bestellung)
            ->assign('Firma', new Firma(true, Shop::Container()->getDB()))
            ->assign('Einstellungen', $mailer->getConfig())
            ->assign('NettoPreise', false)
            ->assign('ShopURL', Shop::getURL())
            ->assign('ShopLogoURL', Shop::getLogo(true))
            ->assign('payments', null)
            ->assign('totalLocalized', null)
            ->assign('Verfuegbarkeit_arr', null)
            ->assign('Zahlungsart', null);

        $html = Designer::render($design, $smarty, $lang, Config::all(), $kind);

        return \str_replace(['[AKZ]', '[LEGAL_DATA]'], ['', ''], $html);
    }

    /** Echtes Artikelbild aus dem Shop (sonst leer -> Bild wird ausgeblendet). */
    private static function sampleImage(): string
    {
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT p.kArtikel, s.cSeo FROM tartikelpict p
                  JOIN tseo s ON s.cKey = \'kArtikel\' AND s.kKey = p.kArtikel AND s.kSprache = 1
                 ORDER BY p.kArtikelPict DESC LIMIT 1'
            );
            if ($row !== null && (string)$row->cSeo !== '') {
                return Shop::getURL() . '/media/image/product/' . (int)$row->kArtikel . '/sm/' . $row->cSeo . '.jpg';
            }
        } catch (\Throwable $e) {
            // kein Beispielbild
        }

        return '';
    }

    private static function delivery(object $first): object
    {
        $item             = clone $first;
        $item->nAusgeliefert = 1;

        return new class ($item, self::shipment()) {
            /** @var list<object> */
            public array $oPosition_arr;
            /** @var list<object> */
            public array $oVersand_arr;

            public function __construct(object $item, object $shipment)
            {
                $this->oPosition_arr = [$item];
                $this->oVersand_arr  = [$shipment];
            }

            public function getEmailVerschickt(): bool
            {
                return false;
            }
        };
    }

    private static function shipment(): object
    {
        return new class {
            public function getIdentCode(): string
            {
                return '00340434161094042557';
            }

            public function getLogistikVarUrl(): string
            {
                return 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html';
            }

            public function getHinweis(): string
            {
                return '';
            }
        };
    }
}
