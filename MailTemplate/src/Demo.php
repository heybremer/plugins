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
    public static function render(string $design, string $lang): string
    {
        $mailer = Shop::Container()->getMailer();
        $smarty = $mailer->getRenderer()->getSmarty();
        $o = static fn(array $a): \stdClass => (object)$a;

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
                'cEinzelpreisLocalized' => [0 => '129,90 €', 1 => '109,16 €'],
                'cGesamtpreisLocalized' => [0 => '129,90 €', 1 => '109,16 €'],
            ]),
            $o([
                'nPosTyp' => 1, 'cName' => 'Armlehnenbezug Fiat Ducato', 'cArtNr' => 'FD-ARM-02', 'nAnzahl' => 2,
                'cLieferstatus' => '', 'WarenkorbPosEigenschaftArr' => [],
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

        $html = Designer::render($design, $smarty, $lang, Config::all());

        return \str_replace(['[AKZ]', '[LEGAL_DATA]'], ['', ''], $html);
    }
}
