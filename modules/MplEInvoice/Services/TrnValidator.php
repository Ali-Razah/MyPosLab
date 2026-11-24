<?php

namespace Modules\MplEInvoice\Services;

/**
 * Validates UAE Tax Registration Numbers (TRN).
 *
 * A TRN issued by the Federal Tax Authority is made of exactly 15 digits.
 */
class TrnValidator
{
    /**
     * Laravel validation rule string for an optional TRN.
     */
    const RULE = 'nullable|digits:15';

    public static function isValid( mixed $trn ): bool
    {
        if ( ! is_string( $trn ) && ! is_int( $trn ) ) {
            return false;
        }

        return preg_match( '/^\d{15}$/', (string) $trn ) === 1;
    }

    /**
     * Removes the whitespace a user may paste around a TRN.
     */
    public static function normalize( mixed $trn ): ?string
    {
        if ( $trn === null ) {
            return null;
        }

        $trn = preg_replace( '/\s+/', '', (string) $trn );

        return $trn === '' ? null : $trn;
    }
}
