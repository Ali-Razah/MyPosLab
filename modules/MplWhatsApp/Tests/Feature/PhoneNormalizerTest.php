<?php

namespace Modules\MplWhatsApp\Tests\Feature;

use InvalidArgumentException;
use Modules\MplWhatsApp\Services\PhoneNormalizer;
use Modules\MplWhatsApp\Tests\MplWhatsAppTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PhoneNormalizerTest extends MplWhatsAppTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validNumbers(): array
    {
        return [
            'local mobile' => [ '0501234567', '971501234567' ],
            'local mobile with spaces' => [ '050 123 4567', '971501234567' ],
            'local mobile with dashes' => [ '050-123-4567', '971501234567' ],
            'international with plus' => [ '+971 50 123 4567', '971501234567' ],
            'international with 00' => [ '00971501234567', '971501234567' ],
            'international digits only' => [ '971501234567', '971501234567' ],
            'without trunk zero' => [ '50 123 4567', '971501234567' ],
            'plus with trunk zero typo' => [ '+971 050 123 4567', '971501234567' ],
            'parentheses' => [ '(050) 123-4567', '971501234567' ],
            'dubai landline' => [ '04 123 4567', '97141234567' ],
            'other country with plus' => [ '+44 7911 123456', '447911123456' ],
            'other country with 00' => [ '0044 7911 123456', '447911123456' ],
        ];
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'empty' => [ '' ],
            'null' => [ null ],
            'letters' => [ 'call me maybe' ],
            'email' => [ 'customer@example.com' ],
            'too short' => [ '12345' ],
            'uae too short' => [ '+971 50 123' ],
            'uae too long' => [ '+971 50 123 45678' ],
            'too long' => [ '+1234567890123456' ],
            'repeated digits' => [ '0500000000' ],
            'plus in the middle' => [ '050+1234567' ],
        ];
    }

    #[DataProvider( 'validNumbers' )]
    public function test_it_normalises_valid_numbers( string $input, string $expected ): void
    {
        $this->assertSame( $expected, app( PhoneNormalizer::class )->normalize( $input, '971' ) );
    }

    #[DataProvider( 'invalidNumbers' )]
    public function test_it_rejects_invalid_numbers( ?string $input ): void
    {
        $this->expectException( InvalidArgumentException::class );

        app( PhoneNormalizer::class )->normalize( $input, '971' );
    }

    public function test_try_normalize_returns_null_for_invalid_numbers(): void
    {
        $this->assertNull( app( PhoneNormalizer::class )->tryNormalize( 'abc', '971' ) );
        $this->assertSame( '971501234567', app( PhoneNormalizer::class )->tryNormalize( '0501234567', '971' ) );
    }
}
