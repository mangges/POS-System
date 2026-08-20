<?php

namespace Tests\Unit\Services\Qris;

use App\Services\Qris\QrisConverter;
use InvalidArgumentException;
use Tests\TestCase;

class QrisConverterTest extends TestCase
{
    private const STATIC_QRIS = '0002010102112614TESTMERCHANT015204581253033605802ID5909TOKO TEST6007JAKARTA63041066';

    public function test_crc16_matches_known_ccitt_false_test_vector(): void
    {
        // Standard CRC-16/CCITT-FALSE check value for ASCII "123456789" is 0x29B1.
        $this->assertSame('29B1', QrisConverter::crc16('123456789'));
    }

    public function test_to_dynamic_rewrites_point_of_initiation_inserts_amount_and_recomputes_crc(): void
    {
        $dynamic = QrisConverter::toDynamic(self::STATIC_QRIS, 50000);

        $this->assertSame(
            '0002010102122614TESTMERCHANT015204581253033605405500005802ID5909TOKO TEST6007JAKARTA63040390',
            $dynamic
        );
    }

    public function test_to_dynamic_throws_when_crc_does_not_match(): void
    {
        $corrupted = substr(self::STATIC_QRIS, 0, -4).'0000';

        $this->expectException(InvalidArgumentException::class);

        QrisConverter::toDynamic($corrupted, 50000);
    }

    public function test_to_dynamic_throws_instead_of_hanging_on_malformed_tlv_length_field(): void
    {
        // Real TLV-shaped body ("00" tag, "02" length, "01" value) followed by a
        // deliberately corrupted length field ("-4" instead of two digits), which
        // casts to a negative int under naive `(int)` parsing and can stall the
        // TLV walker's offset advancement forever.
        $corruptBody = '00'.'02'.'01'.'99'.'-4';
        $bodyWithCrcTag = $corruptBody.'6304';
        $crc = QrisConverter::crc16($bodyWithCrcTag);
        $malformed = $bodyWithCrcTag.$crc;

        $this->expectException(InvalidArgumentException::class);

        QrisConverter::toDynamic($malformed, 50000);
    }
}
