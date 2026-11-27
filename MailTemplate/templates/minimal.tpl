<!DOCTYPE html>
<html lang="{$mt.lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$mt.title}</title>
{literal}<style type="text/css">
body { margin:0; padding:0; }
img { border:0; outline:none; text-decoration:none; }
@media only screen and (max-width: 600px) {
  .mt-wrap { width:100% !important; }
  .mt-pad { padding-left:20px !important; padding-right:20px !important; }
  .mt-col { display:block !important; width:100% !important; padding:0 0 18px 0 !important; }
  .mt-h1 { font-size:26px !important; line-height:32px !important; }
}
</style>{/literal}
</head>
<body style="margin:0;padding:0;background:#ffffff;">
{assign var='mt_mod' value=$Bestellung->Zahlungsart->cModulId|default:''}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" class="mt-wrap" width="560" cellpadding="0" cellspacing="0" border="0" style="width:560px;max-width:560px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#111111;">

  <tr><td class="mt-pad" style="padding:0 0 28px;">
    {if $mtc.logo}<img src="{$mtc.logo}" alt="{$Einstellungen.global.global_shopname}" style="max-height:38px;max-width:200px;">
    {elseif $ShopLogoURL}<img src="{$ShopLogoURL}" alt="{$Einstellungen.global.global_shopname}" style="max-height:38px;max-width:200px;">
    {else}<span style="font-size:15px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;">{$Einstellungen.global.global_shopname}</span>{/if}
  </td></tr>

  <tr><td class="mt-pad" style="padding:0 0 26px;border-top:1px solid #111111;">
    <div style="padding-top:26px;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:{$mtc.accent};">{if $mtc.tagline}{$mtc.tagline}{else}{$mt.order_no} {$Bestellung->cBestellNr}{/if}</div>
    <h1 class="mt-h1" style="margin:10px 0 16px;font-size:32px;line-height:38px;font-weight:300;letter-spacing:-0.5px;color:#111111;">{$mt.title}</h1>
    <p style="margin:0 0 6px;font-size:15px;line-height:25px;">{$mt.hello} {$Kunde->cVorname} {$Kunde->cNachname},</p>
    <p style="margin:0;font-size:15px;line-height:25px;color:#555555;">{$mt.intro}</p>
    {if $mtc.note}<p style="margin:16px 0 0;font-size:14px;line-height:23px;color:#555555;">{$mtc.note}</p>{/if}
  </td></tr>

  <tr><td class="mt-pad" style="padding:0 0 24px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #dddddd;border-bottom:1px solid #dddddd;">
      <tr>
        <td class="mt-col" width="34%" valign="top" style="padding:14px 0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#888888;">{$mt.order_no}<br><span style="font-family:'Courier New',monospace;font-size:15px;letter-spacing:0;color:#111111;">{$Bestellung->cBestellNr}</span></td>
        <td class="mt-col" width="33%" valign="top" style="padding:14px 0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#888888;">{$mt.order_date}<br><span style="font-size:15px;letter-spacing:0;text-transform:none;color:#111111;">{$Bestellung->dErstelldatum_de|default:'-'}</span></td>
        <td class="mt-col" width="33%" valign="top" style="padding:14px 0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#888888;">{$mt.payment}<br><span style="font-size:15px;letter-spacing:0;text-transform:none;color:#111111;">{$Bestellung->cZahlungsartName}</span></td>
      </tr>
    </table>
  </td></tr>

  <tr><td class="mt-pad" style="padding:0 0 8px;">
    <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#888888;padding-bottom:6px;">{$mt.items}</div>
    {if !empty($Verfuegbarkeit_arr.cArtikelName_arr)}
      <p style="margin:6px 0 10px;font-size:13px;line-height:20px;color:#555555;border-left:2px solid {$mtc.accent};padding-left:12px;">
        <strong>{$mt.avail}:</strong> {$Verfuegbarkeit_arr.cHinweis}
        {foreach $Verfuegbarkeit_arr.cArtikelName_arr as $cArtikelname}<br>&ndash; {$cArtikelname}{/foreach}
      </p>
    {/if}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    {foreach $Bestellung->Positionen as $Position}
      <tr>
        <td valign="top" style="padding:14px 0;border-bottom:1px solid #eeeeee;font-size:14px;line-height:21px;">
          {if $Position->nPosTyp == 1}
            <span style="color:#888888;">{$Position->nAnzahl}&times;</span>&nbsp; <strong style="font-weight:600;">{$Position->cName}</strong>{if $Position->cArtNr} <span style="color:#aaaaaa;font-size:12px;">{$Position->cArtNr}</span>{/if}
            {foreach $Position->WarenkorbPosEigenschaftArr as $WKPosEigenschaft}
              <div style="font-size:12px;color:#888888;padding-left:26px;">{$WKPosEigenschaft->cEigenschaftName}: {$WKPosEigenschaft->cEigenschaftWertName}</div>
            {/foreach}
            {if $Einstellungen.kaufabwicklung.bestellvorgang_lieferstatus_anzeigen === 'Y' && $Position->cLieferstatus}<div style="font-size:12px;color:#888888;padding-left:26px;">{$mt.delivery}: {$Position->cLieferstatus}</div>{/if}
          {else}
            <strong style="font-weight:600;">{$Position->cName}</strong>{if !empty($Position->cHinweis)} <span style="color:#888888;font-size:12px;">{$Position->cHinweis}</span>{/if}
          {/if}
        </td>
        <td valign="top" align="right" style="padding:14px 0 14px 12px;border-bottom:1px solid #eeeeee;font-size:14px;white-space:nowrap;">{$Position->cGesamtpreisLocalized[$NettoPreise]}</td>
      </tr>
    {/foreach}
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;margin-top:8px;">
      {if $Einstellungen.global.global_steuerpos_anzeigen !== 'N'}
        {foreach $Bestellung->Steuerpositionen as $Steuerposition}
          <tr><td style="padding:3px 0;color:#888888;">{$Steuerposition->cName}</td><td align="right" style="padding:3px 0;color:#888888;">{$Steuerposition->cPreisLocalized}</td></tr>
        {/foreach}
      {/if}
      {if $Bestellung->GuthabenNutzen|default:0 === 1}
        <tr><td style="padding:3px 0;">{$mt.voucher}</td><td align="right" style="padding:3px 0;">-{$Bestellung->GutscheinLocalized}</td></tr>
      {/if}
      <tr><td style="padding:12px 0 0;font-size:18px;font-weight:600;">{$mt.total}</td><td align="right" style="padding:12px 0 0;font-size:18px;font-weight:600;color:{$mtc.accent};">{$Bestellung->WarensummeLocalized[0]}</td></tr>
      {if !empty($payments)}
        {foreach $payments as $incommingPayment}{foreach $incommingPayment as $payment}
          <tr><td style="padding:3px 0;color:#888888;">{$mt.paid_in} &ndash; {$payment->cZahlungsanbieter}</td><td align="right" style="padding:3px 0;color:#888888;">{$payment->paymentLocalization}</td></tr>
        {/foreach}{/foreach}
        <tr><td style="padding:3px 0;font-weight:600;">{$mt.open_amount}</td><td align="right" style="padding:3px 0;font-weight:600;">{if isset($totalLocalized)}{$totalLocalized}{else}{$Bestellung->WarensummeLocalized[0]}{/if}</td></tr>
      {/if}
    </table>
  </td></tr>

  <tr><td class="mt-pad" style="padding:26px 0 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
      <td class="mt-col" width="50%" valign="top" style="padding-right:14px;font-size:13px;line-height:21px;color:#333333;">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#888888;padding-bottom:6px;">{$mt.billing}</div>
        {if !empty($Kunde->cFirma)}{$Kunde->cFirma}<br>{/if}
        {$Kunde->cVorname} {$Kunde->cNachname}<br>
        {$Kunde->cStrasse} {$Kunde->cHausnummer}<br>
        {if $Kunde->cAdressZusatz}{$Kunde->cAdressZusatz}<br>{/if}
        {$Kunde->cPLZ} {$Kunde->cOrt}<br>
        {if $Kunde->cBundesland}{$Kunde->cBundesland}<br>{/if}
        {$Kunde->angezeigtesLand}
      </td>
      <td class="mt-col" width="50%" valign="top" style="padding-left:14px;font-size:13px;line-height:21px;color:#333333;">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#888888;padding-bottom:6px;">{$mt.shipping}</div>
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

  <tr><td class="mt-pad" style="padding:10px 0 0;font-size:13px;line-height:21px;color:#333333;">
    <div style="padding:10px 0;border-top:1px solid #dddddd;"><span style="color:#888888;">{$mt.delivery}:</span> {if isset($Bestellung->cEstimatedDeliveryEx)}{$Bestellung->cEstimatedDeliveryEx}{else}{$Bestellung->cEstimatedDelivery|default:'-'}{/if}</div>
    {if $mt_mod === 'za_ueberweisung_jtl'}
      <div style="margin-top:6px;padding:16px 18px;border:1px solid #111111;">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:{$mtc.accent};padding-bottom:8px;">{$mt.transfer}</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;line-height:21px;">
          <tr><td width="38%" style="color:#888888;">{$mt.holder}</td><td>{$Firma->cKontoinhaber}</td></tr>
          <tr><td style="color:#888888;">{$mt.bank}</td><td>{$Firma->cBank}</td></tr>
          <tr><td style="color:#888888;">IBAN</td><td style="font-family:'Courier New',monospace;">{$Firma->cIBAN}</td></tr>
          <tr><td style="color:#888888;">BIC</td><td style="font-family:'Courier New',monospace;">{$Firma->cBIC}</td></tr>
          <tr><td style="color:#888888;">{$mt.purpose}</td><td style="font-family:'Courier New',monospace;">{$Bestellung->cBestellNr}</td></tr>
          <tr><td style="color:#888888;">{$mt.amount}</td><td><strong>{if isset($totalLocalized)}{$totalLocalized}{else}{$Bestellung->WarensummeLocalized[0]}{/if}</strong></td></tr>
        </table>
      </div>
    {elseif $mt_mod === 'za_lastschrift_jtl'}
      <p style="margin:8px 0 0;">{$mt.debit}:<br>{$mt.holder}: {$Bestellung->Zahlungsinfo->cInhaber}<br>IBAN: {$Bestellung->Zahlungsinfo->cIBAN|maskPrivate}<br>BIC: {$Bestellung->Zahlungsinfo->cBIC}<br>{$mt.bank}: {$Bestellung->Zahlungsinfo->cBankName}</p>
    {elseif $mt_mod === 'za_paypal_jtl'}
      <p style="margin:8px 0 0;">{$mt.paypal}: {$Einstellungen.zahlungsarten.zahlungsart_paypal_empfaengermail}</p>
    {/if}
    {if !empty($Zahlungsart->cHinweisText)}<div style="margin-top:10px;">{$Zahlungsart->cHinweisText}</div>{/if}
  </td></tr>

  <tr><td class="mt-pad" style="padding:22px 0 8px;font-size:14px;line-height:23px;color:#555555;">
    {$mt.next_text}
    <p style="margin:16px 0 0;color:#111111;">{$mt.regards}<br>{$mt.team} {$Firma->cName}</p>
    <p style="margin:16px 0 0;"><a href="{$ShopURL}" style="color:{$mtc.accent};text-decoration:underline;">{$mt.visit} &rarr;</a></p>
  </td></tr>

  <tr><td class="mt-pad" style="padding:26px 0 0;border-top:1px solid #111111;font-size:12px;line-height:19px;color:#888888;">
    {if $mtc.support_mail || $mtc.support_phone}
      {$mt.help} {if $mtc.support_mail}<a href="mailto:{$mtc.support_mail}" style="color:#111111;">{$mtc.support_mail}</a>{/if}{if $mtc.support_mail && $mtc.support_phone} &middot; {/if}{if $mtc.support_phone}{$mtc.support_phone}{/if}<br><br>
    {/if}
    {$Firma->cName}{if $Firma->cStrasse} &middot; {$Firma->cStrasse}{/if}{if $Firma->cPLZ} &middot; {$Firma->cPLZ} {$Firma->cOrt}{/if}<br>
    [AKZ]
    <div style="margin-top:14px;">[LEGAL_DATA]</div>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
