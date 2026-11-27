<?php

declare(strict_types=1);

namespace Plugin\MailTemplate;

final class Labels
{
    /**
     * @return array<string, string>
     */
    public static function get(string $lang): array
    {
        return \stripos($lang, 'en') === 0 ? self::en() : self::de();
    }

    /**
     * @return array<string, string>
     */
    private static function de(): array
    {
        return [
            'lang'         => 'de',
            'title'        => 'Vielen Dank für Ihre Bestellung!',
            'hello'        => 'Guten Tag',
            'intro'        => 'wir haben Ihre Bestellung erhalten und kümmern uns umgehend darum. Hier ist die Übersicht.',
            'order_no'     => 'Bestellnummer',
            'order_date'   => 'Bestelldatum',
            'payment'      => 'Zahlungsart',
            'delivery'     => 'Voraussichtliche Lieferung',
            'items'        => 'Ihre Bestellung',
            'article'      => 'Artikel',
            'qty'          => 'Menge',
            'price'        => 'Preis',
            'sum'          => 'Summe',
            'voucher'      => 'Gutschein',
            'total'        => 'Gesamtsumme',
            'open_amount'  => 'Offener Betrag',
            'paid_in'      => 'Zahlungen erhalten',
            'billing'      => 'Rechnungsadresse',
            'shipping'     => 'Lieferadresse',
            'transfer'     => 'Bitte überweisen Sie den Betrag',
            'holder'       => 'Kontoinhaber',
            'bank'         => 'Bank',
            'purpose'      => 'Verwendungszweck',
            'amount'       => 'Betrag',
            'debit'        => 'Wir belasten in Kürze folgendes Bankkonto mit der fälligen Summe',
            'paypal'       => 'Falls Sie Ihre Zahlung per PayPal noch nicht durchgeführt haben, nutzen Sie folgende E-Mail-Adresse als Empfänger',
            'avail'        => 'Hinweis zur Verfügbarkeit',
            'next'         => 'Wie geht es weiter?',
            'next_text'    => 'Über den weiteren Verlauf Ihrer Bestellung informieren wir Sie jeweils gesondert per E-Mail.',
            'help'         => 'Fragen zu Ihrer Bestellung?',
            'regards'      => 'Mit freundlichem Gruß',
            'team'         => 'Ihr Team von',
            'visit'        => 'Weiter im Shop',
            'step_1'       => 'Bestellt',
            'step_2'       => 'Bezahlt',
            'step_3'       => 'Versandt',
            'step_4'       => 'Geliefert',
            'phone'        => 'Telefon',
            'mail'         => 'E-Mail',
            'vat_id'       => 'USt-IdNr.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function en(): array
    {
        return [
            'lang'         => 'en',
            'title'        => 'Thank you for your order!',
            'hello'        => 'Hello',
            'intro'        => 'we have received your order and are taking care of it right away. Here is your summary.',
            'order_no'     => 'Order number',
            'order_date'   => 'Order date',
            'payment'      => 'Payment method',
            'delivery'     => 'Estimated delivery',
            'items'        => 'Your order',
            'article'      => 'Item',
            'qty'          => 'Qty',
            'price'        => 'Price',
            'sum'          => 'Total',
            'voucher'      => 'Voucher',
            'total'        => 'Order total',
            'open_amount'  => 'Amount due',
            'paid_in'      => 'Payments received',
            'billing'      => 'Billing address',
            'shipping'     => 'Shipping address',
            'transfer'     => 'Please transfer the amount',
            'holder'       => 'Account holder',
            'bank'         => 'Bank',
            'purpose'      => 'Reference',
            'amount'       => 'Amount',
            'debit'        => 'We will shortly debit the following bank account with the amount due',
            'paypal'       => 'If you have not yet paid via PayPal, please use the following e-mail address as recipient',
            'avail'        => 'Availability notice',
            'next'         => 'What happens next?',
            'next_text'    => 'We will keep you informed about the progress of your order by e-mail.',
            'help'         => 'Questions about your order?',
            'regards'      => 'Kind regards',
            'team'         => 'Your team at',
            'visit'        => 'Continue shopping',
            'step_1'       => 'Ordered',
            'step_2'       => 'Paid',
            'step_3'       => 'Shipped',
            'step_4'       => 'Delivered',
            'phone'        => 'Phone',
            'mail'         => 'E-mail',
            'vat_id'       => 'VAT ID',
        ];
    }
}
