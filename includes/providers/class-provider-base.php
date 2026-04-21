<?php
/**
 * AIPG_Provider_Base
 *
 * Abstract base class that every AI provider must extend.
 * Enforces a common interface so the Generator doesn't care
 * which provider is active.
 */

defined( 'ABSPATH' ) || exit;

abstract class AIPG_Provider_Base {

    protected string $api_key;
    protected string $model;

    public function __construct( string $api_key, string $model ) {
        $this->api_key = $api_key;
        $this->model   = $model;
    }

    /**
     * Send a prompt and return the generated text, or a WP_Error.
     *
     * @param  string $prompt
     * @return string|\WP_Error
     */
    abstract public function generate( string $prompt );

    /**
     * Quick connectivity test.
     *
     * @return true|\WP_Error
     */
    public function test_connection() {
        $result = $this->generate( 'Reply with exactly the text: CONNECTION_OK' );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }

    // ── Shared HTTP helper ────────────────────────────────────────────────────

    /**
     * Perform a POST request via wp_remote_post and return decoded JSON body,
     * or a WP_Error.
     *
     * @param  string $url
     * @param  array  $headers
     * @param  array  $body_array   Will be JSON-encoded automatically
     * @param  int    $timeout
     * @return array|\WP_Error
     */
    protected function http_post( string $url, array $headers, array $body_array, int $timeout = 60 ) {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error( 'aipg_no_api_key', __( 'API key is not configured for this provider.', 'ai-post-generator' ) );
        }

        $response = wp_remote_post( $url, [
            'method'  => 'POST',
            'timeout' => $timeout,
            'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
            'body'    => wp_json_encode( $body_array ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            // Try common error message paths
            $msg = $data['error']['message']
                ?? $data['error']
                ?? $data['message']
                ?? "HTTP $code";
            if ( is_array( $msg ) ) {
                $msg = wp_json_encode( $msg );
            }
            return new \WP_Error( 'aipg_api_error', (string) $msg );
        }

        return $data;
    }
}
