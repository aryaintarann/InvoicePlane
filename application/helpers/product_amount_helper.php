<?php

defined('BASEPATH') || exit('No direct script access allowed');

/**
 * Parse a product's DECIMAL(20,2) amount using the configured separators.
 * A numeric-looking string such as "1.234" can still be grouped rupiah.
 *
 * @return string|false|null Normalized amount, false for invalid input, null for blank input.
 */
function standardize_product_amount($value): string|false|null
{
    if ($value === null) {
        return null;
    }

    if ( ! is_string($value) && ! is_int($value) && ! is_float($value)) {
        return false;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $thousands = (string) get_setting('thousands_separator');
    $decimal   = product_decimal_separator();
    $integer   = '[0-9]+';

    if ($thousands !== '') {
        $integer = '(?:[0-9]+|[0-9]{1,3}(?:' . preg_quote($thousands, '/') . '[0-9]{3})+)';
    }

    $fraction = '(?:' . preg_quote($decimal, '/') . '[0-9]+)?';
    if ($decimal === $thousands || ! preg_match('/\A' . $integer . $fraction . '\z/', $value)) {
        return false;
    }

    if ($thousands !== '') {
        $value = str_replace($thousands, '', $value);
    }
    $value = str_replace($decimal, '.', $value);

    $parts    = explode('.', $value, 2);
    $parts[0] = ltrim($parts[0], '0');
    $parts[0] = $parts[0] === '' ? '0' : $parts[0];

    if (strlen($parts[0]) > 18 || (isset($parts[1]) && strlen(rtrim($parts[1], '0')) > 2)) {
        return false;
    }

    if (isset($parts[1])) {
        $parts[1] = substr($parts[1], 0, 2);
    }

    return implode('.', $parts);
}

function product_decimal_separator(): string
{
    $decimal = (string) get_setting('decimal_point');

    // Whole-unit display settings must not erase existing product cents on edit.
    return $decimal !== '' ? $decimal : (get_setting('thousands_separator') === '.' ? ',' : '.');
}

function format_product_amount($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    // Keep DECIMAL(20,2) values exact when an unchanged form is submitted.
    $parts = explode('.', (string) $value, 2);
    $thousands = (string) get_setting('thousands_separator');
    $integer = preg_replace_callback('/[0-9](?=(?:[0-9]{3})+$)/', static fn ($match) => $match[0] . $thousands, $parts[0]);
    if (get_setting('decimal_point') === '' && (int) ($parts[1] ?? '0') === 0) {
        return $integer;
    }
    $decimals = max(2, (int) get_setting('tax_rate_decimal_places'));

    return $integer . product_decimal_separator() . str_pad($parts[1] ?? '', $decimals, '0');
}
