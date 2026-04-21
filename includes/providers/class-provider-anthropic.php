<?php
/**
 * AIPG_Provider_Anthropic
 *
 * Anthropic Messages API (Claude models).
 * Uses the /v1/messages endpoint which has a different shape
 * from the OpenAI-compatible providers.
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Provider_Anthropic extends AIPG_Provider_Base {

    const API_URL = 'https://api.anthropic.com/v1/messages';
    const API_VERSION = '2023-06-01';

    public function generate( string $prompt ) {
        $data = $this->http_post(
            self::API_URL,
            [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => self::API_VERSION,
            ],
            [
                'model'      => $this->model,
                'max_tokens' => 2048,
                'messages'   => [
                    [ 'role' => 'user', 'content' => $prompt ],
                ],
            ]
        );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        // Claude response: content[0].text
        $text = $data['content'][0]['text'] ?? '';
        if ( empty( $text ) ) {
            $reason = $data['stop_reason'] ?? 'unknown';
            return new \WP_Error( 'aipg_empty_response', "Claude returned empty response. Stop reason: $reason" );
        }

        return $text;
    }
}
