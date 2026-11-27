<!DOCTYPE html>
<html lang="{$mt.lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$mt.title}</title>
{literal}<style type="text/css">
body { margin:0; padding:0; }
img { border:0; outline:none; text-decoration:none; }
@media only screen and (max-width: 620px) {
  .mt-wrap { width:100% !important; }
  .mt-pad { padding-left:16px !important; padding-right:16px !important; }
  .mt-col { display:block !important; width:100% !important; padding:0 0 14px 0 !important; }
  .mt-hide { display:none !important; }
}
</style>{/literal}
</head>
<body style="margin:0;padding:0;background:#f1f1f1;">
{assign var='mt_mod' value=$Bestellung->Zahlungsart->cModulId|default:''}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f1f1;">
<tr><td align="center" style="padding:24px 10px;">
<table role="presentation" class="mt-wrap" width="620" cellpadding="0" cellspacing="0" border="0" style="width:620px;max-width:620px;background:#ffffff;border:1px solid #dcdcdc;font-family:Georgia,'Times New Roman',serif;color:#2b2b2b;">

  <tr><td style="height:5px;line-height:5px;font-size:0;background:{$mtc.accent};">&nbsp;</td></tr>

  <tr><td class="mt-pad" align="center" style="padding:28px 40px 18px;border-bottom:1px solid #e6e6e6;">
    {if $mtc.logo}<img src="{$mtc.logo}" alt="{$Einstellungen.global.global_shopname}" style="max-height:54px;max-width:260px;">
    {elseif $ShopLogoURL}<img src="{$ShopLogoURL}" alt="{$Einstellungen.global.global_shopname}" style="max-height:54px;max-width:260px;">
    {else}<span style="font-size:24px;color:{$mtc.accent};">{$Einstellungen.global.global_shopname}</span>{/if}
    {if $mtc.tagline}<div style="margin-top:6px;font-family:Helvetica,Arial,sans-serif;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#8a8a8a;">{$mtc.tagline}</div>{/if}
  </td></tr>

  <tr><td class="mt-pad" style="padding:32px 40px 10px;">
    <h1 style="margin:0 0 14px;font-size:26px;line-height:32px;font-weight:normal;color:{$mtc.accent};">{$mt.title}</h1>
    <p style="margin:0 0 8px;font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:24px;">{$mt.hello} {$Kunde->cVorname} {$Kunde->cNachname},</p>
    <p style="margin:0;font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:24px;color:#555555;">{$mt.intro}</p>
    {if $mtc.note}<p style="margin:14px 0 0;font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:22px;color:#555555;font-style:italic;">{$mtc.note}</p>{/if}
  </td></tr>

  <tr><td class="mt-pad" style="padding:18px 40px 6px;font-family:Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f7f7;border:1px solid #e6e6e6;">
      <tr>
        <td class="mt-col" width="50%" style="padding:12px 16px;font-size:13px;color:#777777;">{$mt.order_no}<br><strong style="font-size:16px;color:#2b2b2b;">{$Bestellung->cBestellNr}</strong></td>
        <td class="mt-col" width="50%" style="padding:12px 16px;font-size:13px;color:#777777;">{$mt.order_date}<br><strong style="font-size:16px;color:#2b2b2b;">{$Bestellung->dErstelldatum_de|default:''}</strong></td>
      </tr>
    </table>
  </td></tr>

  <tr><td class="mt-pad" style="padding:22px 40px 0;font-family:Helvetica,Arial,sans-serif;">
    <h2 style="margin:0 0 8px;font-family:Georgia,'Times New Roman',serif;font-size:19px;font-weight:normal;color:{$mtc.accent};">{$mt.items}</h2>
    {if !empty($Verfuegbarkeit_arr.cArtikelName_arr)}
      <p style="margin:0 0 10px;padding:10px 12px;background:#fff8e5;border:1px solid #f0dca0;font-size:13px;line-height:20px;color:#6b5400;">
        <strong>{$mt.avail}:</strong> {$Verfuegbarkeit_arr.cHinweis}
        {foreach $Verfuegbarkeit_arr.cArtikelName_arr as $cArtikelname}<br>&bull; {$cArtikelname}{/foreach}
      </p>
    {/if}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
      <tr>
        <td style="padding:8px 0;border-bottom:2px solid {$mtc.accent};font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#777777;">{$mt.article}</td>
        <td align="center" style="padding:8px 6px;border-bottom:2px solid {$mtc.accent};font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#777777;">{$mt.qty}</td>
        <td align="right" style="padding:8px 0;border-bottom:2px solid {$mtc.accent};font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#777777;">{$mt.sum}</td>
      </tr>
      {foreach $Bestellung->Positionen as $Position}
        <tr>
          <td valign="top" style="padding:12px 8px 12px 0;border-bottom:1px solid #e9e9e9;line-height:20px;">
            {if $Position->nPosTyp == 1}
              <strong>{$Position->cName}</strong>{if $Position->cArtNr} <span style="color:#999999;font-size:12px;">({$Position->cArtNr})</span>{/if}
              {foreach $Position->WarenkorbPosEigenschaftArr as $WKPosEigenschaft}
                <div style="font-size:12px;color:#777777;">{$WKPosEigenschaft->cEigenschaftName}: {$WKPosEigenschaft->cEigenschaftWertName}</div>
              {/foreach}
              {if $Einstellungen.kaufabwicklung.bestellvorgang_lieferstatus_anzeigen === 'Y' && $Position->cLieferstatus}<div style="font-size:12px;color:#777777;">{$mt.delivery}: {$Position->cLieferstatus}</div>{/if}
              {if $Einstellungen.kaufabwicklung.bestellvorgang_einzelpreise_anzeigen === 'Y' && isset($Position->cEinzelpreisLocalized)}<div style="font-size:12px;color:#999999;">{$mt.price}: {$Position->cEinzelpreisLocalized[$NettoPreise]}</div>{/if}
            {else}
              <strong>{$Position->cName}</strong>{if !empty($Position->cHinweis)} <span style="color:#777777;font-size:12px;">{$Position->cHinweis}</span>{/if}
            {/if}
          </td>
          <td valign="top" align="center" style="padding:12px 6px;border-bottom:1px solid #e9e9e9;">{$Position->nAnzahl}</td>
          <td valign="top" align="right" style="padding:12px 0;border-bottom:1px solid #e9e9e9;white-space:nowrap;">{$Position->cGesamtpreisLocalized[$NettoPreise]}</td>
        </tr>
      {/foreach}
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;margin-top:6px;">
      {if $Einstellungen.global.global_steuerpos_anzeigen !== 'N'}
        {foreach $Bestellung->Steuerpositionen as $Steuerposition}
          <tr><td align="right" style="padding:4px 0;color:#666666;">{$Steuerposition->cName}:</td><td align="right" width="110" style="padding:4px 0;color:#666666;">{$Steuerposition->cPreisLocalized}</td></tr>
        {/foreach}
      {/if}
      {if $Bestellung->GuthabenNutzen|default:0 === 1}
        <tr><td align="right" style="padding:4px 0;">{$mt.voucher}:</td><td align="right" width="110" style="padding:4px 0;"><strong>-{$Bestellung->GutscheinLocalized}</strong></td></tr>
      {/if}
      <tr><td align="right" style="padding:10px 0 4px;border-top:2px solid {$mtc.accent};font-size:16px;"><strong>{$mt.total}:</strong></td><td align="right" width="110" style="padding:10px 0 4px;border-top:2px solid {$mtc.accent};font-size:16px;color:{$mtc.accent};"><strong>{$Bestellung->WarensummeLocalized[0]}</strong></td></tr>
      {if !empty($payments)}
        {foreach $payments as $incommingPayment}{foreach $incommingPayment as $payment}
          <tr><td align="right" style="padding:3px 0;color:#666666;">{$mt.paid_in} ({$payment->cZahlungsanbieter}):</td><td align="right" width="110" style="padding:3px 0;color:#666666;">{$payment->paymentLocalization}</td></tr>
        {/foreach}{/foreach}
        <tr><td align="right" style="padding:4px 0;"><strong>{$mt.open_amount}:</strong></td><td align="right" width="110" style="padding:4px 0;"><strong>{if isset($totalLocalized)}{$totalLocalized}{else}{$Bestellung->WarensummeLocalized[0]}{/if}</strong></td></tr>
      {/if}
    </table>
  </td></tr>

  <tr><td class="mt-pad" style="padding:26px 40px 0;font-family:Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
      <td class="mt-col" valign="top" width="50%" style="padding-right:10px;font-size:13px;line-height:20px;">
        <div style="font-family:Georgia,'Times New Roman',serif;font-size:16px;color:{$mtc.accent};margin-bottom:6px;border-bottom:1px solid #e6e6e6;padding-bottom:4px;">{$mt.billing}</div>
        {if !empty($Kunde->cFirma)}{$Kunde->cFirma}<br>{/if}
        {$Kunde->cVorname} {$Kunde->cNachname}<br>
        {$Kunde->cStrasse} {$Kunde->cHausnummer}<br>
        {if $Kunde->cAdressZusatz}{$Kunde->cAdressZusatz}<br>{/if}
        {$Kunde->cPLZ} {$Kunde->cOrt}<br>
        {if $Kunde->cBundesland}{$Kunde->cBundesland}<br>{/if}
        {$Kunde->angezeigtesLand}
        {if $Kunde->cUSTID}<br>{$mt.vat_id}: {$Kunde->cUSTID}{/if}
      </td>
      <td class="mt-col" valign="top" width="50%" style="padding-left:10px;font-size:13px;line-height:20px;">
        <div style="font-family:Georgia,'Times New Roman',serif;font-size:16px;color:{$mtc.accent};margin-bottom:6px;border-bottom:1px solid #e6e6e6;padding-bottom:4px;">{$mt.shipping}</div>
        {if !empty($Bestellung->Lieferadresse->kLieferadresse)}
          {if !empty($Bestellung->Lieferadresse->cFirma)}{$Bestellung->Lieferadresse->cFirma}<br>{/if}
          {$Bestellung->Lieferadresse->cVorname} {$Bestellung->Lieferadresse->cNachname}<br>
          {$Bestellung->Lieferadresse->cStrasse} {$Bestellung->Lieferadresse->cHausnummer}<br>
          {if $Bestellung->Lieferadresse->cAdressZusatz}{$Bestellung->Lieferadresse->cAdressZusatz}<br>{/if}
          {$Bestellung->Lieferadresse->cPLZ} {$Bestellung->Lieferadresse->cOrt}<br>
          {if $Bestellung->Lieferadresse->cBundesland}{$Bestellung->Lieferadresse->cBundesland}<br>{/if}
          {$Bestellung->Lieferadresse->angezeigtesLand}
        {else}
          {if !empty($Kunde->cFirma)}{$Kunde->cFirma}<br>{/if}
          {$Kunde->cVorname} {$Kunde->cNachname}<br>
          {$Kunde->cStrasse} {$Kunde->cHausnummer}<br>
          {$Kunde->cPLZ} {$Kunde->cOrt}<br>
          {$Kunde->angezeigtesLand}
        {/if}
      </td>
    </tr></table>
  </td></tr>

  <tr><td class="mt-pad" style="padding:22px 40px 0;font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:22px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #e6e6e6;border-bottom:1px solid #e6e6e6;">
      <tr>
        <td class="mt-col" width="50%" style="padding:10px 0;color:#777777;">{$mt.payment}: <strong style="color:#2b2b2b;">{$Bestellung->cZahlungsartName}</strong></td>
        <td class="mt-col" width="50%" style="padding:10px 0;color:#777777;">{$mt.delivery}: <strong style="color:#2b2b2b;">{if isset($Bestellung->cEstimatedDeliveryEx)}{$Bestellung->cEstimatedDeliveryEx}{else}{$Bestellung->cEstimatedDelivery|default:'-'}{/if}</strong></td>
      </tr>
    </table>
    {if $mt_mod === 'za_ueberweisung_jtl'}
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;background:#f7f7f7;border:1px solid #e0e0e0;font-size:13px;">
        <tr><td colspan="2" style="padding:12px 16px 6px;font-family:Georgia,'Times New Roman',serif;font-size:16px;color:{$mtc.accent};">{$mt.transfer}</td></tr>
        <tr><td style="padding:3px 16px;width:38%;color:#777777;">{$mt.holder}</td><td style="padding:3px 16px;"><strong>{$Firma->cKontoinhaber}</strong></td></tr>
        <tr><td style="padding:3px 16px;color:#777777;">{$mt.bank}</td><td style="padding:3px 16px;"><strong>{$Firma->cBank}</strong></td></tr>
        <tr><td style="padding:3px 16px;color:#777777;">IBAN</td><td style="padding:3px 16px;"><strong>{$Firma->cIBAN}</strong></td></tr>
        <tr><td style="padding:3px 16px;color:#777777;">BIC</td><td style="padding:3px 16px;"><strong>{$Firma->cBIC}</strong></td></tr>
        <tr><td style="padding:3px 16px;color:#777777;">{$mt.purpose}</td><td style="padding:3px 16px;"><strong>{$Bestellung->cBestellNr}</strong></td></tr>
        <tr><td style="padding:3px 16px 12px;color:#777777;">{$mt.amount}</td><td style="padding:3px 16px 12px;"><strong>{if isset($totalLocalized)}{$totalLocalized}{else}{$Bestellung->WarensummeLocalized[0]}{/if}</strong></td></tr>
      </table>
    {elseif $mt_mod === 'za_lastschrift_jtl'}
      <p style="margin:16px 0 0;font-size:13px;line-height:20px;">{$mt.debit}:<br>{$mt.holder}: {$Bestellung->Zahlungsinfo->cInhaber}<br>IBAN: {$Bestellung->Zahlungsinfo->cIBAN|maskPrivate}<br>BIC: {$Bestellung->Zahlungsinfo->cBIC}<br>{$mt.bank}: {$Bestellung->Zahlungsinfo->cBankName}</p>
    {elseif $mt_mod === 'za_paypal_jtl'}
      <p style="margin:16px 0 0;font-size:13px;line-height:20px;">{$mt.paypal}: {$Einstellungen.zahlungsarten.zahlungsart_paypal_empfaengermail}</p>
    {/if}
    {if !empty($Zahlungsart->cHinweisText)}<div style="margin-top:14px;font-size:13px;line-height:20px;">{$Zahlungsart->cHinweisText}</div>{/if}
  </td></tr>

  <tr><td class="mt-pad" style="padding:22px 40px 8px;font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:22px;color:#555555;">
    <strong style="color:#2b2b2b;">{$mt.next}</strong><br>{$mt.next_text}
    <p style="margin:18px 0 0;color:#2b2b2b;">{$mt.regards}<br>{$mt.team} {$Firma->cName}</p>
  </td></tr>

  <tr><td class="mt-pad" align="center" style="padding:22px 40px 26px;font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:19px;color:#8a8a8a;border-top:1px solid #e6e6e6;">
    {if $mtc.support_mail || $mtc.support_phone}
      <strong style="color:#555555;">{$mt.help}</strong><br>
      {if $mtc.support_mail}<a href="mailto:{$mtc.support_mail}" style="color:{$mtc.accent};">{$mtc.support_mail}</a>{/if}{if $mtc.support_mail && $mtc.support_phone} &middot; {/if}{if $mtc.support_phone}{$mtc.support_phone}{/if}<br><br>
    {/if}
    {$Firma->cName}{if $Firma->cStrasse} &middot; {$Firma->cStrasse}{/if}{if $Firma->cPLZ} &middot; {$Firma->cPLZ} {$Firma->cOrt}{/if}<br>
    [AKZ]
    <div style="text-align:left;margin-top:14px;">[LEGAL_DATA]</div>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
