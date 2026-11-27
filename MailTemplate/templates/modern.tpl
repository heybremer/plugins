<!DOCTYPE html>
<html lang="{$mt.lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$mt.title}</title>
{literal}<style type="text/css">
body { margin:0; padding:0; }
img { border:0; outline:none; text-decoration:none; }
a { color:inherit; }
@media only screen and (max-width: 620px) {
  .mt-wrap { width:100% !important; }
  .mt-pad { padding-left:18px !important; padding-right:18px !important; }
  .mt-col { display:block !important; width:100% !important; padding:0 0 12px 0 !important; }
  .mt-hide { display:none !important; }
  .mt-h1 { font-size:24px !important; line-height:30px !important; }
}
</style>{/literal}
</head>
<body style="margin:0;padding:0;background:#eef0f4;">
{assign var='mt_mod' value=$Bestellung->Zahlungsart->cModulId|default:''}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef0f4;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" class="mt-wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;font-family:Helvetica,Arial,sans-serif;color:#1f2937;">

  <tr><td align="center" style="padding:6px 0 18px;">
    {if $mtc.logo}<img src="{$mtc.logo}" alt="{$Einstellungen.global.global_shopname}" style="max-height:48px;max-width:240px;">
    {elseif $ShopLogoURL}<img src="{$ShopLogoURL}" alt="{$Einstellungen.global.global_shopname}" style="max-height:48px;max-width:240px;">
    {else}<span style="font-size:22px;font-weight:bold;color:{$mtc.accent};">{$Einstellungen.global.global_shopname}</span>{/if}
  </td></tr>

  <tr><td class="mt-pad" align="center" style="background:{$mtc.accent};border-radius:16px 16px 0 0;padding:36px 36px 30px;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
      <td align="center" width="56" height="56" style="width:56px;height:56px;border-radius:28px;background:#ffffff;color:{$mtc.accent};font-size:30px;line-height:56px;font-weight:bold;">&#10003;</td>
    </tr></table>
    <h1 class="mt-h1" style="margin:18px 0 8px;font-size:28px;line-height:34px;color:#ffffff;font-weight:bold;">{$mt.title}</h1>
    <p style="margin:0;font-size:15px;line-height:22px;color:#ffffff;opacity:.9;">{if $mtc.tagline}{$mtc.tagline}{else}{$mt.order_no}{/if}</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;"><tr>
      <td style="background:{$mtc.accent_dark};border-radius:20px;padding:8px 18px;font-size:14px;color:#ffffff;">{$mt.order_no}: <strong>{$Bestellung->cBestellNr}</strong></td>
    </tr></table>
  </td></tr>

  <tr><td class="mt-pad" style="background:#ffffff;padding:30px 36px 8px;">
    <p style="margin:0 0 6px;font-size:16px;line-height:24px;"><strong>{$mt.hello} {$Kunde->cVorname} {$Kunde->cNachname},</strong></p>
    <p style="margin:0;font-size:15px;line-height:24px;color:#4b5563;">{$mt.intro}</p>
    {if $mtc.note}<p style="margin:14px 0 0;padding:12px 16px;background:{$mtc.accent_tint};border-left:4px solid {$mtc.accent};font-size:14px;line-height:22px;color:#374151;">{$mtc.note}</p>{/if}
  </td></tr>

  <tr><td class="mt-pad" style="background:#ffffff;padding:22px 36px 6px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
      <td align="center" width="25%" style="font-size:12px;color:#111827;"><div style="width:30px;height:30px;line-height:30px;border-radius:15px;background:{$mtc.accent};color:#ffffff;font-weight:bold;margin:0 auto 6px;">1</div><strong>{$mt.step_1}</strong></td>
      <td align="center" width="25%" style="font-size:12px;color:#9ca3af;"><div style="width:30px;height:30px;line-height:30px;border-radius:15px;background:#e5e7eb;color:#6b7280;font-weight:bold;margin:0 auto 6px;">2</div>{$mt.step_2}</td>
      <td align="center" width="25%" style="font-size:12px;color:#9ca3af;"><div style="width:30px;height:30px;line-height:30px;border-radius:15px;background:#e5e7eb;color:#6b7280;font-weight:bold;margin:0 auto 6px;">3</div>{$mt.step_3}</td>
      <td align="center" width="25%" style="font-size:12px;color:#9ca3af;"><div style="width:30px;height:30px;line-height:30px;border-radius:15px;background:#e5e7eb;color:#6b7280;font-weight:bold;margin:0 auto 6px;">4</div>{$mt.step_4}</td>
    </tr></table>
  </td></tr>

  <tr><td class="mt-pad" style="background:#ffffff;padding:20px 36px 4px;">
    <h2 style="margin:0 0 10px;font-size:16px;color:#111827;">{$mt.items}</h2>
    {if !empty($Verfuegbarkeit_arr.cArtikelName_arr)}
      <div style="margin:0 0 12px;padding:10px 14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:13px;line-height:20px;color:#9a3412;">
        <strong>{$mt.avail}:</strong> {$Verfuegbarkeit_arr.cHinweis}
        {foreach $Verfuegbarkeit_arr.cArtikelName_arr as $cArtikelname}<br>&bull; {$cArtikelname}{/foreach}
      </div>
    {/if}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    {foreach $Bestellung->Positionen as $Position}
      <tr>
        <td valign="top" style="padding:12px 0;border-top:1px solid #eef0f4;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
            <td valign="top" width="44" style="width:44px;"><div style="min-width:34px;padding:4px 0;border-radius:8px;background:{$mtc.accent_tint};color:{$mtc.accent};font-weight:bold;font-size:13px;text-align:center;">{$Position->nAnzahl}&times;</div></td>
            <td valign="top" style="font-size:14px;line-height:20px;color:#111827;">
              {if $Position->nPosTyp == 1}
                <strong>{$Position->cName}</strong>{if $Position->cArtNr} <span style="color:#9ca3af;font-size:12px;">({$Position->cArtNr})</span>{/if}
                {foreach $Position->WarenkorbPosEigenschaftArr as $WKPosEigenschaft}
                  <div style="font-size:12px;color:#6b7280;">{$WKPosEigenschaft->cEigenschaftName}: {$WKPosEigenschaft->cEigenschaftWertName}</div>
                {/foreach}
                {if $Einstellungen.kaufabwicklung.bestellvorgang_lieferstatus_anzeigen === 'Y' && $Position->cLieferstatus}<div style="font-size:12px;color:#6b7280;">{$mt.delivery}: {$Position->cLieferstatus}</div>{/if}
                {if $Einstellungen.kaufabwicklung.bestellvorgang_einzelpreise_anzeigen === 'Y' && isset($Position->cEinzelpreisLocalized)}<div style="font-size:12px;color:#9ca3af;">{$Position->cEinzelpreisLocalized[$NettoPreise]} / Stk.</div>{/if}
              {else}
                <strong>{$Position->cName}</strong>{if !empty($Position->cHinweis)} <span style="color:#6b7280;font-size:12px;">{$Position->cHinweis}</span>{/if}
              {/if}
            </td>
            <td valign="top" align="right" style="font-size:14px;font-weight:bold;color:#111827;white-space:nowrap;padding-left:10px;">{$Position->cGesamtpreisLocalized[$NettoPreise]}</td>
          </tr></table>
        </td>
      </tr>
    {/foreach}
    </table>
  </td></tr>

  <tr><td class="mt-pad" style="background:#ffffff;padding:8px 36px 26px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{$mtc.accent_tint};border-radius:12px;">
      <tr><td style="padding:16px 20px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;color:#374151;">
          {if $Einstellungen.global.global_steuerpos_anzeigen !== 'N'}
            {foreach $Bestellung->Steuerpositionen as $Steuerposition}
              <tr><td style="padding:3px 0;">{$Steuerposition->cName}</td><td align="right" style="padding:3px 0;">{$Steuerposition->cPreisLocalized}</td></tr>
            {/foreach}
          {/if}
          {if $Bestellung->GuthabenNutzen|default:0 === 1}
            <tr><td style="padding:3px 0;">{$mt.voucher}</td><td align="right" style="padding:3px 0;"><strong>-{$Bestellung->GutscheinLocalized}</strong></td></tr>
          {/if}
          <tr><td style="padding:10px 0 0;font-size:17px;font-weight:bold;color:#111827;">{$mt.total}</td><td align="right" style="padding:10px 0 0;font-size:20px;font-weight:bold;color:{$mtc.accent};">{$Bestellung->WarensummeLocalized[0]}</td></tr>
        </table>
      </td></tr>
    </table>
    {if !empty($payments)}
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;font-size:13px;color:#374151;">
        <tr><td colspan="2" style="padding:4px 0;"><strong>{$mt.paid_in}</strong></td></tr>
        {foreach $payments as $incommingPayment}{foreach $incommingPayment as $payment}
          <tr><td style="padding:2px 0;">{$payment->cZahlungsanbieter}</td><td align="right" style="padding:2px 0;">{$payment->paymentLocalization}</td></tr>
        {/foreach}{/foreach}
        <tr><td style="padding:6px 0 0;"><strong>{$mt.open_amount}</strong></td><td align="right" style="padding:6px 0 0;"><strong>{if isset($totalLocalized)}{$totalLocalized}{else}{$Bestellung->WarensummeLocalized[0]}{/if}</strong></td></tr>
      </table>
    {/if}
  </td></tr>

  <tr><td class="mt-pad" style="background:#ffffff;padding:0 36px 10px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
      <td class="mt-col" valign="top" width="50%" style="padding-right:8px;">
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;font-size:13px;line-height:20px;color:#374151;">
          <div style="font-size:11px;letter-spacing:1px;text-transform:uppercase;color:{$mtc.accent};font-weight:bold;margin-bottom:6px;">{$mt.billing}</div>
          {if !empty($Kunde->cFirma)}{$Kunde->cFirma}<br>{/if}
          {$Kunde->cVorname} {$Kunde->cNachname}<br>
          {$Kunde->cStrasse} {$Kunde->cHausnummer}<br>
          {if $Kunde->cAdressZusatz}{$Kunde->cAdressZusatz}<br>{/if}
          {$Kunde->cPLZ} {$Kunde->cOrt}<br>
          {if $Kunde->cBundesland}{$Kunde->cBundesland}<br>{/if}
          {$Kunde->angezeigtesLand}
        </div>
      </td>
      <td class="mt-col" valign="top" width="50%" style="padding-left:8px;">
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;font-size:13px;line-height:20px;color:#374151;">
          <div style="font-size:11px;letter-spacing:1px;text-transform:uppercase;color:{$mtc.accent};font-weight:bold;margin-bottom:6px;">{$mt.shipping}</div>
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
        </div>
      </td>
    </tr></table>
  </td></tr>

  <tr><td class="mt-pad" style="background:#ffffff;padding:6px 36px 6px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;color:#374151;">
      <tr>
        <td style="padding:8px 0;border-top:1px solid #eef0f4;">{$mt.payment}</td>
        <td align="right" style="padding:8px 0;border-top:1px solid #eef0f4;"><strong>{$Bestellung->cZahlungsartName}</strong></td>
      </tr>
      <tr>
        <td style="padding:8px 0;border-top:1px solid #eef0f4;">{$mt.delivery}</td>
        <td align="right" style="padding:8px 0;border-top:1px solid #eef0f4;"><strong>{if isset($Bestellung->cEstimatedDeliveryEx)}{$Bestellung->cEstimatedDeliveryEx}{else}{$Bestellung->cEstimatedDelivery|default:'-'}{/if}</strong></td>
      </tr>
    </table>
    {if $mt_mod === 'za_ueberweisung_jtl'}
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px;border:1px dashed {$mtc.accent};border-radius:12px;font-size:13px;color:#374151;">
        <tr><td colspan="2" style="padding:12px 16px 4px;font-weight:bold;color:{$mtc.accent};">{$mt.transfer}</td></tr>
        <tr><td style="padding:3px 16px;width:40%;">{$mt.holder}</td><td style="padding:3px 16px;"><strong>{$Firma->cKontoinhaber}</strong></td></tr>
        <tr><td style="padding:3px 16px;">{$mt.bank}</td><td style="padding:3px 16px;"><strong>{$Firma->cBank}</strong></td></tr>
        <tr><td style="padding:3px 16px;">IBAN</td><td style="padding:3px 16px;"><strong>{$Firma->cIBAN}</strong></td></tr>
        <tr><td style="padding:3px 16px;">BIC</td><td style="padding:3px 16px;"><strong>{$Firma->cBIC}</strong></td></tr>
        <tr><td style="padding:3px 16px;">{$mt.purpose}</td><td style="padding:3px 16px;"><strong>{$Bestellung->cBestellNr}</strong></td></tr>
        <tr><td style="padding:3px 16px 12px;">{$mt.amount}</td><td style="padding:3px 16px 12px;"><strong>{if isset($totalLocalized)}{$totalLocalized}{else}{$Bestellung->WarensummeLocalized[0]}{/if}</strong></td></tr>
      </table>
    {elseif $mt_mod === 'za_lastschrift_jtl'}
      <p style="margin:14px 0 0;font-size:13px;line-height:20px;color:#374151;">{$mt.debit}:<br>{$mt.holder}: {$Bestellung->Zahlungsinfo->cInhaber}<br>IBAN: {$Bestellung->Zahlungsinfo->cIBAN|maskPrivate}<br>BIC: {$Bestellung->Zahlungsinfo->cBIC}<br>{$mt.bank}: {$Bestellung->Zahlungsinfo->cBankName}</p>
    {elseif $mt_mod === 'za_paypal_jtl'}
      <p style="margin:14px 0 0;font-size:13px;line-height:20px;color:#374151;">{$mt.paypal}: {$Einstellungen.zahlungsarten.zahlungsart_paypal_empfaengermail}</p>
    {/if}
    {if !empty($Zahlungsart->cHinweisText)}<div style="margin-top:12px;font-size:13px;line-height:20px;color:#374151;">{$Zahlungsart->cHinweisText}</div>{/if}
  </td></tr>

  <tr><td class="mt-pad" align="center" style="background:#ffffff;padding:22px 36px 8px;">
    <p style="margin:0 0 4px;font-size:15px;font-weight:bold;color:#111827;">{$mt.next}</p>
    <p style="margin:0 0 18px;font-size:14px;line-height:22px;color:#4b5563;">{$mt.next_text}</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
      <td align="center" style="background:{$mtc.accent};border-radius:10px;"><a href="{$ShopURL}" style="display:inline-block;padding:13px 28px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">{$mt.visit}</a></td>
    </tr></table>
  </td></tr>

  <tr><td class="mt-pad" style="background:#ffffff;padding:24px 36px 30px;border-radius:0 0 16px 16px;font-size:14px;line-height:22px;color:#374151;">
    {$mt.regards}<br>{$mt.team} {$Firma->cName}
  </td></tr>

  <tr><td class="mt-pad" align="center" style="padding:20px 30px 6px;font-size:12px;line-height:19px;color:#6b7280;">
    {if $mtc.support_mail || $mtc.support_phone}
      <strong style="color:#374151;">{$mt.help}</strong><br>
      {if $mtc.support_mail}<a href="mailto:{$mtc.support_mail}" style="color:{$mtc.accent};text-decoration:none;">{$mtc.support_mail}</a>{/if}{if $mtc.support_mail && $mtc.support_phone} &middot; {/if}{if $mtc.support_phone}{$mtc.support_phone}{/if}<br><br>
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
