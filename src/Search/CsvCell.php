<?php

namespace Ispgrid\Ipdr\Search;

/**
 * A CSV cell a spreadsheet will not run: text starting with =, +, -, @, tab or CR (a customer named
 * "=HYPERLINK(...)") gets a leading apostrophe; numbers stay numbers.
 */
final class CsvCell
{
    public static function safe(string|int|float|null $value): string
    {
        $text = (string) $value;

        return $text !== '' && ! is_numeric($text) && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }
}
