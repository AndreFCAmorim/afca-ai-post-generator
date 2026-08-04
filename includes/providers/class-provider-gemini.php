<?php
/**
 * AIPG_Provider_Gemini
 *
 * Google Gemini (generateContent) API.
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Provider_Gemini extends AIPG_Provider_Base {

    public function generate( string $prompt ) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode( $this->model )
            . ':generateContent?key=' . rawurlencode( $this->api_key );

        $data = $this->http_post(
            $url,
            [],
            [
                'contents' => [
                    [
                        'parts' => [
                            [ 'text' => $prompt ],
                        ],
                    ],
                ],
            ]
        );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ( empty( $text ) ) {
            return new \WP_Error( 'afca_aipg_empty_response', 'Gemini returned an empty content payload.' );
        }

        return $text;
    }
}
