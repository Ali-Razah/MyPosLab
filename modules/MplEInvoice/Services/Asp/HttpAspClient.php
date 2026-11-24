<?php

namespace Modules\MplEInvoice\Services\Asp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Services\EInvoiceSettings;
use Throwable;

/**
 * Generic ASP integration: POSTs the UBL XML to the configured endpoint
 * using a bearer token and interprets a JSON (or plain) response.
 *
 * Expected (but tolerant) JSON response shape:
 * { "status": "accepted|submitted|received|rejected", "reference": "...", "errors": [...] }
 */
class HttpAspClient implements AspClient
{
    const TIMEOUT_SECONDS = 30;

    public function __construct( private EInvoiceSettings $settings ) {}

    public function submit( EInvoiceDocument $document ): AspSubmissionResult
    {
        $endpoint = $this->settings->aspEndpoint();

        if (
            $endpoint === ''
            || filter_var( $endpoint, FILTER_VALIDATE_URL ) === false
            || ! in_array( strtolower( (string) parse_url( $endpoint, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true )
        ) {
            return AspSubmissionResult::failed( null, __m( 'The ASP endpoint URL is not configured.', 'MplEInvoice' ) );
        }

        try {
            $request = Http::timeout( self::TIMEOUT_SECONDS )
                ->accept( 'application/json' )
                ->withHeaders( [
                    'X-Document-Number' => $document->number,
                    'X-Document-UUID' => $document->uuid,
                    'X-Document-Type' => $document->type,
                ] );

            $token = $this->settings->aspToken();

            if ( $token !== '' ) {
                $request = $request->withToken( $token );
            }

            $response = $request->withBody( $document->xml, 'application/xml' )->post( $endpoint );
        } catch ( ConnectionException $exception ) {
            return AspSubmissionResult::failed( null, sprintf( __m( 'Unable to reach the ASP: %s', 'MplEInvoice' ), $exception->getMessage() ) );
        } catch ( Throwable $exception ) {
            return AspSubmissionResult::failed( null, sprintf( __m( 'The ASP submission failed: %s', 'MplEInvoice' ), $exception->getMessage() ) );
        }

        return $this->interpret( $response );
    }

    private function interpret( Response $response ): AspSubmissionResult
    {
        $body = (string) $response->body();
        $json = $response->json();
        $json = is_array( $json ) ? $json : [];
        $reference = $this->firstString( $json, [ 'reference', 'asp_reference', 'id', 'document_id', 'submission_id', 'uuid' ] );
        $status = strtolower( (string) ( $this->firstString( $json, [ 'status', 'state', 'result' ] ) ?? '' ) );
        $error = $this->extractError( $json ) ?? sprintf( __m( 'The ASP responded with HTTP %s.', 'MplEInvoice' ), $response->status() );

        if ( $response->successful() ) {
            if ( in_array( $status, [ 'rejected', 'invalid', 'refused', 'error' ], true ) ) {
                return AspSubmissionResult::rejected( $reference, $body, $error );
            }

            if ( in_array( $status, [ 'accepted', 'valid', 'validated', 'delivered', 'cleared', 'reported' ], true ) ) {
                return AspSubmissionResult::accepted( $reference, $body );
            }

            return AspSubmissionResult::submitted( $reference, $body );
        }

        /**
         * 4xx responses (other than throttling/timeouts) mean the ASP refused
         * the document itself, server side errors are transient failures.
         */
        if ( $response->clientError() && ! in_array( $response->status(), [ 401, 403, 408, 429 ], true ) ) {
            return AspSubmissionResult::rejected( $reference, $body, $error );
        }

        return AspSubmissionResult::failed( $body, $error );
    }

    /**
     * @param array<string, mixed> $json
     * @param array<int, string>   $keys
     */
    private function firstString( array $json, array $keys ): ?string
    {
        foreach ( $keys as $key ) {
            $value = data_get( $json, $key ) ?? data_get( $json, 'data.' . $key );

            if ( is_scalar( $value ) && (string) $value !== '' ) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function extractError( array $json ): ?string
    {
        $errors = data_get( $json, 'errors' ) ?? data_get( $json, 'error' ) ?? data_get( $json, 'message' );

        if ( is_string( $errors ) && $errors !== '' ) {
            return $errors;
        }

        if ( is_array( $errors ) && ! empty( $errors ) ) {
            return collect( $errors )->flatten()->filter( fn( $value ) => is_scalar( $value ) )->join( '; ' );
        }

        return null;
    }
}
