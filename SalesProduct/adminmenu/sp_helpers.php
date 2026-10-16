<?php

declare(strict_types=1);

use JTL\Shop;

if (!function_exists('sp_h')) {
    function sp_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sp_looks_like_xtea')) {
    function sp_looks_like_xtea(string $value): bool
    {
        if ($value === '' || \strlen($value) % 4 !== 0) {
            return false;
        }
        if (\preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $value) !== 1) {
            return false;
        }
        $bin = \base64_decode($value, true);
        if ($bin === false || $bin === '') {
            return false;
        }

        return \strlen($bin) % 8 === 0;
    }
}

if (!function_exists('sp_decrypt_field')) {
    function sp_decrypt_field(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $trimmed = \trim($value);
        if (!sp_looks_like_xtea($trimmed)) {
            return $trimmed;
        }
        try {
            $crypto = Shop::Container()->getCryptoService();
            if (\is_object($crypto) && \method_exists($crypto, 'decryptXTEA')) {
                \set_error_handler(static function (int $errno, string $errstr): bool {
                    throw new \RuntimeException($errstr, $errno);
                });
                try {
                    $plain = (string)$crypto->decryptXTEA($trimmed);
                } finally {
                    \restore_error_handler();
                }
                $plain = \rtrim($plain, "\0");
                if ($plain !== '' && \preg_match('//u', $plain) === 1) {
                    return \trim($plain);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $trimmed;
    }
}

if (!function_exists('sp_format_money')) {
    function sp_format_money($value, ?string $currencyIso): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $num = (float)$value;
        $formatted = \number_format($num, 2, ',', '.');
        $iso = \strtoupper(\trim((string)($currencyIso ?? '')));
        $symbol = match ($iso) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            ''    => '€',
            default => $iso,
        };

        return $formatted . ' ' . $symbol;
    }
}

if (!function_exists('sp_url_host')) {
    function sp_url_host(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $host = (string)\parse_url($url, \PHP_URL_HOST);

        return $host !== '' ? \strtolower($host) : '';
    }
}

if (!function_exists('sp_is_payment_redirect_host')) {
    function sp_is_payment_redirect_host(string $host): bool
    {
        if ($host === '') {
            return false;
        }
        $patterns = [
            '/(^|\\.)amazon\\.([a-z.]+)$/',
            '/(^|\\.)paypal\\.([a-z.]+)$/',
            '/(^|\\.)paypalobjects\\.com$/',
            '/(^|\\.)klarna\\.com$/',
            '/(^|\\.)checkout\\.klarna\\.com$/',
            '/(^|\\.)stripe\\.com$/',
            '/(^|\\.)checkout\\.stripe\\.com$/',
            '/(^|\\.)adyen\\.com$/',
            '/(^|\\.)sofort\\.com$/',
            '/(^|\\.)mollie\\.com$/',
            '/(^|\\.)heidelpay\\.com$/',
            '/(^|\\.)unzer\\.com$/',
            '/(^|\\.)secupay\\.(com|ag)$/',
            '/(^|\\.)wirecard\\.com$/',
            '/(^|\\.)payone\\.com$/',
            '/(^|\\.)novalnet\\.de$/',
            '/(^|\\.)computop-paygate\\.com$/',
            '/(^|\\.)girosolution\\.de$/',
            '/(^|\\.)giropay\\.de$/',
            '/(^|\\.)ratepay\\.com$/',
            '/(^|\\.)billsafe\\.de$/',
        ];
        foreach ($patterns as $p) {
            if (\preg_match($p, $host) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('sp_extract_source_info')) {
    function sp_extract_source_info(?string $json): array
    {
        $empty = ['detail' => '', 'fromFirst' => false];
        if ($json === null || $json === '') {
            return $empty;
        }
        $data = \json_decode($json, true);
        if (!\is_array($data)) {
            return $empty;
        }
        foreach (['picked', 'last', 'first'] as $k) {
            $node = $data[$k] ?? null;
            if (!\is_array($node)) {
                continue;
            }
            $detail = \trim((string)($node['source_detail'] ?? ''));
            if ($detail === '') {
                continue;
            }
            $host = sp_url_host($detail);
            if ($host !== '' && sp_is_payment_redirect_host($host)) {
                continue;
            }

            return [
                'detail'    => $detail,
                'fromFirst' => $k === 'first',
            ];
        }

        return $empty;
    }

    function sp_extract_source_detail(?string $json): string
    {
        return sp_extract_source_info($json)['detail'];
    }
}

if (!function_exists('sp_csv_row')) {
    /** @param array<int, string> $row */
    function sp_csv_row(array $row, string $delimiter = ';'): string
    {
        $buf = '';
        $first = true;
        foreach ($row as $cell) {
            if (!$first) {
                $buf .= $delimiter;
            }
            $first = false;
            $cell = \str_replace('"', '""', (string)$cell);
            if (\strpbrk($cell, "{$delimiter}\r\n\"") !== false) {
                $buf .= '"' . $cell . '"';
            } else {
                $buf .= $cell;
            }
        }

        return $buf . "\r\n";
    }
}
