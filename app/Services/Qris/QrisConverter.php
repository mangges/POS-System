<?php

namespace App\Services\Qris;

use InvalidArgumentException;

class QrisConverter
{
    public static function toDynamic(string $staticQris, int $amount): string
    {
        $staticQris = trim($staticQris);

        self::assertValidCrc($staticQris);

        $body = substr($staticQris, 0, -4);
        $elements = self::parseTlv($body);

        // The trailing tag 63 (CRC) is malformed after stripping its value above — drop it, we rebuild it fresh.
        array_pop($elements);

        $elements = array_map(function (array $element) {
            if ($element['tag'] === '01') {
                $element['value'] = '12';
            }

            return $element;
        }, $elements);

        $elements = array_values(array_filter($elements, fn (array $element) => $element['tag'] !== '54'));

        $currencyIndex = null;

        foreach ($elements as $index => $element) {
            if ($element['tag'] === '53') {
                $currencyIndex = $index;
                break;
            }
        }

        if ($currencyIndex === null) {
            throw new InvalidArgumentException('QRIS string is missing the currency tag (53).');
        }

        array_splice($elements, $currencyIndex + 1, 0, [['tag' => '54', 'value' => (string) $amount]]);

        $output = self::buildTlv($elements).'6304';

        return $output.self::crc16($output);
    }

    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $crc ^= (ord($data[$i]) << 8);

            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    private static function assertValidCrc(string $qris): void
    {
        if (strlen($qris) < 8 || substr($qris, -8, 4) !== '6304') {
            throw new InvalidArgumentException('QRIS string is missing a valid CRC tag (63).');
        }

        $body = substr($qris, 0, -4);
        $expected = strtoupper(substr($qris, -4));
        $actual = self::crc16($body);

        if ($expected !== $actual) {
            throw new InvalidArgumentException("QRIS CRC mismatch: expected {$expected}, computed {$actual}.");
        }
    }

    /**
     * @return array<int, array{tag: string, value: string}>
     */
    private static function parseTlv(string $data): array
    {
        $elements = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $tag = substr($data, $offset, 2);
            $valueLength = (int) substr($data, $offset + 2, 2);
            $value = substr($data, $offset + 4, $valueLength);

            $elements[] = ['tag' => $tag, 'value' => $value];
            $offset += 4 + $valueLength;
        }

        return $elements;
    }

    /**
     * @param  array<int, array{tag: string, value: string}>  $elements
     */
    private static function buildTlv(array $elements): string
    {
        $output = '';

        foreach ($elements as $element) {
            $output .= $element['tag'].str_pad((string) strlen($element['value']), 2, '0', STR_PAD_LEFT).$element['value'];
        }

        return $output;
    }
}
