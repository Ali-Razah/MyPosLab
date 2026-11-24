<?php

namespace Modules\MplWhatsApp\Services;

use InvalidArgumentException;

/**
 * Converts the phone formats cashiers type into the international digits-only
 * format WhatsApp expects (E.164 without the "+"), e.g. "971501234567".
 */
class PhoneNormalizer
{
    /**
     * UAE numbers are 971 + 8 digits (landline, e.g. 04 xxx xxxx)
     * or 971 + 9 digits (mobile, e.g. 05x xxx xxxx).
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const NATIONAL_LENGTHS = [
        '971' => [ 8, 9 ],
    ];

    /**
     * Returns the normalised number, or throws when the input is obviously invalid.
     *
     * @throws InvalidArgumentException
     */
    public function normalize( ?string $phone, string $defaultCountryCode = WhatsAppConfig::DEFAULT_COUNTRY_CODE ): string
    {
        $raw = trim( (string) $phone );
        $countryCode = preg_replace( '/\D+/', '', $defaultCountryCode ) ?: WhatsAppConfig::DEFAULT_COUNTRY_CODE;

        if ( $raw === '' ) {
            throw new InvalidArgumentException( __m( 'The phone number is empty.', 'MplWhatsApp' ) );
        }

        /**
         * Only digits, spaces and the usual separators are accepted.
         * A "+" is only allowed as the first character.
         */
        if ( ! preg_match( '/^\+?[\d\s\-\.\(\)\/]+$/', $raw ) ) {
            throw new InvalidArgumentException( __m( 'The phone number contains invalid characters.', 'MplWhatsApp' ) );
        }

        $hasPlus = str_starts_with( $raw, '+' );
        $digits = preg_replace( '/\D+/', '', $raw );

        if ( $hasPlus ) {
            $international = $digits;
        } elseif ( str_starts_with( $digits, '00' ) ) {
            $international = substr( $digits, 2 );
        } elseif ( str_starts_with( $digits, '0' ) ) {
            $international = $countryCode . ltrim( $digits, '0' );
        } elseif ( str_starts_with( $digits, $countryCode ) && $this->isValidLength( $digits, $countryCode ) ) {
            $international = $digits;
        } else {
            $international = $countryCode . $digits;
        }

        /**
         * "+971 0 50 ..." (trunk zero kept after the country code) is a common typo.
         */
        if ( str_starts_with( $international, $countryCode . '0' ) ) {
            $international = $countryCode . ltrim( substr( $international, strlen( $countryCode ) ), '0' );
        }

        if ( str_starts_with( $international, '0' ) || ! $this->isValidLength( $international, $countryCode ) ) {
            throw new InvalidArgumentException( sprintf(
                __m( 'The phone number "%s" is not a valid WhatsApp number.', 'MplWhatsApp' ),
                $raw
            ) );
        }

        if ( preg_match( '/^(\d)\1+$/', substr( $international, -8 ) ) ) {
            throw new InvalidArgumentException( sprintf(
                __m( 'The phone number "%s" is not a valid WhatsApp number.', 'MplWhatsApp' ),
                $raw
            ) );
        }

        return $international;
    }

    /**
     * Same as normalize() but returns null instead of throwing.
     */
    public function tryNormalize( ?string $phone, string $defaultCountryCode = WhatsAppConfig::DEFAULT_COUNTRY_CODE ): ?string
    {
        try {
            return $this->normalize( $phone, $defaultCountryCode );
        } catch ( InvalidArgumentException $exception ) {
            return null;
        }
    }

    private function isValidLength( string $international, string $countryCode ): bool
    {
        foreach ( self::NATIONAL_LENGTHS as $code => [ $min, $max ] ) {
            if ( str_starts_with( $international, (string) $code ) ) {
                $national = strlen( $international ) - strlen( (string) $code );

                return $national >= $min && $national <= $max;
            }
        }

        /**
         * E.164 allows at most 15 digits; anything under 8 is not a real subscriber number.
         */
        return strlen( $international ) >= 8 && strlen( $international ) <= 15;
    }
}
