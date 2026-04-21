<?php
/**
 * AIPG_Provider_Gemini
 *
 * Google Gemini REST API (generateContent endpoint).
 */

defined( 'ABSPATH' ) || exit;

class AIPG_Provider_Gemini extends AIPG_Provider_Base {

    const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function generate( string $prompt ) {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error( 'aipg_no_api_key', __( 'Gemini API key is not configured.', 'ai-post-generator' ) );
        }

        $endpoint = self::API_BASE . $this->model . ':generateContent?key=' . $this->api_key;

        $body = [
            'contents'         => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $prompt ] ] ] ],
            'generationConfig' => [ 'temperature' => 0.8, 'maxOutputTokens' => 2048, 'topP' => 0.95 ],
            'safetySettings'   => [
                [ 'category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
                [ 'category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
                [ 'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
                [ 'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE' ],
            ],
        ];

        $response = wp_remote_post( $endpoint, [
            'method'  => 'POST',
            'timeout' => 60,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? "HTTP $code";
            return new \WP_Error( 'aipg_api_error', $msg );
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ( empty( $text ) ) {
            $finish = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            return new \WP_Error( 'aipg_empty_response', "Gemini returned empty response. Finish reason: $finish" );
        }

        return $text;
    }
}
